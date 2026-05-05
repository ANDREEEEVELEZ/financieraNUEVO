<?php

use App\Models\PrestamoIndividual;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

it('persiste estado como string correctamente', function (): void {
    $pi = PrestamoIndividual::factory()->create(['estado' => 'Aprobado']);
    $pi->refresh();
    expect($pi->estado)->toBe('Aprobado');
});

it('no interpreta 0.00 como estado numérico', function (): void {
    $pi = PrestamoIndividual::factory()->create(['estado' => 'Pendiente']);
    $pi->refresh();
    expect($pi->estado)->toBeString();
    expect($pi->estado)->not->toBe('0.00');
    expect($pi->estado)->not->toBe(0);
});

it('tiene constante ESTADO_PENDIENTE definida como string', function (): void {
    expect(PrestamoIndividual::ESTADO_PENDIENTE)->toBe('Pendiente');
});

it('tiene constante ESTADO_ACTIVO definida como string', function (): void {
    expect(PrestamoIndividual::ESTADO_ACTIVO)->toBe('Activo');
});

it('tiene constante ESTADO_FINALIZADO definida como string', function (): void {
    expect(PrestamoIndividual::ESTADO_FINALIZADO)->toBe('Finalizado');
});

it('tiene constante ESTADO_CANCELADO definida como string', function (): void {
    expect(PrestamoIndividual::ESTADO_CANCELADO)->toBe('Cancelado');
});

it('estaFinalizado devuelve true cuando estado es Finalizado', function (): void {
    $pi = PrestamoIndividual::factory()->make(['estado' => 'Finalizado']);
    expect($pi->estaFinalizado())->toBeTrue();
});

it('estaFinalizado devuelve false cuando estado es Activo', function (): void {
    $pi = PrestamoIndividual::factory()->make(['estado' => 'Activo']);
    expect($pi->estaFinalizado())->toBeFalse();
});
