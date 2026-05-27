<?php

use App\Events\Domain\PrestamoFirmado;
use App\Models\Asesor;
use App\Models\Grupo;
use App\Models\Prestamo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

/**
 * PR3-T4: PrestamoFirmado domain event dispatched when firmar() is called.
 * Also asserts one audit_logs row with accion='prestamo.firmado' is created.
 *
 * RED: must fail until PrestamoFirmado event exists, is dispatched in PrestamoController,
 * and AuditListener creates the audit_log entry.
 */
it('dispatches PrestamoFirmado event and creates audit log when Asesor calls firmar', function () {
    Event::fake([PrestamoFirmado::class]);

    $asesorUser = User::factory()->create(['active' => true]);
    $asesorUser->assignRole('Asesor');
    $asesor = Asesor::factory()->create(['user_id' => $asesorUser->id]);
    $token  = $asesorUser->createToken('test')->plainTextToken;

    $grupo    = Grupo::factory()->create(['asesor_id' => $asesor->id]);
    $prestamo = Prestamo::factory()->create([
        'grupo_id' => $grupo->id,
        'estado'   => Prestamo::ESTADO_APROBADO,
    ]);

    $this->withToken($token)
         ->patchJson("/api/prestamos/{$prestamo->id}/firmar")
         ->assertOk();

    Event::assertDispatched(PrestamoFirmado::class);
});

it('AuditListener creates audit_log row with accion prestamo.firmado when PrestamoFirmado is dispatched', function () {
    // Do NOT fake the event — let the real listener handle it
    $asesorUser = User::factory()->create(['active' => true]);
    $asesorUser->assignRole('Asesor');
    $asesor = Asesor::factory()->create(['user_id' => $asesorUser->id]);
    $token  = $asesorUser->createToken('test')->plainTextToken;

    $grupo    = Grupo::factory()->create(['asesor_id' => $asesor->id]);
    $prestamo = Prestamo::factory()->create([
        'grupo_id' => $grupo->id,
        'estado'   => Prestamo::ESTADO_APROBADO,
    ]);

    $this->withToken($token)
         ->patchJson("/api/prestamos/{$prestamo->id}/firmar")
         ->assertOk();

    $this->assertDatabaseHas('audit_logs', [
        'accion' => 'prestamo.firmado',
    ]);
});
