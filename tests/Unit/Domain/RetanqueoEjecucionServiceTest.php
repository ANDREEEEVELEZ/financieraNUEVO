<?php

use App\Domain\Prestamos\RetanqueoEjecucionService;

/**
 * Task 2.3 — Unit: RetanqueoEjecucionService (SR-4)
 * Pure unit tests — no DB.
 */
it('RetanqueoEjecucionService can be instantiated', function () {
    $service = new RetanqueoEjecucionService();
    expect($service)->toBeInstanceOf(RetanqueoEjecucionService::class);
});

it('RetanqueoEjecucionService has method ejecutarRetanqueo', function () {
    expect(method_exists(RetanqueoEjecucionService::class, 'ejecutarRetanqueo'))->toBeTrue();
});

it('RetanqueoEjecucionService does NOT have crearSolicitudRetanqueo (belongs to WorkflowService)', function () {
    expect(method_exists(RetanqueoEjecucionService::class, 'crearSolicitudRetanqueo'))->toBeFalse();
});

it('RetanqueoEjecucionService does NOT have aprobarRetanqueo (belongs to WorkflowService)', function () {
    expect(method_exists(RetanqueoEjecucionService::class, 'aprobarRetanqueo'))->toBeFalse();
});
