<?php

use App\Contracts\RetanqueoQueryInterface;
use App\Contracts\RetanqueoWorkflowInterface;
use App\Contracts\RetanqueoEjecucionInterface;

/**
 * Task 2.7 — Unit: Three Retanqueo interfaces exist with correct method signatures (SR-4)
 */
it('RetanqueoQueryInterface exists and declares obtenerGruposElegibles', function () {
    expect(interface_exists(RetanqueoQueryInterface::class))->toBeTrue();

    $reflection = new ReflectionClass(RetanqueoQueryInterface::class);
    expect($reflection->hasMethod('obtenerGruposElegibles'))->toBeTrue();
    expect($reflection->hasMethod('calcularEstadoPrestamo'))->toBeTrue();
});

it('RetanqueoWorkflowInterface exists and declares the three workflow methods', function () {
    expect(interface_exists(RetanqueoWorkflowInterface::class))->toBeTrue();

    $reflection = new ReflectionClass(RetanqueoWorkflowInterface::class);
    expect($reflection->hasMethod('crearSolicitudRetanqueo'))->toBeTrue();
    expect($reflection->hasMethod('aprobarRetanqueo'))->toBeTrue();
    expect($reflection->hasMethod('rechazarRetanqueo'))->toBeTrue();
});

it('RetanqueoEjecucionInterface exists and declares ejecutarRetanqueo', function () {
    expect(interface_exists(RetanqueoEjecucionInterface::class))->toBeTrue();

    $reflection = new ReflectionClass(RetanqueoEjecucionInterface::class);
    expect($reflection->hasMethod('ejecutarRetanqueo'))->toBeTrue();
});

it('RetanqueoQueryService implements RetanqueoQueryInterface', function () {
    expect(class_exists(\App\Domain\Prestamos\RetanqueoQueryService::class))->toBeTrue();
    expect(
        is_a(\App\Domain\Prestamos\RetanqueoQueryService::class, RetanqueoQueryInterface::class, true)
    )->toBeTrue();
});

it('RetanqueoWorkflowService implements RetanqueoWorkflowInterface', function () {
    expect(class_exists(\App\Domain\Prestamos\RetanqueoWorkflowService::class))->toBeTrue();
    expect(
        is_a(\App\Domain\Prestamos\RetanqueoWorkflowService::class, RetanqueoWorkflowInterface::class, true)
    )->toBeTrue();
});

it('RetanqueoEjecucionService implements RetanqueoEjecucionInterface', function () {
    expect(class_exists(\App\Domain\Prestamos\RetanqueoEjecucionService::class))->toBeTrue();
    expect(
        is_a(\App\Domain\Prestamos\RetanqueoEjecucionService::class, RetanqueoEjecucionInterface::class, true)
    )->toBeTrue();
});
