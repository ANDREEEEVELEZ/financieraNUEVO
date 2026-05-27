<?php

use App\Contracts\AuditServiceInterface;
use App\Events\Domain\ReagrupacionEjecutada;
use App\Listeners\Domain\AuditListener;
use App\Models\Reagrupacion;
use Illuminate\Events\Attributes\AsEventListener;

/**
 * Task 1.6 — Unit: AuditListener handles ReagrupacionEjecutada (SR-3)
 * Pure unit tests — no DB, no factories.
 */
it('AuditListener::handleReagrupacionEjecutada calls registrar with reagrupacion.ejecutada', function () {
    $reagrupacion = new Reagrupacion();
    $reagrupacion->id = 10;
    $reagrupacion->grupo_origen_id = 1;
    $reagrupacion->grupo_nuevo_id  = 2;
    $reagrupacion->tipo            = 'retanqueo';
    $reagrupacion->monto_descuento = 100.00;
    $reagrupacion->observaciones   = 'obs';

    $auditMock = Mockery::mock(AuditServiceInterface::class);
    $auditMock->shouldReceive('registrar')
        ->once()
        ->with(
            'reagrupacion.ejecutada',
            Mockery::type(Reagrupacion::class),
            Mockery::type('array'),
            Mockery::type('array'),
            Mockery::type('string')
        );

    $listener = new AuditListener($auditMock);
    $listener->handleReagrupacionEjecutada(new ReagrupacionEjecutada($reagrupacion));
});

it('AuditListener::handleReagrupacionEjecutada is annotated with #[AsEventListener]', function () {
    $method = new ReflectionMethod(AuditListener::class, 'handleReagrupacionEjecutada');
    $attrs   = $method->getAttributes(AsEventListener::class);

    expect($attrs)->not->toBeEmpty();

    $args = $attrs[0]->getArguments();
    expect($args)->toContain(ReagrupacionEjecutada::class);
});
