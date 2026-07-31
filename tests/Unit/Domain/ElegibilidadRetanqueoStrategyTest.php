<?php

use App\Domain\Prestamos\Strategies\ElegibilidadRetanqueoStrategy;
use App\Domain\Prestamos\Strategies\ElegibilidadRetanqueoIndividual;
use App\Domain\Prestamos\Strategies\ElegibilidadRetanqueoGrupal;

/**
 * Task 2.4 — Unit: ElegibilidadRetanqueoStrategy interface + implementations exist (SR-4)
 * Pure unit tests — no DB.
 */
it('ElegibilidadRetanqueoStrategy is an interface', function () {
    $reflection = new ReflectionClass(ElegibilidadRetanqueoStrategy::class);
    expect($reflection->isInterface())->toBeTrue();
});

it('ElegibilidadRetanqueoStrategy declares esElegible(Prestamo $prestamo): bool', function () {
    $reflection = new ReflectionClass(ElegibilidadRetanqueoStrategy::class);
    expect($reflection->hasMethod('esElegible'))->toBeTrue();

    $method = $reflection->getMethod('esElegible');
    $params = $method->getParameters();
    expect(count($params))->toBe(1);
    expect($params[0]->getName())->toBe('prestamo');
    expect((string)$params[0]->getType())->toBe(\App\Models\Prestamo::class);
});

it('ElegibilidadRetanqueoIndividual implements ElegibilidadRetanqueoStrategy', function () {
    expect(class_exists(ElegibilidadRetanqueoIndividual::class))->toBeTrue();
    expect(
        is_a(ElegibilidadRetanqueoIndividual::class, ElegibilidadRetanqueoStrategy::class, true)
    )->toBeTrue();
});

it('ElegibilidadRetanqueoGrupal implements ElegibilidadRetanqueoStrategy', function () {
    expect(class_exists(ElegibilidadRetanqueoGrupal::class))->toBeTrue();
    expect(
        is_a(ElegibilidadRetanqueoGrupal::class, ElegibilidadRetanqueoStrategy::class, true)
    )->toBeTrue();
});

it('ElegibilidadRetanqueoIndividual can be instantiated', function () {
    $strategy = new ElegibilidadRetanqueoIndividual();
    expect($strategy)->toBeInstanceOf(ElegibilidadRetanqueoIndividual::class);
});

it('ElegibilidadRetanqueoGrupal can be instantiated', function () {
    $strategy = new ElegibilidadRetanqueoGrupal();
    expect($strategy)->toBeInstanceOf(ElegibilidadRetanqueoGrupal::class);
});

/**
 * Eje 1 (dominio-pagos-mora-retanqueo) — Task 1.1/1.2: esElegible() is now a
 * pure structural check (discovery-time, no participation data available
 * yet), separate from the real quorum check which lives in cumpleQuorum().
 */
it('ElegibilidadRetanqueoGrupal declares cumpleQuorum(Retanqueo $retanqueo): bool', function () {
    $reflection = new ReflectionClass(ElegibilidadRetanqueoGrupal::class);
    expect($reflection->hasMethod('cumpleQuorum'))->toBeTrue();

    $method = $reflection->getMethod('cumpleQuorum');
    $params = $method->getParameters();
    expect(count($params))->toBe(1);
    expect((string)$params[0]->getType())->toBe(\App\Models\Retanqueo::class);
    expect((string)$method->getReturnType())->toBe('bool');
});
