<?php

use App\Events\Domain\ReagrupacionEjecutada;
use App\Models\Reagrupacion;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Task 1.3 — Unit: ReagrupacionEjecutada event shape (SR-3)
 * Pure unit tests — no DB, no factories.
 */
it('ReagrupacionEjecutada is a final class using Dispatchable and SerializesModels', function () {
    $reflection = new ReflectionClass(ReagrupacionEjecutada::class);

    expect($reflection->isFinal())->toBeTrue();

    $traits = $reflection->getTraitNames();
    expect($traits)->toContain(Dispatchable::class);
    expect($traits)->toContain(SerializesModels::class);
});

it('ReagrupacionEjecutada constructor accepts a Reagrupacion and exposes readonly property', function () {
    $reagrupacion = new Reagrupacion();
    $reagrupacion->id = 99;

    $event = new ReagrupacionEjecutada($reagrupacion);

    expect($event->reagrupacion)->toBe($reagrupacion);
    expect($event->reagrupacion->id)->toBe(99);

    $prop = new ReflectionProperty($event, 'reagrupacion');
    expect($prop->isReadOnly())->toBeTrue();
});

it('ReagrupacionEjecutada has a static dispatch method (from Dispatchable trait)', function () {
    expect(method_exists(ReagrupacionEjecutada::class, 'dispatch'))->toBeTrue();
});
