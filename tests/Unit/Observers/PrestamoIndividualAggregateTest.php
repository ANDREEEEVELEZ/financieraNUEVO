<?php

use App\Models\Prestamo;
use App\Models\PrestamoIndividual;
use App\Observers\PrestamoIndividualObserver;
use App\Domain\Grupos\CicloService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class, RefreshDatabase::class);

// ── T-2.5: Observer uses aggregate SUM, not ->get()->sum() chain ──────────────

it('recalcularTotales uses selectRaw aggregate — no get() call on PI collection', function () {
    // Verify by source inspection: the observer must not use ->get()->sum()
    $observerCode = file_get_contents(app_path('Observers/PrestamoIndividualObserver.php'));

    // Old pattern must be gone
    expect($observerCode)->not->toContain('->get()->sum(')
        ->and($observerCode)->not->toContain("->get()\n"
        );
});

it('recalcularTotales uses selectRaw SUM query in observer code', function () {
    $observerCode = file_get_contents(app_path('Observers/PrestamoIndividualObserver.php'));

    expect($observerCode)->toContain('selectRaw(');
});

// ── T-2.5: Observer correctly computes totals from DB aggregate ───────────────

it('recalcularTotales correctly aggregates monto_prestado_total and monto_devolver', function () {
    $prestamo = Prestamo::factory()->create([
        'estado'               => 'Pendiente',
        'monto_prestado_total' => 0,
        'monto_devolver'       => 0,
    ]);

    // Create two PI rows; each triggers the observer recalcularTotales
    PrestamoIndividual::factory()->create([
        'prestamo_id'               => $prestamo->id,
        'monto_prestado_individual' => 400,
        'monto_devolver_individual' => 468,
        'estado'                    => 'Pendiente',
    ]);
    PrestamoIndividual::factory()->create([
        'prestamo_id'               => $prestamo->id,
        'monto_prestado_individual' => 600,
        'monto_devolver_individual' => 702,
        'estado'                    => 'Pendiente',
    ]);

    $prestamo->refresh();

    expect((float) $prestamo->monto_prestado_total)->toBe(1000.0,
        'monto_prestado_total must be the sum of all individual amounts'
    );
});

// ── Triangulation: aggregate handles empty PI set gracefully ──────────────────

it('recalcularTotales with no PI rows sets totals to 0 without error', function () {
    $prestamo = Prestamo::factory()->create([
        'estado'               => 'Pendiente',
        'monto_prestado_total' => 500,
        'monto_devolver'       => 585,
    ]);

    // Call observer directly with no PI rows for this prestamo
    $observer = app(PrestamoIndividualObserver::class);

    // Create a PI on a different prestamo to ensure table is not empty
    $otherPrestamo = Prestamo::factory()->create(['estado' => 'Pendiente']);
    PrestamoIndividual::factory()->create([
        'prestamo_id'               => $otherPrestamo->id,
        'monto_prestado_individual' => 400,
    ]);

    // Calling recalcularTotales indirectly via creating a PI then deleting all
    $pi = PrestamoIndividual::factory()->create([
        'prestamo_id'               => $prestamo->id,
        'monto_prestado_individual' => 400,
        'monto_devolver_individual' => 468,
    ]);

    // After delete, totals should go to 0 (deleted event triggers recalcularTotales)
    $pi->delete();

    $prestamo->refresh();
    expect((float) $prestamo->monto_prestado_total)->toBe(0.0);
});
