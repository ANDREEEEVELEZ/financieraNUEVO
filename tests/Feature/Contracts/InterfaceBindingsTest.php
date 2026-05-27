<?php

use App\Contracts\PagoServiceInterface;
use App\Contracts\CronogramaServiceInterface;
use App\Contracts\AuditServiceInterface;
use App\Contracts\SeparacionServiceInterface;
use App\Domain\Pagos\PagoService;
use App\Domain\Prestamos\CronogramaService;
use App\Infrastructure\Audit\AuditService;
use App\Domain\Grupos\MorosoSeparationService;

test('PagoServiceInterface resolves to PagoService', function () {
    $resolved = app(PagoServiceInterface::class);

    expect($resolved)->toBeInstanceOf(PagoService::class);
});

test('CronogramaServiceInterface resolves to CronogramaService', function () {
    $resolved = app(CronogramaServiceInterface::class);

    expect($resolved)->toBeInstanceOf(CronogramaService::class);
});

test('AuditServiceInterface resolves to AuditService', function () {
    $resolved = app(AuditServiceInterface::class);

    expect($resolved)->toBeInstanceOf(AuditService::class);
});

test('SeparacionServiceInterface resolves to MorosoSeparationService', function () {
    $resolved = app(SeparacionServiceInterface::class);

    expect($resolved)->toBeInstanceOf(MorosoSeparationService::class);
});
