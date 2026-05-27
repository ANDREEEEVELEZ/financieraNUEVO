<?php

use App\Events\Domain\PrestamoDesembolsado;
use App\Models\Asesor;
use App\Models\Grupo;
use App\Models\Prestamo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

/**
 * PR3-T3: PrestamoDesembolsado domain event dispatched when desembolsar() is called.
 *
 * RED: must fail until PrestamoDesembolsado event exists and is dispatched in PrestamoController.
 */
it('dispatches PrestamoDesembolsado event when JO calls desembolsar', function () {
    Event::fake([PrestamoDesembolsado::class]);

    $joUser = User::factory()->create(['active' => true]);
    $joUser->assignRole('Jefe de operaciones');
    $token = $joUser->createToken('test')->plainTextToken;

    [$asesorUser, $asesor] = makeAsesorForDesembolsoTest();

    $grupo    = Grupo::factory()->create(['asesor_id' => $asesor->id]);
    $prestamo = Prestamo::factory()->create([
        'grupo_id' => $grupo->id,
        'estado'   => Prestamo::ESTADO_FIRMADO,
    ]);

    $this->withToken($token)
         ->patchJson("/api/prestamos/{$prestamo->id}/desembolsar", [
             'titular_cuenta' => 'Test Titular',
             'numero_cuenta'  => '001-123456',
         ])
         ->assertOk();

    Event::assertDispatched(PrestamoDesembolsado::class);
});

function makeAsesorForDesembolsoTest(): array
{
    $user   = User::factory()->create(['active' => true]);
    $user->assignRole('Asesor');
    $asesor = Asesor::factory()->create(['user_id' => $user->id]);
    return [$user, $asesor];
}
