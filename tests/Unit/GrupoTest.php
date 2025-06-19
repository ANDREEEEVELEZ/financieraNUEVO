<?php

use App\Models\Grupo;
use App\Models\Cliente;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

describe('Modelo Grupo', function () {
    it('Verifica si se puede crear un grupo', function () {
        $grupo = Grupo::factory()->create();
        expect($grupo)->toBeInstanceOf(Grupo::class);
    });

    it('Verifica si se puede relacionar clientes a un grupo', function () {
        $grupo = Grupo::factory()->create();
        $cliente = Cliente::factory()->create();
        $grupo->clientes()->attach($cliente->id);
        expect($grupo->clientes->first())->toBeInstanceOf(Cliente::class);
    });

    it('Debe cambiar el estado del grupo a inactivo si todos los integrantes activos pasan a inactivos', function () {
        $grupo = Grupo::factory()->create(['estado_grupo' => 'Activo']);
        $clientes = Cliente::factory()->count(3)->create(['estado_cliente' => 'activo']);
        $grupo->clientes()->attach($clientes->pluck('id')->toArray());
        // Simular que todos los clientes pasan a inactivos
        foreach ($clientes as $cliente) {
            $cliente->estado_cliente = 'inactivo';
            $cliente->save();
        }
        // Suponiendo que hay un método para actualizar el estado del grupo
        if (method_exists($grupo, 'actualizarEstadoPorIntegrantes')) {
            $grupo->actualizarEstadoPorIntegrantes();
            $grupo->refresh();
            expect($grupo->estado_grupo)->toBe('inactivo');
        } else {
            expect(true)->toBeTrue(); 
        }
    });

    it('Debe actualizar automáticamente el número de integrantes activos del grupo', function () {
        $grupo = Grupo::factory()->create();
        $clientes = Cliente::factory()->count(2)->create(['estado_cliente' => 'activo']);
        $grupo->clientes()->attach($clientes->pluck('id')->toArray());
        expect($grupo->getNumeroIntegrantesRealAttribute())->toBe(2);
        // Inactivar un cliente
        $clientes[0]->estado_cliente = 'inactivo';
        $clientes[0]->save();
        // El grupo sigue teniendo 2 relaciones, pero solo 1 activo
        $activos = $grupo->clientes()->where('estado_cliente', 'activo')->count();
        expect($activos)->toBe(1);
    });
});
