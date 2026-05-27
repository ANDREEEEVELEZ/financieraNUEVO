<?php

it('domain and infrastructure service classes exist in their new namespaces', function () {
    $classes = [
        \App\Domain\Pagos\PagoService::class,
        \App\Domain\Pagos\CondonacionMoraService::class,
        \App\Domain\Prestamos\CronogramaService::class,
        \App\Domain\Prestamos\ReagrupacionParcialService::class,
        \App\Domain\Prestamos\RetanqueoQueryService::class,
        \App\Domain\Prestamos\RetanqueoWorkflowService::class,
        \App\Domain\Prestamos\RetanqueoEjecucionService::class,
        \App\Domain\Cartera\CarteraService::class,
        \App\Domain\Cartera\MetaCobranzaService::class,
        \App\Domain\Cartera\WidgetStatsService::class,
        \App\Domain\Grupos\CicloService::class,
        \App\Domain\Grupos\MorosoSeparationService::class,
        \App\Domain\Documentos\DeclaracionJuradaService::class,
        \App\Domain\Documentos\ReporteProfesionalService::class,
        \App\Domain\Documentos\WordTemplateService::class,
        \App\Infrastructure\Audit\AuditService::class,
        \App\Infrastructure\Cache\CacheService::class,
        \App\Infrastructure\Notifications\NotificationService::class,
    ];

    foreach ($classes as $class) {
        expect(class_exists($class))->toBeTrue("Class {$class} does not exist");
    }
});

it('LibreOfficeTemplateService is absent from all namespaces (SR-7)', function () {
    expect(class_exists('App\Domain\Documentos\LibreOfficeTemplateService'))->toBeFalse();
    expect(class_exists('App\Services\LibreOfficeTemplateService'))->toBeFalse();
});

it('old App\\Services namespace classes no longer exist for moved services', function () {
    $movedClasses = [
        'App\Services\PagoService',
        'App\Services\CondonacionMoraService',
        'App\Services\CronogramaService',
        'App\Services\ReagrupacionParcialService',
        'App\Services\RetanqueoService',
        'App\Domain\Prestamos\RetanqueoService',
        'App\Services\CarteraService',
        'App\Services\MetaCobranzaService',
        'App\Services\WidgetStatsService',
        'App\Services\CicloService',
        'App\Services\MorosoSeparationService',
        'App\Services\DeclaracionJuradaService',
        'App\Services\ReporteProfesionalService',
        'App\Services\WordTemplateService',
        'App\Services\AuditService',
        'App\Services\CacheService',
        'App\Services\NotificationService',
    ];

    foreach ($movedClasses as $class) {
        expect(class_exists($class))->toBeFalse("Class {$class} still exists in old namespace");
    }
});
