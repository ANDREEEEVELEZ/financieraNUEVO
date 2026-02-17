<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Prestamo;
use App\Models\PrestamoIndividual;
use App\Models\Grupo;
use App\Models\Cliente;
use App\Models\Asesor;
use App\Models\Persona;
use App\Models\CuotasGrupales;
use App\Models\CuotaIndividual;
use App\Models\MovimientoFinanciero;
use App\Policies\PrestamoPolicy;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

/**
 * Tests para Fase 8: Verificación del nuevo flujo de estados,
 * reducción de monto, permisos y creación de cuotas.
 */
class PrestamoWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $jefeCreditos;
    protected User $jefeOperaciones;
    protected User $asesor;
    protected Asesor $asesorRecord;
    protected Grupo $grupo;
    protected Prestamo $prestamo;

    protected function setUp(): void
    {
        parent::setUp();

        // Crear roles y permisos
        $this->createRolesAndPermissions();

        // Crear usuarios de prueba
        $this->createTestUsers();

        // Crear grupo y préstamo de prueba
        $this->createTestData();
    }

    protected function createRolesAndPermissions(): void
    {
        // Crear roles
        Role::create(['name' => 'super_admin', 'guard_name' => 'web']);
        Role::create(['name' => 'Jefe de creditos', 'guard_name' => 'web']);
        Role::create(['name' => 'Jefe de operaciones', 'guard_name' => 'web']);
        Role::create(['name' => 'Asesor', 'guard_name' => 'web']);

        // Crear permisos
        $permisos = [
            'prestamos.aprobar',
            'prestamos.rechazar',
            'prestamos.reducir_monto',
            'prestamos.desembolsar',
            'prestamos.crear',
            'prestamos.ver_todos',
        ];

        foreach ($permisos as $permiso) {
            Permission::create(['name' => $permiso, 'guard_name' => 'web']);
        }

        // Asignar permisos a roles
        $jc = Role::findByName('Jefe de creditos');
        $jc->givePermissionTo(['prestamos.aprobar', 'prestamos.rechazar', 'prestamos.reducir_monto']);

        $jo = Role::findByName('Jefe de operaciones');
        $jo->givePermissionTo(['prestamos.reducir_monto', 'prestamos.desembolsar']);

        $asesor = Role::findByName('Asesor');
        $asesor->givePermissionTo(['prestamos.crear']);
    }

    protected function createTestUsers(): void
    {
        // Super Admin
        $this->superAdmin = User::factory()->create(['name' => 'Super Admin']);
        $this->superAdmin->assignRole('super_admin');

        // Jefe de Créditos
        $this->jefeCreditos = User::factory()->create(['name' => 'Jefe Creditos']);
        $this->jefeCreditos->assignRole('Jefe de creditos');

        // Jefe de Operaciones
        $this->jefeOperaciones = User::factory()->create(['name' => 'Jefe Operaciones']);
        $this->jefeOperaciones->assignRole('Jefe de operaciones');

        // Asesor
        $this->asesor = User::factory()->create(['name' => 'Asesor Test']);
        $this->asesor->assignRole('Asesor');

        // Crear registro de Asesor
        $personaAsesor = Persona::factory()->create();
        $this->asesorRecord = Asesor::factory()->create([
            'persona_id' => $personaAsesor->id,
            'user_id' => $this->asesor->id,
            'estado_asesor' => 'ACTIVO'
        ]);
    }

    protected function createTestData(): void
    {
        // Crear grupo
        $this->grupo = Grupo::factory()->create([
            'asesor_id' => $this->asesorRecord->id,
            'estado_grupo' => 'ACTIVO'
        ]);

        // Crear clientes y asociarlos al grupo
        $clientes = Cliente::factory()->count(3)->create([
            'asesor_id' => $this->asesorRecord->id
        ]);

        // Crear préstamo en estado Pendiente
        $this->prestamo = Prestamo::factory()->create([
            'grupo_id' => $this->grupo->id,
            'estado' => Prestamo::ESTADO_PENDIENTE,
            'monto_total' => 4000,
            'tasa_interes' => 17,
            'plazo' => 4,
        ]);

        // Crear préstamos individuales
        foreach ($clientes as $index => $cliente) {
            PrestamoIndividual::factory()->create([
                'prestamo_id' => $this->prestamo->id,
                'cliente_id' => $cliente->id,
                'monto_prestamo' => 1000 + ($index * 500),
                'monto_interes' => 170 + ($index * 85),
                'monto_seguro' => 30,
                'estado' => 'Pendiente',
            ]);
        }

        // Actualizar monto total del préstamo
        $this->prestamo->sincronizarMontosTotal();
    }

    // ========================================
    // TEST 1: Flujo Completo de Estados
    // ========================================

    /** @test */
    public function test_flujo_pendiente_a_aprobado_a_por_desembolsar()
    {
        // Estado inicial: Pendiente
        $this->assertEquals(Prestamo::ESTADO_PENDIENTE, $this->prestamo->estado);

        // Aprobar préstamo (debe pasar automáticamente a Por Desembolsar)
        $this->actingAs($this->jefeCreditos);
        $this->prestamo->aprobar();
        $this->prestamo->refresh();

        // Verificar que pasó a Por Desembolsar (transición automática)
        $this->assertEquals(Prestamo::ESTADO_POR_DESEMBOLSAR, $this->prestamo->estado);
    }

    /** @test */
    public function test_flujo_por_desembolsar_a_desembolsado()
    {
        // Preparar: Llevar a estado Por Desembolsar
        $this->prestamo->update(['estado' => Prestamo::ESTADO_POR_DESEMBOLSAR]);

        // Desembolsar
        $this->actingAs($this->jefeOperaciones);
        $fechaDesembolso = now();
        $this->prestamo->desembolsar($fechaDesembolso);
        $this->prestamo->refresh();

        // Verificar estado
        $this->assertEquals(Prestamo::ESTADO_DESEMBOLSADO, $this->prestamo->estado);
        $this->assertEquals($fechaDesembolso->toDateString(), $this->prestamo->fecha_desembolso->toDateString());
    }

    /** @test */
    public function test_flujo_completo_pendiente_a_desembolsado()
    {
        // Estado inicial
        $this->assertEquals(Prestamo::ESTADO_PENDIENTE, $this->prestamo->estado);

        // Paso 1: Aprobar
        $this->prestamo->aprobar();
        $this->prestamo->refresh();
        $this->assertEquals(Prestamo::ESTADO_POR_DESEMBOLSAR, $this->prestamo->estado);

        // Paso 2: Desembolsar
        $this->prestamo->desembolsar(now());
        $this->prestamo->refresh();
        $this->assertEquals(Prestamo::ESTADO_DESEMBOLSADO, $this->prestamo->estado);
    }

    // ========================================
    // TEST 2: Reducción de Monto
    // ========================================

    /** @test */
    public function test_reducir_monto_actualiza_proporcionalmente()
    {
        // Preparar: Llevar a estado Por Desembolsar
        $this->prestamo->update(['estado' => Prestamo::ESTADO_POR_DESEMBOLSAR]);

        $montoOriginal = $this->prestamo->monto_total;
        $nuevoMonto = 3000; // Reducción de 4000 a 3000 (75%)

        // Reducir monto
        $resultado = $this->prestamo->reducirMonto($nuevoMonto, 'Cliente solicitó menos dinero');
        $this->prestamo->refresh();

        // Verificar monto total actualizado
        $this->assertEquals($nuevoMonto, $this->prestamo->monto_total);

        // Verificar que los préstamos individuales se redujeron proporcionalmente
        $factor = $nuevoMonto / $montoOriginal;
        foreach ($this->prestamo->prestamosIndividuales as $pi) {
            // Los montos deben haberse reducido proporcionalmente
            $this->assertNotEquals($pi->getOriginal('monto_prestamo'), $pi->monto_prestamo);
        }
    }

    /** @test */
    public function test_no_se_puede_reducir_monto_a_mayor()
    {
        $this->prestamo->update(['estado' => Prestamo::ESTADO_POR_DESEMBOLSAR]);

        $montoOriginal = $this->prestamo->monto_total;
        $montoMayor = $montoOriginal + 1000;

        // Intentar aumentar monto (debe fallar)
        $this->expectException(\InvalidArgumentException::class);
        $this->prestamo->reducirMonto($montoMayor, 'Intento de aumento');
    }

    /** @test */
    public function test_no_se_puede_reducir_monto_en_estado_pendiente()
    {
        // El préstamo está en estado Pendiente
        $this->assertEquals(Prestamo::ESTADO_PENDIENTE, $this->prestamo->estado);

        // Verificar que no puede reducir monto
        $this->assertFalse($this->prestamo->puedeReducirMonto());
    }

    // ========================================
    // TEST 3: Cuotas se crean solo en Desembolsado
    // ========================================

    /** @test */
    public function test_cuotas_no_se_crean_en_aprobado()
    {
        $cuotasAntes = CuotasGrupales::where('prestamo_id', $this->prestamo->id)->count();

        // Aprobar préstamo
        $this->prestamo->aprobar();
        $this->prestamo->refresh();

        // Verificar que está en Por Desembolsar
        $this->assertEquals(Prestamo::ESTADO_POR_DESEMBOLSAR, $this->prestamo->estado);

        // Verificar que NO se crearon cuotas
        $cuotasDespues = CuotasGrupales::where('prestamo_id', $this->prestamo->id)->count();
        $this->assertEquals($cuotasAntes, $cuotasDespues);
    }

    /** @test */
    public function test_cuotas_si_se_crean_en_desembolsado()
    {
        // Preparar: Llevar a Por Desembolsar
        $this->prestamo->update(['estado' => Prestamo::ESTADO_POR_DESEMBOLSAR]);
        $cuotasAntes = CuotasGrupales::where('prestamo_id', $this->prestamo->id)->count();

        // Desembolsar
        $this->prestamo->desembolsar(now());
        $this->prestamo->refresh();

        // Verificar estado
        $this->assertEquals(Prestamo::ESTADO_DESEMBOLSADO, $this->prestamo->estado);

        // Verificar que SÍ se crearon cuotas
        $cuotasDespues = CuotasGrupales::where('prestamo_id', $this->prestamo->id)->count();
        $this->assertGreaterThan($cuotasAntes, $cuotasDespues);
    }

    // ========================================
    // TEST 4: Permisos por Rol
    // ========================================

    /** @test */
    public function test_jc_puede_aprobar()
    {
        $this->assertTrue($this->jefeCreditos->can('prestamos.aprobar'));
    }

    /** @test */
    public function test_jc_puede_reducir_monto()
    {
        $this->assertTrue($this->jefeCreditos->can('prestamos.reducir_monto'));
    }

    /** @test */
    public function test_jc_no_puede_desembolsar()
    {
        $this->assertFalse($this->jefeCreditos->can('prestamos.desembolsar'));
    }

    /** @test */
    public function test_jo_puede_desembolsar()
    {
        $this->assertTrue($this->jefeOperaciones->can('prestamos.desembolsar'));
    }

    /** @test */
    public function test_jo_puede_reducir_monto()
    {
        $this->assertTrue($this->jefeOperaciones->can('prestamos.reducir_monto'));
    }

    /** @test */
    public function test_jo_no_puede_aprobar()
    {
        $this->assertFalse($this->jefeOperaciones->can('prestamos.aprobar'));
    }

    /** @test */
    public function test_asesor_solo_puede_crear()
    {
        $this->assertTrue($this->asesor->can('prestamos.crear'));
        $this->assertFalse($this->asesor->can('prestamos.aprobar'));
        $this->assertFalse($this->asesor->can('prestamos.desembolsar'));
        $this->assertFalse($this->asesor->can('prestamos.reducir_monto'));
    }

    // ========================================
    // TEST 5: MovimientoFinanciero
    // ========================================

    /** @test */
    public function test_movimiento_financiero_se_registra_en_desembolso()
    {
        // Preparar
        $this->prestamo->update(['estado' => Prestamo::ESTADO_POR_DESEMBOLSAR]);
        $movimientosAntes = MovimientoFinanciero::where('prestamo_id', $this->prestamo->id)->count();

        // Desembolsar
        $this->prestamo->desembolsar(now());

        // Verificar que se creó el movimiento financiero
        $movimientosDespues = MovimientoFinanciero::where('prestamo_id', $this->prestamo->id)->count();
        $this->assertGreaterThan($movimientosAntes, $movimientosDespues);
    }

    /** @test */
    public function test_movimiento_financiero_tiene_tipo_egreso()
    {
        // Preparar y desembolsar
        $this->prestamo->update(['estado' => Prestamo::ESTADO_POR_DESEMBOLSAR]);
        $this->prestamo->desembolsar(now());

        // Obtener el movimiento
        $movimiento = MovimientoFinanciero::where('prestamo_id', $this->prestamo->id)
            ->where('tipo', 'EGRESO')
            ->first();

        $this->assertNotNull($movimiento);
        $this->assertEquals('EGRESO', $movimiento->tipo);
        $this->assertEquals($this->prestamo->monto_total, $movimiento->monto);
    }

    // ========================================
    // TEST 6: Validaciones de Estado
    // ========================================

    /** @test */
    public function test_puede_aprobar_solo_en_pendiente()
    {
        // Pendiente: puede aprobar
        $this->prestamo->update(['estado' => Prestamo::ESTADO_PENDIENTE]);
        $this->assertTrue($this->prestamo->puedeSerAprobado());

        // Por Desembolsar: no puede aprobar
        $this->prestamo->update(['estado' => Prestamo::ESTADO_POR_DESEMBOLSAR]);
        $this->assertFalse($this->prestamo->puedeSerAprobado());

        // Desembolsado: no puede aprobar
        $this->prestamo->update(['estado' => Prestamo::ESTADO_DESEMBOLSADO]);
        $this->assertFalse($this->prestamo->puedeSerAprobado());
    }

    /** @test */
    public function test_puede_desembolsar_solo_en_por_desembolsar()
    {
        // Pendiente: no puede desembolsar
        $this->prestamo->update(['estado' => Prestamo::ESTADO_PENDIENTE]);
        $this->assertFalse($this->prestamo->puedeDesembolsar());

        // Por Desembolsar: puede desembolsar
        $this->prestamo->update(['estado' => Prestamo::ESTADO_POR_DESEMBOLSAR]);
        $this->assertTrue($this->prestamo->puedeDesembolsar());

        // Desembolsado: no puede volver a desembolsar
        $this->prestamo->update(['estado' => Prestamo::ESTADO_DESEMBOLSADO]);
        $this->assertFalse($this->prestamo->puedeDesembolsar());
    }

    /** @test */
    public function test_puede_reducir_monto_solo_en_por_desembolsar()
    {
        // Pendiente: no puede reducir
        $this->prestamo->update(['estado' => Prestamo::ESTADO_PENDIENTE]);
        $this->assertFalse($this->prestamo->puedeReducirMonto());

        // Por Desembolsar: puede reducir
        $this->prestamo->update(['estado' => Prestamo::ESTADO_POR_DESEMBOLSAR]);
        $this->assertTrue($this->prestamo->puedeReducirMonto());

        // Desembolsado: no puede reducir
        $this->prestamo->update(['estado' => Prestamo::ESTADO_DESEMBOLSADO]);
        $this->assertFalse($this->prestamo->puedeReducirMonto());
    }

    /** @test */
    public function test_puede_rechazar_solo_en_pendiente()
    {
        // Pendiente: puede rechazar
        $this->prestamo->update(['estado' => Prestamo::ESTADO_PENDIENTE]);
        $this->assertTrue($this->prestamo->puedeSerRechazado());

        // Por Desembolsar: no puede rechazar
        $this->prestamo->update(['estado' => Prestamo::ESTADO_POR_DESEMBOLSAR]);
        $this->assertFalse($this->prestamo->puedeSerRechazado());
    }
}
