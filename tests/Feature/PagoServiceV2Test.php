<?php

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
use App\Domain\Pagos\PagoService;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'Jefe de operaciones', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'Jefe de creditos', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'Asesor', 'guard_name' => 'web']);

    foreach (['prestamos.aprobar', 'prestamos.desembolsar', 'prestamos.ver_todos', 'pagos.aprobar', 'prestamos.crear', 'prestamos.reducir_monto'] as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
    }

    Role::findByName('Jefe de operaciones')->givePermissionTo(['prestamos.desembolsar', 'pagos.aprobar']);
    Role::findByName('Jefe de creditos')->givePermissionTo(['prestamos.aprobar', 'prestamos.reducir_monto']);
    Role::findByName('Asesor')->givePermissionTo(['prestamos.crear']);

    $this->jefeOperaciones = User::factory()->create();
    $this->jefeOperaciones->assignRole('Jefe de operaciones');

    $this->jefeCreditos = User::factory()->create();
    $this->jefeCreditos->assignRole('Jefe de creditos');

    $persona = Persona::factory()->create();
    $this->asesor = Asesor::factory()->create(['persona_id' => $persona->id]);

    $this->grupo = Grupo::factory()->create([
        'asesor_id'    => $this->asesor->id,
        'estado_grupo' => 'ACTIVO',
    ]);

    $this->prestamo = Prestamo::factory()->create([
        'grupo_id'         => $this->grupo->id,
        'estado'           => Prestamo::ESTADO_POR_DESEMBOLSAR,
        'tasa_interes'     => 17,
        'cantidad_cuotas'  => 4,
        'frecuencia'       => 'semanal',
        'fecha_desembolso' => now()->toDateString(),
        'monto_devolver'   => 1476.00,
    ]);

    $this->clientes = [];
    foreach ([400, 400, 400] as $monto) {
        $persona  = Persona::factory()->create();
        $cliente  = Cliente::factory()->create(['asesor_id' => $this->asesor->id, 'persona_id' => $persona->id]);
        $this->clientes[] = $cliente;

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

        PrestamoIndividual::factory()->create([
            'prestamo_id'                    => $this->prestamo->id,
            'cliente_id'                     => $cliente->id,
            'monto_prestado_individual'      => $monto,
            'interes'                        => $interes,
            'seguro'                         => $seguro,
            'monto_devolver_individual'      => $devolver,
            'monto_cuota_prestamo_individual' => round($devolver / 4, 2),
            'estado'                         => 'Aprobado',
        ]);
    }
});

// ====================================================
// FASE 1.3 — Event-driven: CuotaIndividual se crea al desembolsar
// ====================================================

it('desembolso crea cuota individual por cada integrante y cuota', function () {
    $totalEsperado = count($this->clientes) * $this->prestamo->cantidad_cuotas;

    expect(CuotaIndividual::where('prestamo_id', $this->prestamo->id)->count())->toBe(0);

    $this->prestamo->updateQuietly(['estado' => Prestamo::ESTADO_FIRMADO]);
    $this->prestamo->desembolsar(now()->toDateString());

    expect(CuotaIndividual::where('prestamo_id', $this->prestamo->id)->count())->toBe($totalEsperado);
});

it('cada cuota individual tiene monto capital e interes correctos', function () {
    $this->prestamo->updateQuietly(['estado' => Prestamo::ESTADO_FIRMADO]);
    $this->prestamo->desembolsar(now()->toDateString());

    $primerCliente = $this->clientes[0];
    $pi = PrestamoIndividual::where('prestamo_id', $this->prestamo->id)
        ->where('cliente_id', $primerCliente->id)
        ->first();

    $cuotasCliente = CuotaIndividual::where('prestamo_id', $this->prestamo->id)
        ->where('cliente_id', $primerCliente->id)
        ->get();

    expect($cuotasCliente)->toHaveCount(4);

    $capitalEsperado = round($pi->monto_prestado_individual / 4, 2);
    $interesEsperado = round($pi->interes / 4, 2);

    foreach ($cuotasCliente as $cuota) {
        expect((float) $cuota->monto_capital_original)->toEqualWithDelta($capitalEsperado, 0.01);
        expect((float) $cuota->monto_interes_original)->toEqualWithDelta($interesEsperado, 0.01);
        expect($cuota->estado)->toBe('pendiente');
    }
});

// ====================================================
// FASE 2.3 — PagoService: aprobar pago crea AplicacionPago
// ====================================================

it('aprobar pago crea aplicacion pago por cada integrante', function () {
    $this->prestamo->updateQuietly(['estado' => Prestamo::ESTADO_FIRMADO]);
    $this->prestamo->desembolsar(now()->toDateString());

    $cuotaGrupal = CuotasGrupales::where('prestamo_id', $this->prestamo->id)->orderBy('numero_cuota')->first()
        ?? CuotasGrupales::create([
            'prestamo_id'         => $this->prestamo->id,
            'numero_cuota'        => 1,
            'monto_cuota_grupal'  => 369.00,
            'fecha_vencimiento'   => now()->addWeek(),
            'estado_cuota_grupal' => 'vigente',
            'estado_pago'         => 'pendiente',
        ]);

    $pago = Pago::create([
        'cuota_grupal_id'  => $cuotaGrupal->id,
        'tipo_pago'        => 'pago_completo',
        'codigo_operacion' => 'TEST-001',
        'monto_pagado'     => $cuotaGrupal->monto_cuota_grupal,
        'fecha_pago'       => now(),
        'estado_pago'      => 'pendiente',
    ]);

    $this->actingAs($this->jefeOperaciones);
    app(PagoService::class)->aprobarPago($pago);

    $aplicaciones = AplicacionPago::where('pago_id', $pago->id)->get();
    expect($aplicaciones->count())->toBeGreaterThan(0);

    $totalAplicado = $aplicaciones->sum(fn ($a) => (float) $a->monto_aplicado_capital + (float) $a->monto_aplicado_interes);
    expect($totalAplicado)->toBeGreaterThan(0);

    foreach ($aplicaciones as $ap) {
        expect($ap->cuota_id)->not->toBeNull();
        expect((float) $ap->monto_aplicado_capital)->toBeGreaterThanOrEqual(0);
        expect((float) $ap->monto_aplicado_interes)->toBeGreaterThanOrEqual(0);
    }
});

it('aprobar pago tiene aplicaciones con cuota individual valida', function () {
    $this->prestamo->updateQuietly(['estado' => Prestamo::ESTADO_FIRMADO]);
    $this->prestamo->desembolsar(now()->toDateString());

    $cuotaGrupal = CuotasGrupales::where('prestamo_id', $this->prestamo->id)->orderBy('numero_cuota')->first()
        ?? CuotasGrupales::create([
            'prestamo_id'         => $this->prestamo->id,
            'numero_cuota'        => 1,
            'monto_cuota_grupal'  => 369.00,
            'fecha_vencimiento'   => now()->addWeek(),
            'estado_cuota_grupal' => 'vigente',
            'estado_pago'         => 'pendiente',
        ]);

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

    $aplicaciones = AplicacionPago::where('pago_id', $pago->id)->get();
    expect($aplicaciones->count())->toBeGreaterThan(0);

    foreach ($aplicaciones as $ap) {
        expect($ap->cuota_id)->not->toBeNull();
        expect(CuotaIndividual::find($ap->cuota_id))->not->toBeNull();
    }
});
