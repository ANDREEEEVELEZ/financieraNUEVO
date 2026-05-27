<?php

use App\Contracts\AuditServiceInterface;
use App\Events\Domain\PrestamoAprobado;
use App\Listeners\Domain\AuditListener;
use App\Models\Prestamo;
use Illuminate\Events\Attributes\AsEventListener;

/**
 * Task 1.4 — Unit: AuditListener handles PrestamoAprobado (SR-1)
 * Pure unit tests — no DB, no factories.
 */
it('AuditListener::handlePrestamoAprobado calls registrar with prestamo.aprobado', function () {
    $prestamo = new Prestamo();
    $prestamo->id = 1;

    $auditMock = Mockery::mock(AuditServiceInterface::class);
    $auditMock->shouldReceive('registrar')
        ->once()
        ->with(
            'prestamo.aprobado',
            Mockery::type(Prestamo::class),
            Mockery::type('array'),
            Mockery::type('array'),
            Mockery::type('string')
        );

    $listener = new AuditListener($auditMock);
    $listener->handlePrestamoAprobado(new PrestamoAprobado($prestamo));
});

it('AuditListener::handlePrestamoAprobado is annotated with #[AsEventListener]', function () {
    $method = new ReflectionMethod(AuditListener::class, 'handlePrestamoAprobado');
    $attrs   = $method->getAttributes(AsEventListener::class);

    expect($attrs)->not->toBeEmpty();

    // Verify the event argument references PrestamoAprobado without booting the app
    $args = $attrs[0]->getArguments();
    expect($args)->toContain(PrestamoAprobado::class);
});
