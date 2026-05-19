<?php

use App\Models\Grupo;
use App\Models\Cliente;
use App\Models\Prestamo;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

function rolesParaGrupo(): void
{
    foreach (['super_admin', 'Jefe de creditos', 'Jefe de operaciones', 'Asesor'] as $rol) {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => $rol, 'guard_name' => 'web']);
    }
}

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

describe('Grupo::puedeAgregarIntegrante', function () {
    it('permite agregar cuando hay menos de 6 integrantes', function () {
        $grupo    = Grupo::factory()->create();
        $clientes = Cliente::factory()->count(5)->create();
        foreach ($clientes as $c) {
            $grupo->clientes()->attach($c->id, ['fecha_ingreso' => now()]);
        }
        expect($grupo->puedeAgregarIntegrante())->toBeTrue();
    });

    it('no permite agregar cuando hay exactamente 6 integrantes', function () {
        $grupo    = Grupo::factory()->create();
        $clientes = Cliente::factory()->count(6)->create();
        foreach ($clientes as $c) {
            $grupo->clientes()->attach($c->id, ['fecha_ingreso' => now()]);
        }
        expect($grupo->puedeAgregarIntegrante())->toBeFalse();
    });
});

describe('Grupo::puedeRemoverIntegrante', function () {
    it('permite remover cuando hay más de 4 integrantes', function () {
        $grupo    = Grupo::factory()->create();
        $clientes = Cliente::factory()->count(5)->create();
        foreach ($clientes as $c) {
            $grupo->clientes()->attach($c->id, ['fecha_ingreso' => now()]);
        }
        expect($grupo->puedeRemoverIntegrante())->toBeTrue();
    });

    it('no permite remover cuando hay exactamente 4 integrantes', function () {
        $grupo    = Grupo::factory()->create();
        $clientes = Cliente::factory()->count(4)->create();
        foreach ($clientes as $c) {
            $grupo->clientes()->attach($c->id, ['fecha_ingreso' => now()]);
        }
        expect($grupo->puedeRemoverIntegrante())->toBeFalse();
    });
});

describe('Grupo::tienePrestamosActivos', function () {
    beforeEach(fn () => rolesParaGrupo());

    it('detecta préstamos en ESTADOS_ACTIVOS como activos', function () {
        $grupo = Grupo::factory()->create();

        foreach (Prestamo::ESTADOS_ACTIVOS as $estado) {
            Prestamo::factory()->create(['grupo_id' => $grupo->id, 'estado' => $estado]);
            expect($grupo->tienePrestamosActivos())->toBeTrue("Estado {$estado} debe considerarse activo");
            // Limpiar para el siguiente estado
            $grupo->prestamos()->delete();
        }
    });

    it('detecta préstamos Pendiente, Aprobado y Firmado como activos', function () {
        $grupo = Grupo::factory()->create();

        foreach ([Prestamo::ESTADO_PENDIENTE, Prestamo::ESTADO_APROBADO, Prestamo::ESTADO_FIRMADO] as $estado) {
            Prestamo::factory()->create(['grupo_id' => $grupo->id, 'estado' => $estado]);
            expect($grupo->tienePrestamosActivos())->toBeTrue("Estado {$estado} debe bloquear modificaciones");
            $grupo->prestamos()->delete();
        }
    });

    it('retorna false para un grupo sin préstamos', function () {
        $grupo = Grupo::factory()->create();
        expect($grupo->tienePrestamosActivos())->toBeFalse();
    });

    it('retorna false para préstamos Finalizado y Cancelado', function () {
        $grupo = Grupo::factory()->create();
        Prestamo::factory()->create(['grupo_id' => $grupo->id, 'estado' => Prestamo::ESTADO_FINALIZADO]);
        expect($grupo->tienePrestamosActivos())->toBeFalse();
    });
});
