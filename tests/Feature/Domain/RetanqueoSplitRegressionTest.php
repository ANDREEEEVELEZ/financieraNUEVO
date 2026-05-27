<?php

use App\Domain\Prestamos\RetanqueoQueryService;
use App\Domain\Prestamos\RetanqueoWorkflowService;
use App\Domain\Prestamos\RetanqueoEjecucionService;
use App\Domain\Prestamos\Strategies\ElegibilidadRetanqueoIndividual;
use App\Contracts\RetanqueoQueryInterface;
use App\Contracts\RetanqueoWorkflowInterface;
use App\Contracts\RetanqueoEjecucionInterface;
use App\Models\Grupo;
use App\Models\Prestamo;
use App\Models\Asesor;
use App\Models\CuotasGrupales;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Task 2.6 — Feature: RetanqueoService decomposition regression suite (SR-4)
 *
 * Verifies that the three new services resolve correctly from the container
 * and that core method signatures are preserved.
 */

it('container resolves RetanqueoQueryInterface to RetanqueoQueryService', function () {
    $service = app(RetanqueoQueryInterface::class);
    expect($service)->toBeInstanceOf(RetanqueoQueryService::class);
});

it('container resolves RetanqueoWorkflowInterface to RetanqueoWorkflowService', function () {
    $service = app(RetanqueoWorkflowInterface::class);
    expect($service)->toBeInstanceOf(RetanqueoWorkflowService::class);
});

it('container resolves RetanqueoEjecucionInterface to RetanqueoEjecucionService', function () {
    $service = app(RetanqueoEjecucionInterface::class);
    expect($service)->toBeInstanceOf(RetanqueoEjecucionService::class);
});

it('RetanqueoQueryService calcularEstadoPrestamo throws for non-existent prestamo', function () {
    $service = app(RetanqueoQueryInterface::class);

    expect(fn () => $service->calcularEstadoPrestamo(999999))
        ->toThrow(\Exception::class, 'Préstamo no encontrado');
});

it('RetanqueoQueryService obtenerGruposElegibles returns empty collection when no eligible groups', function () {
    $service = app(RetanqueoQueryInterface::class);

    $result = $service->obtenerGruposElegibles();

    // No groups seeded → collection is empty
    expect($result)->toBeInstanceOf(\Illuminate\Support\Collection::class);
    expect($result->isEmpty())->toBeTrue();
});

it('RetanqueoQueryService obtenerGruposElegibles returns group with exactly 1 cuota pendiente', function () {
    $service = app(RetanqueoQueryInterface::class);

    $asesor   = Asesor::factory()->create();
    $grupo    = Grupo::factory()->create(['asesor_id' => $asesor->id, 'estado_grupo' => 'Activo']);
    $prestamo = Prestamo::factory()->create([
        'grupo_id' => $grupo->id,
        'estado'   => 'Aprobado',
        'cantidad_cuotas' => 4,
    ]);

    // Create 3 paid + 1 pending cuota
    CuotasGrupales::factory()->count(3)->create([
        'prestamo_id'   => $prestamo->id,
        'estado_pago'   => 'pagado',
        'saldo_pendiente' => 0,
    ]);
    CuotasGrupales::factory()->create([
        'prestamo_id'    => $prestamo->id,
        'estado_pago'    => 'pendiente',
        'saldo_pendiente' => 100,
    ]);

    $result = $service->obtenerGruposElegibles();

    expect($result->count())->toBe(1);
    expect($result->first()->id)->toBe($grupo->id);
});

it('RetanqueoQueryService obtenerGruposElegibles excludes group with 2+ cuotas pendientes', function () {
    $service = app(RetanqueoQueryInterface::class);

    $asesor   = Asesor::factory()->create();
    $grupo    = Grupo::factory()->create(['asesor_id' => $asesor->id, 'estado_grupo' => 'Activo']);
    $prestamo = Prestamo::factory()->create([
        'grupo_id' => $grupo->id,
        'estado'   => 'Aprobado',
        'cantidad_cuotas' => 4,
    ]);

    // 2 paid + 2 pending → NOT eligible
    CuotasGrupales::factory()->count(2)->create([
        'prestamo_id'    => $prestamo->id,
        'estado_pago'    => 'pagado',
        'saldo_pendiente' => 0,
    ]);
    CuotasGrupales::factory()->count(2)->create([
        'prestamo_id'    => $prestamo->id,
        'estado_pago'    => 'pendiente',
        'saldo_pendiente' => 100,
    ]);

    $result = $service->obtenerGruposElegibles();

    expect($result->isEmpty())->toBeTrue();
});

it('RetanqueoWorkflowService throws for non-existent retanqueo on aprobarRetanqueo', function () {
    $service = app(RetanqueoWorkflowInterface::class);

    expect(fn () => $service->aprobarRetanqueo(999999))
        ->toThrow(\Exception::class, 'Retanqueo no encontrado');
});

it('RetanqueoWorkflowService throws for non-existent retanqueo on rechazarRetanqueo', function () {
    $service = app(RetanqueoWorkflowInterface::class);

    expect(fn () => $service->rechazarRetanqueo(999999))
        ->toThrow(\Exception::class, 'Retanqueo no encontrado');
});

it('RetanqueoEjecucionService throws for non-existent retanqueo on ejecutarRetanqueo', function () {
    $service = app(RetanqueoEjecucionInterface::class);

    expect(fn () => $service->ejecutarRetanqueo(999999))
        ->toThrow(\Exception::class, 'Retanqueo no encontrado');
});

it('old RetanqueoService class does not exist (SR-4)', function () {
    expect(class_exists(\App\Domain\Prestamos\RetanqueoService::class, false))->toBeFalse();
});
