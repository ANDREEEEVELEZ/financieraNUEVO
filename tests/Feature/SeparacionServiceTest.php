<?php

declare(strict_types=1);

use App\Domain\Grupos\MorosoSeparationService;
use App\Models\Asesor;
use App\Models\Cliente;
use App\Models\CuotaIndividual;
use App\Models\Grupo;
use App\Models\Persona;
use App\Models\Prestamo;
use App\Models\ProductoFinanciero;
use App\Models\SeparacionCliente;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    // ── Roles ──
    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'Jefe de operaciones', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'Jefe de creditos', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'Asesor', 'guard_name' => 'web']);

    // ── Permisos necesarios ──
    foreach (['prestamos.separar_cliente', 'prestamos.ver_todos'] as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
    }

    // ── Producto financiero ──
    $this->producto = ProductoFinanciero::create([
        'codigo'                    => 'CI-EMPRENDEDOR',
        'nombre'                    => 'Crédito Individual Emprendedor',
        'tipo'                      => 'individual',
        'tasa_interes'              => 0.12,
        'tasa_mora'                 => 0.06,
        'monto_minimo'              => 100,
        'monto_maximo'              => 10000,
        'plazo_minimo_meses'        => 1,
        'plazo_maximo_meses'        => 12,
        'penalizacion_separacion'   => 0.0500,
        'activo'                    => true,
    ]);

    // ── Usuarios ──
    $this->ejecutor = User::factory()->create();
    $this->ejecutor->assignRole('Jefe de creditos');
    $this->ejecutor->givePermissionTo('prestamos.separar_cliente');

    // ── Asesor ──
    $persona = Persona::factory()->create();
    $this->asesor = Asesor::factory()->create(['persona_id' => $persona->id]);

    // ── Grupo con 3 clientes ──
    $this->grupo = Grupo::factory()->create([
        'asesor_id'          => $this->asesor->id,
        'numero_integrantes' => 3,
    ]);

    $this->clientes = [];
    foreach (range(1, 3) as $i) {
        $p = Persona::factory()->create();
        $c = Cliente::factory()->create(['persona_id' => $p->id]);
        $this->grupo->clientes()->attach($c->id, [
            'fecha_ingreso'        => now()->toDateString(),
            'estado_grupo_cliente' => 'activo',
        ]);
        $this->clientes[] = $c;
    }

    // ── Préstamo grupal activo ──
    $this->prestamo = Prestamo::factory()->create([
        'grupo_id'             => $this->grupo->id,
        'producto_id'          => $this->producto->id,
        'estado'               => Prestamo::ESTADO_ACTIVO,
        'tipo'                 => 'grupal',
        'tasa_interes'         => 17,
        'monto_prestado_total' => 1500.00,
        'cantidad_cuotas'      => 4,
        'frecuencia'           => 'semanal',
    ]);

    // ── CuotasIndividuales pendientes para cada cliente ──
    foreach ($this->clientes as $cliente) {
        CuotaIndividual::create([
            'prestamo_id'            => $this->prestamo->id,
            'cliente_id'             => $cliente->id,
            'numero_cuota'           => 1,
            'monto_capital_original' => 125.00,
            'monto_interes_original' => 15.00,
            'saldo_capital'          => 125.00,
            'saldo_interes'          => 15.00,
            'fecha_vencimiento'      => now()->addDays(7),
            'estado'                 => 'pendiente',
        ]);
    }

    $this->service = app(MorosoSeparationService::class);
});

// ─────────────────────────────────────────────────────────────────────────────
// Tests
// ─────────────────────────────────────────────────────────────────────────────

test('separarClienteMoroso crea un nuevo Prestamo individual con estado Separado', function (): void {
    $resultado = $this->service->separarClienteMoroso(
        origen:       $this->prestamo,
        cliente:      $this->clientes[0],
        ejecutadoPor: $this->ejecutor,
        motivo:       'Incumplimiento reiterado de pagos',
    );

    expect($resultado)->toBeInstanceOf(SeparacionCliente::class)
        ->and($resultado->estado)->toBe('ejecutada')
        ->and($resultado->prestamo_nuevo_id)->not->toBeNull();

    $nuevoPrestamo = Prestamo::find($resultado->prestamo_nuevo_id);
    expect($nuevoPrestamo)->not->toBeNull()
        ->and($nuevoPrestamo->estado)->toBe(Prestamo::ESTADO_SEPARADO)
        ->and($nuevoPrestamo->tipo)->toBe('individual');
});

