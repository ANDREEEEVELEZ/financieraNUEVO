<?php

use App\Models\Retanqueo;
use App\Models\Prestamo;
use App\Models\Grupo;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

describe('Modelo Retanqueo', function () {
    it('puede crear un retanqueo y asociarlo a un préstamo y grupo', function () {
        $prestamo = Prestamo::factory()->create();
        $grupo = Grupo::factory()->create();
        $retanqueo = Retanqueo::factory()->create([
            'prestamos_id' => $prestamo->id,
            'grupo_id' => $grupo->id,
        ]);
        expect($retanqueo->prestamo)->toBeInstanceOf(Prestamo::class);
        expect($retanqueo->grupo)->toBeInstanceOf(Grupo::class);
    });
});
