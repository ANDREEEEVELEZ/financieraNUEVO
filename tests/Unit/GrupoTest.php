<?php

use App\Models\Grupo;
use App\Models\Cliente;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

describe('Modelo Grupo', function () {
    it('puede crear un grupo', function () {
        $grupo = Grupo::factory()->create();
        expect($grupo)->toBeInstanceOf(Grupo::class);
    });

    it('puede relacionar clientes a un grupo', function () {
        $grupo = Grupo::factory()->create();
        $cliente = Cliente::factory()->create();
        $grupo->clientes()->attach($cliente->id);
        expect($grupo->clientes->first())->toBeInstanceOf(Cliente::class);
    });
});
