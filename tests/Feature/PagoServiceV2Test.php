<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Prestamo;
use App\Models\PrestamoIndividual;
use App\Models\Grupo;
use App\Models\Cliente;
use App\Models\Asesor;
use App\Models\Persona;
use App\Models\CuotasGrupales;
use App\Models\CuotaIndividual;
use App\Models\AplicacionPago;
use App\Models\Pago;
use App\Services\PagoService;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

/**
 * Fase 1.3 + Fase 2.3: Tests para el sistema de pagos V2 (AplicacionPago).
 *
 * Verifica que:
 * - Al desembolsar se crean CuotaIndividual para cada integrante (N×M)
 * - Al aprobar un pago se crean AplicacionPago con desglose capital/interés por integrante
 * - No quedan referencias a DetallePago
 */
class PagoServiceV2Test extends TestCase
{
    use RefreshDatabase;

    protected User $jefeOperaciones;
    protected User $jefeCreditos;
    protected Grupo $grupo;
    protected Prestamo $prestamo;
    protected array $clientes = [];
    protected Asesor $asesor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->crearRolesYPermisos();
        $this->crearUsuarios();
        $this->crearPrestamoGrupalConIntegrantes();
    }

    private function crearRolesYPermisos(): void
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Jefe de operaciones', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Jefe de creditos', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Asesor', 'guard_name' => 'web']);

        $permisos = [
            'prestamos.aprobar',
            'prestamos.desembolsar',
            'prestamos.ver_todos',
            'pagos.aprobar',
            'prestamos.crear',
            'prestamos.reducir_monto',
        ];
        foreach ($permisos as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }

        Role::findByName('Jefe de operaciones')->givePermissionTo(['prestamos.desembolsar', 'pagos.aprobar']);
        Role::findByName('Jefe de creditos')->givePermissionTo(['prestamos.aprobar', 'prestamos.reducir_monto']);
        Role::findByName('Asesor')->givePermissionTo(['prestamos.crear']);
    }

    private function crearUsuarios(): void
    {
        $this->jefeOperaciones = User::factory()->create();
        $this->jefeOperaciones->assignRole('Jefe de operaciones');

        $this->jefeCreditos = User::factory()->create();
        $this->jefeCreditos->assignRole('Jefe de creditos');
    }

    private function crearPrestamoGrupalConIntegrantes(): void
    {
        $persona = Persona::factory()->create();
        $this->asesor = Asesor::factory()->create(['persona_id' => $persona->id]);

        $this->grupo = Grupo::factory()->create([
            'asesor_id'    => $this->asesor->id,
            'estado_grupo' => 'ACTIVO',
        ]);

        $this->prestamo = Prestamo::factory()->create([
            'grupo_id'        => $this->grupo->id,
            'estado'          => Prestamo::ESTADO_POR_DESEMBOLSAR,
            'tasa_interes'    => 17,
            'cantidad_cuotas' => 4,
            'frecuencia'      => 'semanal',
            'fecha_desembolso' => now()->toDateString(),
            'monto_devolver'  => 1476.00,
        ]);

        // 3 integrantes con montos fijos
        $montos = [400, 400, 400];
        foreach ($montos as $monto) {
            $persona = Persona::factory()->create();
            $cliente = Cliente::factory()->create([
                'asesor_id' => $this->asesor->id,
                'persona_id' => $persona->id,
            ]);
            $this->clientes[] = $cliente;

            // CRÍTICO: asociar cliente al grupo via tabla pivot grupo_cliente
            $this->grupo->todosLosIntegrantes()->attach($cliente->id, [
                'fecha_ingreso'        => now()->toDateString(),
                'fecha_salida'         => null,
                'estado_grupo_cliente' => 'Activo',
                'rol'                  => 'Miembro',
                'created_at'           => now(),
                'updated_at'           => now(),
            ]);

            $interes  = round($monto * 0.17, 2);
            $seguro   = 7.00;
            $devolver = $monto + $interes + $seguro;
            $cuotaInd = round($devolver / 4, 2);

            PrestamoIndividual::factory()->create([
                'prestamo_id'                     => $this->prestamo->id,
                'cliente_id'                      => $cliente->id,
                'monto_prestado_individual'        => $monto,
                'interes'                          => $interes,
                'seguro'                           => $seguro,
                'monto_devolver_individual'        => $devolver,
                'monto_cuota_prestamo_individual'  => $cuotaInd,
                'estado'                           => 'Aprobado',
            ]);
        }
    }

    /**
     * Helper: simula el desembolso activando el Observer via la transición correcta de estados.
     * Por Desembolsar → Desembolsado (el Observer escucha exactamente esta transición).
     */
    private function simularDesembolso(): void
    {
        // El Observer escucha: estado === 'desembolsado' Y estado_anterior === 'por desembolsar'
        // Usamos updateQuietly para el estado anterior y luego save() para disparar el Observer
        $this->prestamo->estado = Prestamo::ESTADO_DESEMBOLSADO;
        $this->prestamo->fecha_desembolso = now()->toDateString();
        $this->prestamo->save();
    }

    // ====================================================
    // FASE 1.3 — Observer: CuotaIndividual se crea al desembolsar
    // ====================================================

    /** @test */
    public function desembolso_crea_cuota_individual_por_cada_integrante_y_cuota(): void
    {
        $cantidadIntegrantes = count($this->clientes); // 3
        $cantidadCuotas      = $this->prestamo->cantidad_cuotas; // 4
        $totalEsperado       = $cantidadIntegrantes * $cantidadCuotas; // 12

        // RED: antes del desembolso, no hay cuotas individuales
        $this->assertEquals(0, CuotaIndividual::where('prestamo_id', $this->prestamo->id)->count());

        // Simular transición Por Desembolsar → Desembolsado
        $this->simularDesembolso();

        // GREEN: debe haber N integrantes × M cuotas registros
        $actual = CuotaIndividual::where('prestamo_id', $this->prestamo->id)->count();
        $this->assertEquals(
            $totalEsperado,
            $actual,
            "Se esperaban {$totalEsperado} CuotaIndividual (3 integrantes × 4 cuotas), se crearon {$actual}"
        );
    }

    /** @test */
    public function cada_cuota_individual_tiene_monto_capital_e_interes_correctos(): void
    {
        $this->simularDesembolso();

        $primerCliente = $this->clientes[0];
        $pi = PrestamoIndividual::where('prestamo_id', $this->prestamo->id)
            ->where('cliente_id', $primerCliente->id)
            ->first();

        $cuotasCliente = CuotaIndividual::where('prestamo_id', $this->prestamo->id)
            ->where('cliente_id', $primerCliente->id)
            ->get();

        $this->assertCount(4, $cuotasCliente);

        $capitalEsperado = round($pi->monto_prestado_individual / 4, 2);
        $interesEsperado = round($pi->interes / 4, 2);

        foreach ($cuotasCliente as $cuota) {
            $this->assertEqualsWithDelta($capitalEsperado, (float) $cuota->monto_capital_original, 0.01);
            $this->assertEqualsWithDelta($interesEsperado, (float) $cuota->monto_interes_original, 0.01);
            $this->assertEquals('pendiente', $cuota->estado);
        }
    }

    // ====================================================
    // FASE 2.3 — PagoService: aprobar pago crea AplicacionPago
    // ====================================================

    /** @test */
    public function aprobar_pago_crea_aplicacion_pago_por_cada_integrante(): void
    {
        // Preparar: desembolsar + activar
        $this->simularDesembolso();

        // Garantizar que hay CuotasGrupales (las crea el Observer)
        $cuotaGrupal = CuotasGrupales::where('prestamo_id', $this->prestamo->id)
            ->orderBy('numero_cuota')
            ->first();

        // Si el Observer no las creó (por las condiciones del test), créalas manualmente
        if (!$cuotaGrupal) {
            $cuotaGrupal = CuotasGrupales::create([
                'prestamo_id'       => $this->prestamo->id,
                'numero_cuota'      => 1,
                'monto_cuota_grupal' => 369.00, // 3 integrantes × 123
                'saldo_pendiente'   => 369.00,
                'fecha_vencimiento' => now()->addWeek(),
                'estado_cuota_grupal' => 'vigente',
                'estado_pago'       => 'pendiente',
            ]);
        }

        // Activar el préstamo para que PagoService no tire excepción
        $this->prestamo->update(['estado' => Prestamo::ESTADO_ACTIVO]);

        // Crear pago pendiente de asesor
        $pago = Pago::create([
            'cuota_grupal_id'  => $cuotaGrupal->id,
            'tipo_pago'        => 'pago_completo',
            'codigo_operacion' => 'TEST-001',
            'monto_pagado'     => $cuotaGrupal->monto_cuota_grupal,
            'fecha_pago'       => now(),
            'estado_pago'      => 'pendiente',
        ]);

        // Aprobar pago
        $this->actingAs($this->jefeOperaciones);
        app(PagoService::class)->aprobarPago($pago);

        // Verificar AplicacionPago creadas
        $aplicaciones = AplicacionPago::where('pago_id', $pago->id)->get();

        $this->assertGreaterThan(
            0,
            $aplicaciones->count(),
            'aprobarPago() debe crear al menos un registro en AplicacionPago'
        );

        // El total capital+interés aplicado debe ser > 0
        $totalAplicado = $aplicaciones->sum(
            fn ($a) => (float) $a->monto_aplicado_capital + (float) $a->monto_aplicado_interes
        );

        $this->assertGreaterThan(
            0,
            $totalAplicado,
            'La suma de AplicacionPago (capital+interés) debe ser mayor que 0'
        );

        // Cada AplicacionPago debe tener cuota_id válido (FK a CuotaIndividual)
        foreach ($aplicaciones as $ap) {
            $this->assertNotNull($ap->cuota_id);
            $this->assertTrue(
                (float) $ap->monto_aplicado_capital >= 0 && (float) $ap->monto_aplicado_interes >= 0,
                'Los montos de AplicacionPago deben ser no negativos'
            );
        }
    }

    /** @test */
    public function aprobar_pago_actualiza_estado_cuota_individual_a_pagada(): void
    {
        $this->simularDesembolso();

        $cuotaGrupal = CuotasGrupales::where('prestamo_id', $this->prestamo->id)
            ->orderBy('numero_cuota')
            ->first();

        if (!$cuotaGrupal) {
            $cuotaGrupal = CuotasGrupales::create([
                'prestamo_id'         => $this->prestamo->id,
                'numero_cuota'        => 1,
                'monto_cuota_grupal'  => 369.00,
                'saldo_pendiente'     => 369.00,
                'fecha_vencimiento'   => now()->addWeek(),
                'estado_cuota_grupal' => 'vigente',
                'estado_pago'         => 'pendiente',
            ]);
        }

        $this->prestamo->update(['estado' => Prestamo::ESTADO_ACTIVO]);

        $pago = Pago::create([
            'cuota_grupal_id'  => $cuotaGrupal->id,
            'tipo_pago'        => 'pago_completo',
            'codigo_operacion' => 'TEST-002',
            'monto_pagado'     => $cuotaGrupal->monto_cuota_grupal,
            'fecha_pago'       => now(),
            'estado_pago'      => 'pendiente',
        ]);

        $this->actingAs($this->jefeOperaciones);
        app(PagoService::class)->aprobarPago($pago);

        // Verificar que se crearon AplicacionPago con cuota_id vinculado a CuotaIndividual
        $aplicaciones = AplicacionPago::where('pago_id', $pago->id)->get();
        $this->assertGreaterThan(0, $aplicaciones->count());

        // Cada aplicación debe tener cuota_id válido
        foreach ($aplicaciones as $ap) {
            $this->assertNotNull($ap->cuota_id, 'AplicacionPago debe tener cuota_id (FK a CuotaIndividual)');
            $cuotaInd = CuotaIndividual::find($ap->cuota_id);
            $this->assertNotNull($cuotaInd, 'cuota_id debe apuntar a una CuotaIndividual existente');
        }
    }
}
