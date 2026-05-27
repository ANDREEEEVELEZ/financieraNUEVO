<?php

use App\Events\Domain\PrestamoAprobado;
use App\Models\Asesor;
use App\Models\Grupo;
use App\Models\Prestamo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

/**
 * Task 1.7 — Integration: aprobar() dispatches PrestamoAprobado and creates audit row (SR-1)
 */
it('aprobar dispatches PrestamoAprobado event after commit', function () {
    Event::fake([PrestamoAprobado::class]);

    $jcUser = User::factory()->create(['active' => true]);
    $jcUser->assignRole('Jefe de creditos');
    $token = $jcUser->createToken('test')->plainTextToken;

    $asesor   = Asesor::factory()->create();
    $grupo    = Grupo::factory()->create(['asesor_id' => $asesor->id]);
    $prestamo = Prestamo::factory()->create([
        'grupo_id' => $grupo->id,
        'estado'   => Prestamo::ESTADO_PENDIENTE,
    ]);

    $this->withToken($token)->patchJson("/api/prestamos/{$prestamo->id}/aprobar")
        ->assertOk();

    Event::assertDispatched(PrestamoAprobado::class, function ($event) use ($prestamo) {
        return $event->prestamo->id === $prestamo->id;
    });
});

it('aprobar creates exactly one audit row with action prestamo.aprobado', function () {
    $jcUser = User::factory()->create(['active' => true]);
    $jcUser->assignRole('Jefe de creditos');
    $token = $jcUser->createToken('test')->plainTextToken;

    $asesor   = Asesor::factory()->create();
    $grupo    = Grupo::factory()->create(['asesor_id' => $asesor->id]);
    $prestamo = Prestamo::factory()->create([
        'grupo_id' => $grupo->id,
        'estado'   => Prestamo::ESTADO_PENDIENTE,
    ]);

    $this->withToken($token)->patchJson("/api/prestamos/{$prestamo->id}/aprobar")
        ->assertOk();

    $this->assertDatabaseHas('audit_logs', ['accion' => 'prestamo.aprobado']);

    $count = \Illuminate\Support\Facades\DB::table('audit_logs')
        ->where('accion', 'prestamo.aprobado')
        ->count();
    expect($count)->toBe(1);
});

/**
 * Task 1.10 — FSM failure: no event dispatched on non-approvable state (SR-1 scenario 2)
 */
it('aprobar on already-Aprobado prestamo dispatches no PrestamoAprobado event', function () {
    Event::fake([PrestamoAprobado::class]);

    $jcUser = User::factory()->create(['active' => true]);
    $jcUser->assignRole('Jefe de creditos');
    $token = $jcUser->createToken('test')->plainTextToken;

    $asesor   = Asesor::factory()->create();
    $grupo    = Grupo::factory()->create(['asesor_id' => $asesor->id]);
    $prestamo = Prestamo::factory()->create([
        'grupo_id' => $grupo->id,
        'estado'   => Prestamo::ESTADO_APROBADO,
    ]);

    $this->withToken($token)->patchJson("/api/prestamos/{$prestamo->id}/aprobar")
        ->assertStatus(422);

    Event::assertNotDispatched(PrestamoAprobado::class);
});

it('aprobar on already-Aprobado prestamo writes no prestamo.aprobado audit row', function () {
    $jcUser = User::factory()->create(['active' => true]);
    $jcUser->assignRole('Jefe de creditos');
    $token = $jcUser->createToken('test')->plainTextToken;

    $asesor   = Asesor::factory()->create();
    $grupo    = Grupo::factory()->create(['asesor_id' => $asesor->id]);
    $prestamo = Prestamo::factory()->create([
        'grupo_id' => $grupo->id,
        'estado'   => Prestamo::ESTADO_APROBADO,
    ]);

    $this->withToken($token)->patchJson("/api/prestamos/{$prestamo->id}/aprobar")
        ->assertStatus(422);

    $this->assertDatabaseMissing('audit_logs', ['accion' => 'prestamo.aprobado']);
});
