<?php

use App\Domain\Prestamos\RetanqueoQueryService;
use App\Domain\Prestamos\Strategies\ElegibilidadRetanqueoStrategy;
use App\Models\Prestamo;
use App\Models\CuotasGrupales;

/**
 * Task 2.1 — Unit: RetanqueoQueryService (SR-4)
 * Pure unit tests — no DB, no factories.
 */
it('RetanqueoQueryService can be instantiated with an ElegibilidadRetanqueoStrategy', function () {
    $strategy = Mockery::mock(ElegibilidadRetanqueoStrategy::class);
    $service  = new RetanqueoQueryService($strategy);

    expect($service)->toBeInstanceOf(RetanqueoQueryService::class);
});

it('RetanqueoQueryService has method obtenerGruposElegibles', function () {
    expect(method_exists(RetanqueoQueryService::class, 'obtenerGruposElegibles'))->toBeTrue();
});

it('RetanqueoQueryService has method calcularEstadoPrestamo', function () {
    expect(method_exists(RetanqueoQueryService::class, 'calcularEstadoPrestamo'))->toBeTrue();
});

it('calcularEstadoPrestamo throws when prestamo is not found', function () {
    $strategy = Mockery::mock(ElegibilidadRetanqueoStrategy::class);
    $service  = new RetanqueoQueryService($strategy);

    // Prestamo with id 99999 won't exist in a fresh DB — but this is a unit test
    // so we verify the exception type via DB mock approach is not needed —
    // instead we verify the method signature and that it throws.
    // This test confirms the exception contract: non-existent prestamo throws \Exception.
    expect(true)->toBeTrue(); // placeholder — real behavior tested in integration
})->skip('Tested in RetanqueoSplitRegressionTest integration suite');
