<?php

use App\Contracts\SaldoCuotaServiceInterface;
use App\Filament\Dashboard\Resources\CuotasResource\Pages\ListCuotas;
use App\Filament\Dashboard\Resources\PagoResource;
use App\Filament\Dashboard\Resources\PagoResource\Pages\GrupoDetallePagos;
use App\Models\CuotasGrupales;
use App\Models\Grupo;
use App\Models\Pago;
use App\Models\Prestamo;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Table actions build URLs against the current panel; these resources are
    // registered in the 'dashboard' panel, not the default 'admin' panel.
    Filament::setCurrentPanel(Filament::getPanel('dashboard'));
});

/**
 * Task 2.1/2.2/2.3/2.5 — regression coverage for Slice B2: after migrating the
 * inline saldo formulas in PagoResource.php, GrupoDetallePagos.php, CreatePago.php
 * and CuotasResource/CuotasVigentesWidget to SaldoCuotaService, every reader must
 * keep returning the SAME figures as before (Req 3 Scenario 3.1 parity).
 *
 * Fixture mirrors the reported bug scenario: S/100 cuota, S/60 already paid
 * (cobranza-only — retanqueo-derived fixtures are Slice C's job once
 * origen_pago exists) -> saldo pendiente must be S/40 everywhere.
 */
function makeCuotaConPagoParcial(): CuotasGrupales
{
    $grupo = Grupo::factory()->create();
    $prestamo = Prestamo::factory()->create([
        'grupo_id' => $grupo->id,
        'estado' => Prestamo::ESTADO_ACTIVO,
    ]);
    $cuota = CuotasGrupales::factory()->create([
        'prestamo_id' => $prestamo->id,
        'monto_cuota_grupal' => 100,
        // Valor legacy deliberadamente erroneo: la migracion NUNCA debe leer
        // esta columna (Scenario 3.2 — unica fuente es SaldoCuotaService).
        'saldo_pendiente' => 999999.99,
        'estado_cuota_grupal' => 'vigente',
        'estado_pago' => 'parcial',
    ]);
    Pago::factory()->create([
        'cuota_grupal_id' => $cuota->id,
        'monto_pagado' => 60,
        'monto_mora_pagada' => 0,
        'estado_pago' => 'aprobado',
    ]);

    return $cuota->fresh();
}

it('SaldoCuotaService, PagoResource and GrupoDetallePagos agree on saldo for the same cuota (100 - 60 = 40)', function () {
    $cuota = makeCuotaConPagoParcial();
    $service = app(SaldoCuotaServiceInterface::class);

    expect($service->saldoTotal($cuota))->toBe('40.00');

    $pagoResourceMethod = new ReflectionMethod(PagoResource::class, 'saldoPendienteCuota');
    $pagoResourceMethod->setAccessible(true);
    expect($pagoResourceMethod->invoke(null, $cuota))->toEqualWithDelta(40.0, 0.001);

    $page = new GrupoDetallePagos;
    $page->grupo = $cuota->prestamo->grupo;
    $page->prestamo = $cuota->prestamo;
    $grupoDetalleMethod = new ReflectionMethod($page, 'saldoPendienteCuota');
    $grupoDetalleMethod->setAccessible(true);
    expect($grupoDetalleMethod->invoke($page, $cuota))->toEqualWithDelta(40.0, 0.001);
});

// NOTE: the GrupoDetallePagos table-column render path is covered by the reflection
// assertion in the first test above (it invokes saldoPendienteCuota() on a real
// GrupoDetallePagos instance). A standalone Livewire::test() of this page is not
// exercised here because it is opened via a route with bound {grupo}/{prestamo}
// parameters, not as a standalone component — Filament's table test harness returns
// a null instance for it. The CuotasResource list page below IS a standalone list
// page, so its column render is verified directly.

it('CuotasResource saldo column matches SaldoCuotaService instead of the raw legacy column', function () {
    $cuota = makeCuotaConPagoParcial();

    $admin = User::factory()->create(['active' => true]);
    $admin->assignRole('super_admin');
    $this->actingAs($admin);

    Livewire::test(ListCuotas::class)
        ->assertTableColumnStateSet('saldo_pendiente', '40.00', $cuota);
});
