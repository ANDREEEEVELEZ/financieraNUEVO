<?php

/**
 * Task 2.7 — architecture guard (Req 3 Scenario 3.4): no inline saldo-pendiente
 * arithmetic (->sum('monto_pagado') combined with manual max()/subtraction math,
 * or a raw read of the legacy cuotas_grupales.saldo_pendiente column) may remain
 * in the Filament readers Slice B2 was responsible for migrating (tasks #246
 * 2.1-2.5). Every one of these files must route saldo/mora figures through
 * App\Domain\Pagos\SaldoCuotaService instead of recomputing them inline.
 *
 * Scope note: PagosStatsWidget, ListPagos (summary columns), ReporteProfesionalService,
 * PagoExportNuevoController and PagoPdfController are INTENTIONALLY excluded from
 * this guard. They compute portfolio-wide "total collected" figures across many
 * Pago rows (not a single cuota's saldo pendiente) — a different requirement
 * (Req 2, retanqueo-exclusion via the future Pago::scopeCobranza()) explicitly
 * owned by Slice C (tasks #246 3.11-3.12), which depends on the origen_pago
 * column that does not exist yet. Forcing them through SaldoCuotaService now
 * would require inventing methods outside design's documented 5-method contract
 * (design obs #244 D3) without fixing the actual retanqueo-exclusion bug.
 *
 * Deliberately framework-free (no Tests\TestCase / no DB): pure file-content
 * assertions, so this guard runs even when the test database is unreachable.
 */
function slicedB2AppPath(string $relative): string
{
    return dirname(__DIR__, 3).'/app/'.$relative;
}

it('no inline saldo-pendiente formula remains in the Slice B2 migrated files', function () {
    $files = [
        slicedB2AppPath('Filament/Dashboard/Resources/PagoResource.php'),
        slicedB2AppPath('Filament/Dashboard/Resources/PagoResource/Pages/GrupoDetallePagos.php'),
        slicedB2AppPath('Filament/Dashboard/Resources/PagoResource/Pages/CreatePago.php'),
        slicedB2AppPath('Filament/Dashboard/Resources/CuotasResource.php'),
        slicedB2AppPath('Filament/Dashboard/Widgets/CuotasVigentesWidget.php'),
    ];

    foreach ($files as $file) {
        expect($file)->toBeFile();

        $contents = file_get_contents($file);

        expect($contents)
            ->not->toContain("sum('monto_pagado')")
            ->not->toContain('floatval($cuota->saldo_pendiente)')
            ->not->toMatch('/max\(\(\$montoCuota\s*\+\s*\$montoMora\)\s*-\s*\$pagosAprobados/');
    }
});

it('Pago model no longer carries the dead saldo_pendiente cast (Req 4.3)', function () {
    $contents = file_get_contents(slicedB2AppPath('Models/Pago.php'));

    expect($contents)->not->toContain("'saldo_pendiente' => 'decimal:2'");
});
