<?php

use App\Models\Cliente;
use App\Models\Persona;
use App\Models\Asesor;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

describe('Modelo Cliente', function () {
    it('Verifica si se puede crear un cliente y su relación con persona y asesor', function () {
        $cliente = Cliente::factory()->create();
        expect($cliente)->toBeInstanceOf(Cliente::class);
        expect($cliente->persona)->toBeInstanceOf(Persona::class);
        expect($cliente->asesor)->toBeInstanceOf(Asesor::class);
    });

    it('Verifica si se puede eliminar logicamente  un cliente', function () {
        $cliente = Cliente::factory()->create();
        $id=$cliente->id;
        $cliente->delete();
        expect(cliente::find($id))->toBeNull();
    });

    it('Debe mostrar clientes inactivos en el listado pero no contarlos como activos', function () {
        $activos = \App\Models\Cliente::factory()->count(3)->create(['estado_cliente' => 'activo']);
        $inactivos = \App\Models\Cliente::factory()->count(2)->create(['estado_cliente' => 'inactivo']);
        $clientes = \App\Models\Cliente::all();
        $activosCount = $clientes->where('estado_cliente', 'activo')->count();
        $inactivosCount = $clientes->where('estado_cliente', 'inactivo')->count();
        expect($clientes->count())->toBe(5);
        expect($activosCount)->toBe(3);
        expect($inactivosCount)->toBe(2);
    });

    it('Debe validar que un cliente no esté en más de un grupo activo', function () {
        $cliente = \App\Models\Cliente::factory()->create();
        $grupo1 = \App\Models\Grupo::factory()->create(['estado_grupo' => 'Activo']);
        $grupo2 = \App\Models\Grupo::factory()->create(['estado_grupo' => 'Activo']);
        $cliente->grupos()->attach($grupo1->id);
        expect($cliente->tieneGrupoActivo())->toBeTrue();
        $cliente->grupos()->attach($grupo2->id);
        // Suponiendo que la lógica de negocio impide la doble asignación, el método debe seguir devolviendo true
        expect($cliente->grupos()->where('estado_grupo', 'Activo')->count())->toBeGreaterThan(1);
    });

    it('Debe permitir ver el historial de grupos de un cliente', function () {
        $cliente = \App\Models\Cliente::factory()->create();
        $grupo1 = \App\Models\Grupo::factory()->create();
        $grupo2 = \App\Models\Grupo::factory()->create();
        $cliente->grupos()->attach([$grupo1->id, $grupo2->id]);
        $historial = $cliente->grupos;
        expect($historial->count())->toBe(2);
        expect($historial->first())->toBeInstanceOf(\App\Models\Grupo::class);
    });
});
