<?php

use App\Http\Controllers\Api\PagoController;
use App\Http\Controllers\Api\PrestamoController;
use App\Contracts\PagoServiceInterface;
use App\Contracts\AuditServiceInterface;

test('PagoController constructor injects PagoServiceInterface', function () {
    $constructor = (new ReflectionClass(PagoController::class))->getConstructor();

    expect($constructor)->not->toBeNull('PagoController must have a constructor with DI');

    $params = $constructor->getParameters();
    $types  = array_map(fn ($p) => (string) $p->getType(), $params);

    expect($types)->toContain(PagoServiceInterface::class);
});

test('PrestamoController constructor injects AuditServiceInterface', function () {
    $constructor = (new ReflectionClass(PrestamoController::class))->getConstructor();

    expect($constructor)->not->toBeNull('PrestamoController must have a constructor with DI');

    $params = $constructor->getParameters();
    $types  = array_map(fn ($p) => (string) $p->getType(), $params);

    expect($types)->toContain(AuditServiceInterface::class);
});
