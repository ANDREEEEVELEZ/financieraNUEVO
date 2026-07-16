<?php

use App\Domain\Documentos\ReporteProfesionalService;
use App\Filament\Dashboard\Resources\PagoResource\Pages\ListPagos;
use App\Filament\Dashboard\Resources\PagoResource\Widgets\PagosStatsWidget;
use App\Models\CuotasGrupales;
use App\Models\Grupo;
use App\Models\Pago;
use App\Models\Prestamo;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * Task 3.11/3.12 — retanqueo-tipo Pago excluded from cobranza-metrics readers
 * (Req 2 items 1, 2, 5, 6, 7; Scenario 2.1). A cash-tipo pago is included
 * normally in every reader.
 */
function pagosStatsMakeCuotaConPagosMixtos(): CuotasGrupales
{
    $grupo = Grupo::factory()->create();
    $prestamo = Prestamo::factory()->create([
        'grupo_id' => $grupo->id,
        'estado' => Prestamo::ESTADO_ACTIVO,
    ]);
    $cuota = CuotasGrupales::factory()->create([
        'prestamo_id' => $prestamo->id,
        'monto_cuota_grupal' => 100,
        'estado_pago' => 'parcial',
        'estado_cuota_grupal' => 'vigente',
    ]);

    Pago::factory()->create([
        'cuota_grupal_id' => $cuota->id,
        'origen_pago' => 'cobranza',
        'monto_pagado' => 100,
        'monto_mora_pagada' => 5,
        'estado_pago' => 'aprobado',
        'fecha_pago' => now(),
    ]);
    Pago::factory()->create([
        'cuota_grupal_id' => $cuota->id,
        'origen_pago' => 'retanqueo',
        'monto_pagado' => 50,
        'monto_mora_pagada' => 0,
        'estado_pago' => 'aprobado',
        'fecha_pago' => now(),
    ]);

    return $cuota->fresh();
}

function pagosStatsActingAsSuperAdmin(): User
{
    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $admin = User::factory()->create(['active' => true]);
    $admin->assignRole('super_admin');

    return $admin;
}

// ── 1. PagosStatsWidget (Req 2 item 1) ──────────────────────────────────────

it('PagosStatsWidget excludes retanqueo-tipo pagos from Monto Total Registrado', function () {
    $admin = pagosStatsActingAsSuperAdmin();
    test()->actingAs($admin);
    pagosStatsMakeCuotaConPagosMixtos();

    $widget = new PagosStatsWidget;
    $method = new ReflectionMethod($widget, 'getStats');
    $method->setAccessible(true);
    $stats = $method->invoke($widget);

    $montoTotalStat = collect($stats)->first(fn ($stat) => $stat->getLabel() === 'Monto Total Registrado');

    expect($montoTotalStat->getValue())->toBe('S/100.00');
});

// ── 2. ListPagos (Req 2 item 2) ─────────────────────────────────────────────

it('ListPagos eager-loaded cobranza sums exclude retanqueo-tipo pagos', function () {
    $admin = pagosStatsActingAsSuperAdmin();
    test()->actingAs($admin);
    $cuota = pagosStatsMakeCuotaConPagosMixtos();

    $page = new ListPagos;
    $method = new ReflectionMethod($page, 'getTableQuery');
    $method->setAccessible(true);
    $prestamo = $method->invoke($page)->first();

    $cuotaCargada = $prestamo->cuotasGrupales->firstWhere('id', $cuota->id);

    // Req 2 item 2: total_pagado_aprobado / monto_mora_pendiente columns read
    // these cobranza-only aggregates — must exclude the retanqueo pago (S/50).
    expect((float) $cuotaCargada->monto_pagado_cobranza_sum)->toEqual(100.0);
    expect((float) $cuotaCargada->monto_mora_pagada_cobranza_sum)->toEqual(5.0);

    // saldo_pendiente_real (SaldoCuotaService-backed) INCLUDES both origins —
    // retanqueo coverage legitimately reduces the real balance owed (D1 crux).
    expect((float) $cuotaCargada->monto_pagado_aprobado_sum)->toEqual(150.0);
});

// ── 3. ReporteProfesionalService (Req 2 item 5) ─────────────────────────────

it('ReporteProfesionalService totals exclude retanqueo-tipo pagos from the rendered PDF', function () {
    $cuota = pagosStatsMakeCuotaConPagosMixtos();
    $pagos = $cuota->pagos()->with('cuotaGrupal.prestamo.grupo')->get();

    $capturedHtml = null;
    // The real Barryvdh\DomPDF\PDF::loadHTML() return type is enforced (PHP 8+
    // strict return types apply even to faked facade calls), so the mock chain
    // must itself be an instance of that class — a plain array-based Mockery
    // mock does not satisfy `instanceof PDF` and throws a TypeError.
    $pdfMock = \Mockery::mock(\Barryvdh\DomPDF\PDF::class);
    $pdfMock->shouldReceive('setOptions')->andReturnSelf();
    $pdfMock->shouldReceive('setPaper')->andReturnSelf();

    Pdf::shouldReceive('loadHTML')
        ->once()
        ->with(\Mockery::on(function ($html) use (&$capturedHtml) {
            $capturedHtml = $html;

            return true;
        }))
        ->andReturn($pdfMock);

    (new ReporteProfesionalService)->generarReportePagos($pagos);

    expect($capturedHtml)->toContain('S/100.00'); // Monto de Pagos (cobranza-only)
    expect($capturedHtml)->not->toContain('S/150.00'); // NUNCA la suma con retanqueo incluido
});

// ── 4. PagoExportNuevoController Excel export (Req 2 item 6) ───────────────

it('PagoExportNuevoController Excel export totals exclude retanqueo-tipo pagos', function () {
    $admin = pagosStatsActingAsSuperAdmin();
    pagosStatsMakeCuotaConPagosMixtos();

    // The route name is "excel" but PagoExportNuevoController::export() actually
    // branches on the `formato` query param (default 'pdf') — without it, this
    // hits exportPDFProfesional() instead of exportExcel().
    $response = test()->actingAs($admin)->get('/pagos/exportar/excel?formato=excel');

    $response->assertOk();
    $html = $response->getContent();

    expect($html)->toContain('S/ 100.00');
    expect($html)->not->toContain('S/ 150.00');
});

// ── 5. PagoPdfController (Req 2 item 7) ─────────────────────────────────────

it('PagoPdfController excludes retanqueo-tipo pagos from the exported row list', function () {
    $admin = pagosStatsActingAsSuperAdmin();
    $cuota = pagosStatsMakeCuotaConPagosMixtos();

    $captured = null;
    View::composer('pdf.pagos', function ($view) use (&$captured) {
        $captured = $view->getData()['pagos'];
    });

    test()->actingAs($admin)->get('/pagos/exportar/pdf')->assertOk();

    expect($captured)->not->toBeNull();
    expect($captured->pluck('origen_pago')->unique()->all())->toBe(['cobranza']);
    expect((float) $captured->sum('monto_pagado'))->toEqual(100.0);
});
