<?php

use App\Models\Cliente;
use App\Models\Prestamo;
use App\Models\PrestamoIndividual;
use App\Domain\Grupos\CicloService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

function crearRolesParaCiclo(): void
{
    foreach (['super_admin', 'Jefe de creditos', 'Jefe de operaciones', 'Asesor'] as $rol) {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => $rol, 'guard_name' => 'web']);
    }
}

describe('CicloService', function () {
    beforeEach(function () {
        crearRolesParaCiclo();
        $this->service = app(CicloService::class);
    });

    it('no promueve el ciclo si el cliente no califica', function () {
        $cliente = Cliente::factory()->create(['ciclo' => 1]);

        // 0 préstamos completados — no sube
        $this->service->actualizarCicloCliente($cliente);

        expect($cliente->fresh()->ciclo)->toBe(1);
    });

    it('promueve el ciclo cuando el cliente califica', function () {
        $cliente = Cliente::factory()->create(['ciclo' => 1]);
        $prestamo = Prestamo::factory()->create(['estado' => 'Activo']);

        // Se necesitan 2 préstamos completados para subir de ciclo 1 → 2
        PrestamoIndividual::factory()->create(['cliente_id' => $cliente->id, 'prestamo_id' => $prestamo->id, 'estado' => 'Finalizado']);
        PrestamoIndividual::factory()->create(['cliente_id' => $cliente->id, 'prestamo_id' => $prestamo->id, 'estado' => 'Finalizado']);

        $this->service->actualizarCicloCliente($cliente->fresh());

        expect($cliente->fresh()->ciclo)->toBeGreaterThan(1);
    });

    it('es idempotente — no sube dos veces el mismo ciclo', function () {
        $cliente = Cliente::factory()->create(['ciclo' => 1]);
        $prestamo = Prestamo::factory()->create(['estado' => 'Activo']);

        PrestamoIndividual::factory()->create([
            'cliente_id'  => $cliente->id,
            'prestamo_id' => $prestamo->id,
            'estado'      => 'Finalizado',
        ]);

        $this->service->actualizarCicloCliente($cliente->fresh());
        $cicloTrasUno = $cliente->fresh()->ciclo;

        $this->service->actualizarCicloCliente($cliente->fresh());
        $cicloTrasDos = $cliente->fresh()->ciclo;

        expect($cicloTrasUno)->toBe($cicloTrasDos);
    });

    it('actualizarCiclosDeGrupo actualiza todos los clientes del grupo', function () {
        $grupo   = \App\Models\Grupo::factory()->create();
        $prestamo = Prestamo::factory()->create([
            'grupo_id' => $grupo->id,
            'estado'   => 'Activo',
        ]);

        $clientes = Cliente::factory()->count(3)->create(['ciclo' => 1]);
        foreach ($clientes as $cliente) {
            $grupo->clientes()->attach($cliente->id, ['fecha_ingreso' => now()]);
            // Se necesitan 2 completados por cliente para subir de ciclo 1 → 2
            PrestamoIndividual::factory()->create(['cliente_id' => $cliente->id, 'prestamo_id' => $prestamo->id, 'estado' => 'Finalizado']);
            PrestamoIndividual::factory()->create(['cliente_id' => $cliente->id, 'prestamo_id' => $prestamo->id, 'estado' => 'Finalizado']);
        }

        $prestamo->load('grupo.clientes');
        $this->service->actualizarCiclosDeGrupo($prestamo);

        foreach ($clientes as $cliente) {
            expect($cliente->fresh()->ciclo)->toBeGreaterThan(1);
        }
    });

    it('actualizarCiclosDeGrupo es seguro si el prestamo no tiene grupo', function () {
        $prestamo = Prestamo::factory()->create(['grupo_id' => null]);

        // No debe lanzar excepción
        expect(fn () => $this->service->actualizarCiclosDeGrupo($prestamo))
            ->not->toThrow(\Throwable::class);
    });
});
