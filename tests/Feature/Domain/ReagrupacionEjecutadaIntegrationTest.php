<?php

use App\Domain\Prestamos\ReagrupacionParcialService;
use App\Events\Domain\ReagrupacionEjecutada;
use App\Models\Asesor;
use App\Models\Cliente;
use App\Models\Grupo;
use App\Models\Prestamo;
use App\Models\Reagrupacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

/**
 * Task 1.9 — Integration: ReagrupacionParcialService dispatches ReagrupacionEjecutada (SR-3)
 *
 * Setup helper: build a group with 2 clients (so we can transfer 1 and keep 1).
 * We use a group with NO active prestamos so transferirClienteAGrupo does not throw.
 */
function reagrupacionMakeSetup(): array
{
    $user   = User::factory()->create(['active' => true]);
    $asesor = Asesor::factory()->create(['user_id' => $user->id]);
    Auth::login($user);

    // 5 members: transfer 1, keep 4 — satisfies min-4 rule on origen
    // The new grupo starts empty so puedeAgregarIntegrante() is true
    $grupoOrigen = Grupo::factory()->create([
        'asesor_id'          => $asesor->id,
        'estado_grupo'       => 'Activo',
        'numero_integrantes' => 5,
    ]);

    // Set to FINALIZADO so tienePrestamosActivos() returns false and transfer is allowed
    $prestamo = Prestamo::factory()->create([
        'grupo_id' => $grupoOrigen->id,
        'estado'   => Prestamo::ESTADO_FINALIZADO,
    ]);

    $clientes = Cliente::factory()->count(5)->create();

    // Attach all 5 clients as active members
    $rows = $clientes->map(fn ($c) => [
        'grupo_id'             => $grupoOrigen->id,
        'cliente_id'           => $c->id,
        'fecha_ingreso'        => now()->toDateString(),
        'fecha_salida'         => null,
        'rol'                  => 'Integrante',
        'estado_grupo_cliente' => 'Activo',
        'created_at'           => now(),
        'updated_at'           => now(),
    ])->toArray();

    DB::table('grupo_cliente')->insert($rows);

    $cliente1 = $clientes->first();   // will be transferred
    $cliente2 = $clientes->get(1);    // represents "retained" set

    return [$prestamo, $grupoOrigen, $cliente1, $cliente2];
}

it('ReagrupacionParcialService dispatches ReagrupacionEjecutada event after successful reagrupacion', function () {
    Event::fake([ReagrupacionEjecutada::class]);

    [$prestamo, $grupoOrigen, $cliente1, $cliente2] = reagrupacionMakeSetup();

    $service = new ReagrupacionParcialService();
    $service->reagrupar(
        $prestamo,
        [$cliente1->id],
        'retanqueo',
        'Test obs'
    );

    Event::assertDispatched(ReagrupacionEjecutada::class, function ($event) {
        return $event->reagrupacion instanceof Reagrupacion;
    });
});

it('ReagrupacionParcialService populates reagrupacion_cliente pivot table with trasladado and retenido types', function () {
    [$prestamo, $grupoOrigen, $cliente1, $cliente2] = reagrupacionMakeSetup();

    $service = new ReagrupacionParcialService();
    $reagrupacion = $service->reagrupar(
        $prestamo,
        [$cliente1->id],
        'retanqueo',
        'Test obs'
    );

    $trasladados = $reagrupacion->clientesTrasladados()->get();
    expect($trasladados)->toHaveCount(1);
    expect($trasladados->first()->id)->toBe($cliente1->id);

    $retenidos = $reagrupacion->clientesRetenidos()->get();
    expect($retenidos)->toHaveCount(4);
});

it('ReagrupacionParcialService creates an audit_log row with reagrupacion.ejecutada', function () {
    $user   = User::factory()->create(['active' => true]);
    Auth::login($user);

    [$prestamo, $grupoOrigen, $cliente1, $cliente2] = reagrupacionMakeSetup();

    $service = new ReagrupacionParcialService();
    $service->reagrupar(
        $prestamo,
        [$cliente1->id],
        'retanqueo',
        'Audit obs'
    );

    $this->assertDatabaseHas('audit_logs', ['accion' => 'reagrupacion.ejecutada']);
});

it('ReagrupacionParcialService audit row includes required audit context fields', function () {
    [$prestamo, $grupoOrigen, $cliente1, $cliente2] = reagrupacionMakeSetup();

    $service = new ReagrupacionParcialService();
    $result = $service->reagrupar(
        $prestamo,
        [$cliente1->id],
        'retanqueo',
        'Parity check'
    );

    // Schema parity: audit_log row must have the same columns as other domain events
    $row = DB::table('audit_logs')
        ->where('accion', 'reagrupacion.ejecutada')
        ->first();

    expect($row)->not->toBeNull();
    expect($row->accion)->toBe('reagrupacion.ejecutada');
    // Schema parity: AuditService writes auditable_type + auditable_id (polymorphic)
    expect($row->auditable_type)->toBe(\App\Models\Reagrupacion::class);
    expect($row->auditable_id)->not->toBeNull();
});

/**
 * Task 1.12 — Failed reagrupacion: no event dispatched (SR-3 scenario 2)
 */
it('ReagrupacionParcialService does not dispatch ReagrupacionEjecutada on validation failure', function () {
    Event::fake([ReagrupacionEjecutada::class]);

    $user   = User::factory()->create(['active' => true]);
    $asesor = Asesor::factory()->create(['user_id' => $user->id]);
    Auth::login($user);

    $grupoOrigen = Grupo::factory()->create(['asesor_id' => $asesor->id]);
    $prestamo    = Prestamo::factory()->create([
        'grupo_id' => $grupoOrigen->id,
        'estado'   => Prestamo::ESTADO_FINALIZADO,
    ]);

    $service = new ReagrupacionParcialService();

    // Empty clientes array triggers validation exception
    try {
        $service->reagrupar($prestamo, [], 'retanqueo', 'Fallo');
    } catch (\Exception) {
        // Expected
    }

    Event::assertNotDispatched(ReagrupacionEjecutada::class);
});

it('ReagrupacionParcialService failed reagrupacion writes no reagrupacion.ejecutada audit row', function () {
    $user   = User::factory()->create(['active' => true]);
    $asesor = Asesor::factory()->create(['user_id' => $user->id]);
    Auth::login($user);

    $grupoOrigen = Grupo::factory()->create(['asesor_id' => $asesor->id]);
    $prestamo    = Prestamo::factory()->create([
        'grupo_id' => $grupoOrigen->id,
        'estado'   => Prestamo::ESTADO_FINALIZADO,
    ]);

    $service = new ReagrupacionParcialService();

    try {
        $service->reagrupar($prestamo, [], 'retanqueo', 'Fallo');
    } catch (\Exception) {
        // Expected
    }

    $this->assertDatabaseMissing('audit_logs', ['accion' => 'reagrupacion.ejecutada']);
});
