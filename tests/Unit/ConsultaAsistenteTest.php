<?php

use App\Models\ConsultaAsistente;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

describe('Modelo ConsultaAsistente', function () {
    it('puede crear una consulta y asociarla a un usuario', function () {
        $user = User::factory()->create();
        $consulta = ConsultaAsistente::factory()->create([
            'user_id' => $user->id,
        ]);
        expect($consulta)->toBeInstanceOf(ConsultaAsistente::class);
        expect($consulta->user)->toBeInstanceOf(User::class);
    });

    it('puede actualizar la respuesta de la consulta', function () {
        $consulta = ConsultaAsistente::factory()->create([
            'respuesta' => null,
        ]);
        $consulta->respuesta = 'Respuesta automática';
        $consulta->save();
        $consulta->refresh();
        expect($consulta->respuesta)->toBe('Respuesta automática');
    });
});