test('separarClienteMoroso reasigna CuotasIndividuales al nuevo préstamo', function (): void {
    $this->service->separarClienteMoroso(
        origen:       $this->prestamo,
        cliente:      $this->clientes[0],
        ejecutadoPor: $this->ejecutor,
        motivo:       'Separación por mora',
    );

    $cuotasReasignadas = CuotaIndividual::where('cliente_id', $this->clientes[0]->id)
        ->where('prestamo_id', '!=', $this->prestamo->id)
        ->get();

    expect($cuotasReasignadas)->not->toBeEmpty()
        ->and($cuotasReasignadas->first()->estado)->toBe('pendiente');
});

test('separarClienteMoroso registra deuda correcta en separaciones_clientes', function (): void {
    $resultado = $this->service->separarClienteMoroso(
        origen:       $this->prestamo,
        cliente:      $this->clientes[0],
        ejecutadoPor: $this->ejecutor,
        motivo:       'Morosidad',
    );

    expect((float) $resultado->deuda_capital)->toBe(125.00)
        ->and((float) $resultado->deuda_interes)->toBe(15.00)
        ->and((float) $resultado->penalizacion_grupo)->toBeGreaterThan(0);
});

test('separarClienteMoroso lanza RuntimeException si no hay cuotas pendientes', function (): void {
    // Marcar todas las cuotas como pagadas
    CuotaIndividual::where('cliente_id', $this->clientes[0]->id)
        ->update(['estado' => 'pagada']);

    $this->service->separarClienteMoroso(
        origen:       $this->prestamo,
        cliente:      $this->clientes[0],
        ejecutadoPor: $this->ejecutor,
        motivo:       'Separación',
    );
})->throws(RuntimeException::class);

test('separarClienteMoroso lanza RuntimeException si el cliente ya fue separado', function (): void {
    $this->service->separarClienteMoroso(
        origen:       $this->prestamo,
        cliente:      $this->clientes[0],
        ejecutadoPor: $this->ejecutor,
        motivo:       'Primera separación',
    );

    // Segundo intento debe fallar
    $this->service->separarClienteMoroso(
        origen:       $this->prestamo,
        cliente:      $this->clientes[0],
        ejecutadoPor: $this->ejecutor,
        motivo:       'Segunda separación',
    );
})->throws(RuntimeException::class, 'ya fue separado');

test('separarClienteMoroso desvincula al cliente del grupo', function (): void {
    $this->service->separarClienteMoroso(
        origen:       $this->prestamo,
        cliente:      $this->clientes[0],
        ejecutadoPor: $this->ejecutor,
        motivo:       'Separación',
    );

    $pivot = $this->grupo->todosLosIntegrantes()
        ->where('clientes.id', $this->clientes[0]->id)
        ->first();

    expect($pivot)->not->toBeNull();
    expect($pivot->pivot->estado_grupo_cliente)->toBe('separado');
    expect($pivot->pivot->fecha_salida)->not->toBeNull();
});

test('separarClienteMoroso decrementa numero_integrantes del grupo', function (): void {
    $this->service->separarClienteMoroso(
        origen:       $this->prestamo,
        cliente:      $this->clientes[0],
        ejecutadoPor: $this->ejecutor,
        motivo:       'Separación',
    );

    $this->grupo->refresh();
    expect((int) $this->grupo->numero_integrantes)->toBe(2);
});

test('separarClienteMoroso lanza RuntimeException si motivo está vacío', function (): void {
    $this->service->separarClienteMoroso(
        origen:       $this->prestamo,
        cliente:      $this->clientes[0],
        ejecutadoPor: $this->ejecutor,
        motivo:       '',
    );
})->throws(RuntimeException::class);

test('separarClienteMoroso es transaccional — rollback completo si falla después de crear Prestamo', function (): void {
    // Simular fallo forzando una excepción después de crear el nuevo Prestamo
    // usando mock parcial — se lanza RuntimeException antes de commit
    $mockService = Mockery::mock(MorosoSeparationService::class)
        ->makePartial()
        ->shouldAllowMockingProtectedMethods();

    $mockService->shouldReceive('crearPrestamoSeparado')
        ->once()
        ->andThrow(new RuntimeException('Fallo simulado después de crear préstamo'));

    $this->instance(MorosoSeparationService::class, $mockService);

    // El préstamo original y las cuotas deben quedar intactos
    $prestamoId = $this->prestamo->id;
    $cuotasCount = CuotaIndividual::where('prestamo_id', $prestamoId)->count();

    try {
        $mockService->separarClienteMoroso(
            origen:       $this->prestamo,
            cliente:      $this->clientes[0],
            ejecutadoPor: $this->ejecutor,
            motivo:       'Separación',
        );
    } catch (RuntimeException) {
        // Esperado
    }

    expect(CuotaIndividual::where('prestamo_id', $prestamoId)->count())->toBe($cuotasCount);
    expect(SeparacionCliente::count())->toBe(0);
});
