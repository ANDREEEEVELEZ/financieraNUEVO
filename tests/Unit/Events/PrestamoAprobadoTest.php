<?php

use App\Events\Domain\PrestamoAprobado;
use App\Models\Prestamo;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Task 1.1 — Unit: PrestamoAprobado event shape (SR-1)
 * Pure unit tests — no DB, no factories.
 */
it('PrestamoAprobado is a final class using Dispatchable and SerializesModels', function () {
    $reflection = new ReflectionClass(PrestamoAprobado::class);

    expect($reflection->isFinal())->toBeTrue();

    $traits = $reflection->getTraitNames();
    expect($traits)->toContain(Dispatchable::class);
    expect($traits)->toContain(SerializesModels::class);
});

it('PrestamoAprobado constructor accepts a Prestamo and exposes readonly property', function () {
    $prestamo = new Prestamo();
    $prestamo->id = 42;

    $event = new PrestamoAprobado($prestamo);

    expect($event->prestamo)->toBe($prestamo);
    expect($event->prestamo->id)->toBe(42);

    $property = new ReflectionProperty($event, 'prestamo');
    expect($property->isReadOnly())->toBeTrue();
});

it('PrestamoAprobado has a static dispatch method (from Dispatchable trait)', function () {
    expect(method_exists(PrestamoAprobado::class, 'dispatch'))->toBeTrue();
});
