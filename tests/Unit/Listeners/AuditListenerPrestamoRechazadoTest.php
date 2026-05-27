<?php

use App\Contracts\AuditServiceInterface;
use App\Events\Domain\PrestamoRechazado;
use App\Listeners\Domain\AuditListener;
use App\Models\Prestamo;
use Illuminate\Events\Attributes\AsEventListener;

/**
 * Task 1.5 — Unit: AuditListener handles PrestamoRechazado (SR-2)
 * Pure unit tests — no DB, no factories.
 */
it('AuditListener::handlePrestamoRechazado calls registrar with prestamo.rechazado', function () {
    $prestamo = new Prestamo();
    $prestamo->id = 2;

    $auditMock = Mockery::mock(AuditServiceInterface::class);
    $auditMock->shouldReceive('registrar')
        ->once()
        ->with(
            'prestamo.rechazado',
            Mockery::type(Prestamo::class),
            Mockery::type('array'),
            Mockery::type('array'),
            Mockery::type('string')
        );

    $listener = new AuditListener($auditMock);
    $listener->handlePrestamoRechazado(new PrestamoRechazado($prestamo, 'Motivo'));
});

it('AuditListener::handlePrestamoRechazado is annotated with #[AsEventListener]', function () {
    $method = new ReflectionMethod(AuditListener::class, 'handlePrestamoRechazado');
    $attrs   = $method->getAttributes(AsEventListener::class);

    expect($attrs)->not->toBeEmpty();

    $args = $attrs[0]->getArguments();
    expect($args)->toContain(PrestamoRechazado::class);
});
