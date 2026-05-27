<?php

use App\Domain\Prestamos\RetanqueoWorkflowService;

/**
 * Task 2.2 — Unit: RetanqueoWorkflowService (SR-4)
 * Pure unit tests — no DB.
 */
it('RetanqueoWorkflowService can be instantiated', function () {
    $service = new RetanqueoWorkflowService();
    expect($service)->toBeInstanceOf(RetanqueoWorkflowService::class);
});

it('RetanqueoWorkflowService has method crearSolicitudRetanqueo', function () {
    expect(method_exists(RetanqueoWorkflowService::class, 'crearSolicitudRetanqueo'))->toBeTrue();
});

it('RetanqueoWorkflowService has method aprobarRetanqueo', function () {
    expect(method_exists(RetanqueoWorkflowService::class, 'aprobarRetanqueo'))->toBeTrue();
});

it('RetanqueoWorkflowService has method rechazarRetanqueo', function () {
    expect(method_exists(RetanqueoWorkflowService::class, 'rechazarRetanqueo'))->toBeTrue();
});

it('RetanqueoWorkflowService does NOT have ejecutarRetanqueo (belongs to EjecucionService)', function () {
    expect(method_exists(RetanqueoWorkflowService::class, 'ejecutarRetanqueo'))->toBeFalse();
});
