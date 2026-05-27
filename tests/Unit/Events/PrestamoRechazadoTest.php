<?php

use App\Events\Domain\PrestamoRechazado;
use App\Models\Prestamo;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Task 1.2 — Unit: PrestamoRechazado event shape (SR-2)
 * Pure unit tests — no DB, no factories.
 */
it('PrestamoRechazado is a final class using Dispatchable and SerializesModels', function () {
    $reflection = new ReflectionClass(PrestamoRechazado::class);

    expect($reflection->isFinal())->toBeTrue();

    $traits = $reflection->getTraitNames();
    expect($traits)->toContain(Dispatchable::class);
    expect($traits)->toContain(SerializesModels::class);
});

it('PrestamoRechazado constructor accepts Prestamo and optional motivo defaulting to empty string', function () {
    $prestamo = new Prestamo();
    $prestamo->id = 7;

    $eventWithMotivo = new PrestamoRechazado($prestamo, 'Documentación incompleta');
    expect($eventWithMotivo->prestamo)->toBe($prestamo);
    expect($eventWithMotivo->motivo)->toBe('Documentación incompleta');

    $eventNoMotivo = new PrestamoRechazado($prestamo);
    expect($eventNoMotivo->motivo)->toBe('');
});

it('PrestamoRechazado prestamo and motivo properties are readonly', function () {
    $prestamo = new Prestamo();
    $event = new PrestamoRechazado($prestamo, 'Test');

    $prestamoProp = new ReflectionProperty($event, 'prestamo');
    expect($prestamoProp->isReadOnly())->toBeTrue();

    $motivoProp = new ReflectionProperty($event, 'motivo');
    expect($motivoProp->isReadOnly())->toBeTrue();
});

it('PrestamoRechazado has a static dispatch method (from Dispatchable trait)', function () {
    expect(method_exists(PrestamoRechazado::class, 'dispatch'))->toBeTrue();
});
