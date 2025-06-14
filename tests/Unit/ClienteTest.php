<?php

use App\Models\Cliente;
use App\Models\Persona;
use App\Models\Asesor;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

describe('Modelo Cliente', function () {
    it('puede crear un cliente y su relación con persona y asesor', function () {
        $cliente = Cliente::factory()->create();
        expect($cliente)->toBeInstanceOf(Cliente::class);
        expect($cliente->persona)->toBeInstanceOf(Persona::class);
        expect($cliente->asesor)->toBeInstanceOf(Asesor::class);
    });

    it('puede actualizar el estado del cliente', function () {
        $cliente = Cliente::factory()->create();
        $cliente->estado_cliente = 'inactivo';
        $cliente->save();
        $cliente->refresh();
        expect($cliente->estado_cliente)->toBe('inactivo');
    });

    it('puede eliminar un cliente', function () {
        $cliente = Cliente::factory()->create();
        $id = $cliente->id;
        $cliente->delete();
        expect(Cliente::find($id))->toBeNull();
    });
});
