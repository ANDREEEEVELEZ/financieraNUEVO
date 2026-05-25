<?php

declare(strict_types=1);

use App\Models\CuotaIndividual;
use App\Models\Grupo;
use App\Models\Prestamo;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

it('scopeRetanqueoElegible includes prestamo where all non-last cuotas are pagada', function () {
    $grupo    = Grupo::factory()->create();
    $prestamo = Prestamo::factory()->create(['grupo_id' => $grupo->id, 'estado' => 'Activo']);

    // 3 cuotas: first two must be pagada, last can be anything
    CuotaIndividual::factory()->create([
        'prestamo_id'      => $prestamo->id,
        'numero_cuota'     => 1,
        'estado'           => 'pagada',
        'fecha_vencimiento' => now()->subMonths(2)->toDateString(),
    ]);
    CuotaIndividual::factory()->create([
        'prestamo_id'      => $prestamo->id,
        'numero_cuota'     => 2,
        'estado'           => 'pagada',
        'fecha_vencimiento' => now()->subMonths(1)->toDateString(),
    ]);
    CuotaIndividual::factory()->create([
        'prestamo_id'      => $prestamo->id,
        'numero_cuota'     => 3,
        'estado'           => 'pendiente',
        'fecha_vencimiento' => now()->addMonths(1)->toDateString(),
    ]);

    $result = Prestamo::retanqueoElegible()->pluck('id');

    expect($result)->toContain($prestamo->id);
});

it('scopeRetanqueoElegible excludes prestamo with one non-last cuota not pagada', function () {
    $grupo    = Grupo::factory()->create();
    $prestamo = Prestamo::factory()->create(['grupo_id' => $grupo->id, 'estado' => 'Activo']);

    // Cuota 1 is vencida (not pagada) — makes it ineligible
    CuotaIndividual::factory()->create([
        'prestamo_id'      => $prestamo->id,
        'numero_cuota'     => 1,
        'estado'           => 'vencida',
        'fecha_vencimiento' => now()->subMonths(2)->toDateString(),
    ]);
    CuotaIndividual::factory()->create([
        'prestamo_id'      => $prestamo->id,
        'numero_cuota'     => 2,
        'estado'           => 'pagada',
        'fecha_vencimiento' => now()->subMonths(1)->toDateString(),
    ]);
    CuotaIndividual::factory()->create([
        'prestamo_id'      => $prestamo->id,
        'numero_cuota'     => 3,
        'estado'           => 'pendiente',
        'fecha_vencimiento' => now()->addMonths(1)->toDateString(),
    ]);

    $result = Prestamo::retanqueoElegible()->pluck('id');

    expect($result)->not->toContain($prestamo->id);
});

it('scopeRetanqueoElegible includes single-cuota prestamo (no non-last cuotas to violate)', function () {
    $grupo    = Grupo::factory()->create();
    $prestamo = Prestamo::factory()->create(['grupo_id' => $grupo->id, 'estado' => 'Activo']);

    // Only one cuota — it's the last, so there are no non-last cuotas to fail the check
    CuotaIndividual::factory()->create([
        'prestamo_id'      => $prestamo->id,
        'numero_cuota'     => 1,
        'estado'           => 'pendiente',
        'fecha_vencimiento' => now()->addMonths(1)->toDateString(),
    ]);

    $result = Prestamo::retanqueoElegible()->pluck('id');

    expect($result)->toContain($prestamo->id);
});
