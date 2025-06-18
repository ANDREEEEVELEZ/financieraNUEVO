<?php

use App\Models\ConsultaAsistente;
use App\Models\User;
use App\Models\Asesor;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

describe('Modelo ConsultaAsistente', function () {

    it('puede crear una consulta y asociarla a un usuario', function ()
    {
        $user = User::factory()->create();
        $consulta = ConsultaAsistente::factory()->create
        ([
            'user_id' => $user->id,
        ]);

        expect($consulta)->toBeInstanceOf(ConsultaAsistente::class);
        expect($consulta->user)->toBeInstanceOf(User::class);
    });

    it('puede actualizar la respuesta de la consulta', function ()
    {

        $consulta = ConsultaAsistente::factory()->conRespuestaVacia()->create();

        $consulta->respuesta = 'Respuesta automática';
        $consulta->save();
        $consulta->refresh();

        expect($consulta->respuesta)->toBe('Respuesta automática');
    });

    it('solo muestra las consultas del usuario autenticado', function ()
     {
        $asesorUser = User::factory()->create();
        $otroUser = User::factory()->create();
        ConsultaAsistente::factory()->create(['user_id' => $asesorUser->id]);
        ConsultaAsistente::factory()->create(['user_id' => $otroUser->id]);
        $this->actingAs($asesorUser);
        $consultas = ConsultaAsistente::where('user_id', $asesorUser->id)->get();
        expect($consultas)->toHaveCount(1);
    });

});
