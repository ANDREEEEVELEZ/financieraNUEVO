<?php

use App\Events\Domain\PrestamoAprobado;
use App\Events\Domain\PrestamoRechazado;
use App\Models\Asesor;
use App\Models\Grupo;
use App\Models\Prestamo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

/**
 * Task 1.8 — Integration: rechazar() dispatches PrestamoRechazado and creates audit row (SR-2)
 */
it('rechazar dispatches PrestamoRechazado event after commit', function () {
    Event::fake([PrestamoRechazado::class]);

    $jcUser = User::factory()->create(['active' => true]);
    $jcUser->assignRole('Jefe de creditos');
    $token = $jcUser->createToken('test')->plainTextToken;

    $asesor   = Asesor::factory()->create();
    $grupo    = Grupo::factory()->create(['asesor_id' => $asesor->id]);
    $prestamo = Prestamo::factory()->create([
        'grupo_id' => $grupo->id,
        'estado'   => Prestamo::ESTADO_PENDIENTE,
    ]);

    $this->withToken($token)->patchJson("/api/prestamos/{$prestamo->id}/rechazar", [
        'motivo' => 'Documentación incompleta',
    ])->assertOk();

    Event::assertDispatched(PrestamoRechazado::class, function ($event) use ($prestamo) {
        return $event->prestamo->id === $prestamo->id
            && $event->motivo === 'Documentación incompleta';
    });
});

it('rechazar creates exactly one audit row with action prestamo.rechazado', function () {
    $jcUser = User::factory()->create(['active' => true]);
    $jcUser->assignRole('Jefe de creditos');
    $token = $jcUser->createToken('test')->plainTextToken;

    $asesor   = Asesor::factory()->create();
    $grupo    = Grupo::factory()->create(['asesor_id' => $asesor->id]);
    $prestamo = Prestamo::factory()->create([
        'grupo_id' => $grupo->id,
        'estado'   => Prestamo::ESTADO_PENDIENTE,
    ]);

    $this->withToken($token)->patchJson("/api/prestamos/{$prestamo->id}/rechazar", [
        'motivo' => 'Test motivo',
    ])->assertOk();

    $this->assertDatabaseHas('audit_logs', ['accion' => 'prestamo.rechazado']);

    $count = DB::table('audit_logs')->where('accion', 'prestamo.rechazado')->count();
    expect($count)->toBe(1);
});

/**
 * Task 1.8 scenario 3: approval and rejection events are independent (SR-2)
 */
it('rejecting a prestamo does not affect an existing prestamo.aprobado audit row', function () {
    // First approve a different prestamo (direct audit, so we can verify independence)
    $jcUser = User::factory()->create(['active' => true]);
    $jcUser->assignRole('Jefe de creditos');
    $token = $jcUser->createToken('test')->plainTextToken;

    $asesor = Asesor::factory()->create();
    $grupo  = Grupo::factory()->create(['asesor_id' => $asesor->id]);

    $prestamoA = Prestamo::factory()->create(['grupo_id' => $grupo->id, 'estado' => Prestamo::ESTADO_PENDIENTE]);
    $prestamoB = Prestamo::factory()->create(['grupo_id' => $grupo->id, 'estado' => Prestamo::ESTADO_PENDIENTE]);

    // Approve A
    $this->withToken($token)->patchJson("/api/prestamos/{$prestamoA->id}/aprobar")->assertOk();

    // Reject B
    $this->withToken($token)->patchJson("/api/prestamos/{$prestamoB->id}/rechazar", [
        'motivo' => 'Independencia de eventos',
    ])->assertOk();

    // Both audit rows must exist independently
    $this->assertDatabaseHas('audit_logs', ['accion' => 'prestamo.aprobado']);
    $this->assertDatabaseHas('audit_logs', ['accion' => 'prestamo.rechazado']);

    $approvedCount = DB::table('audit_logs')->where('accion', 'prestamo.aprobado')->count();
    $rejectedCount = DB::table('audit_logs')->where('accion', 'prestamo.rechazado')->count();

    expect($approvedCount)->toBe(1);
    expect($rejectedCount)->toBe(1);
});

/**
 * Task 1.11 — FSM failure: no event dispatched on non-rejectable state (SR-2 scenario 2)
 */
it('rechazar on already-Rechazado prestamo dispatches no PrestamoRechazado event', function () {
    Event::fake([PrestamoRechazado::class]);

    $jcUser = User::factory()->create(['active' => true]);
    $jcUser->assignRole('Jefe de creditos');
    $token = $jcUser->createToken('test')->plainTextToken;

    $asesor   = Asesor::factory()->create();
    $grupo    = Grupo::factory()->create(['asesor_id' => $asesor->id]);
    $prestamo = Prestamo::factory()->create([
        'grupo_id' => $grupo->id,
        'estado'   => Prestamo::ESTADO_RECHAZADO,
    ]);

    $this->withToken($token)->patchJson("/api/prestamos/{$prestamo->id}/rechazar", [
        'motivo' => 'Intento inválido',
    ])->assertStatus(422);

    Event::assertNotDispatched(PrestamoRechazado::class);
});

it('rechazar on already-Rechazado prestamo writes no prestamo.rechazado audit row', function () {
    $jcUser = User::factory()->create(['active' => true]);
    $jcUser->assignRole('Jefe de creditos');
    $token = $jcUser->createToken('test')->plainTextToken;

    $asesor   = Asesor::factory()->create();
    $grupo    = Grupo::factory()->create(['asesor_id' => $asesor->id]);
    $prestamo = Prestamo::factory()->create([
        'grupo_id' => $grupo->id,
        'estado'   => Prestamo::ESTADO_RECHAZADO,
    ]);

    $this->withToken($token)->patchJson("/api/prestamos/{$prestamo->id}/rechazar", [
        'motivo' => 'Intento inválido',
    ])->assertStatus(422);

    $this->assertDatabaseMissing('audit_logs', ['accion' => 'prestamo.rechazado']);
});
