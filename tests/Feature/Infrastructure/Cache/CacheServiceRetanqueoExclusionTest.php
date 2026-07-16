<?php

declare(strict_types=1);

use App\Contracts\CacheServiceInterface;
use App\Models\Asesor;
use App\Models\CuotasGrupales;
use App\Models\Grupo;
use App\Models\Pago;
use App\Models\Prestamo;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * SDD core-contable-seguridad, Req 2 (12th cobranza-metrics reader — found during
 * Slice C apply-time grep sweep, NOT in the spec's original 10-item enumeration).
 *
 * app/Infrastructure/Cache/CacheService.php::getDashboardStatsAsesor() and
 * ::getDashboardStatsJO() both compute a "monto_cobrado_mes" KPI by summing
 * Pago.monto_pagado for the current month with no origin filter — retanqueo
 * coverage (debt relief between loans, not cash collected) was being counted
 * as money collected. Fixed by chaining Pago::scopeCobranza().
 */
function cacheServiceMakeCuotaConPagosMixtos(?Asesor $asesor = null): array
{
    $asesor = $asesor ?? Asesor::factory()->create();
    $grupo = Grupo::factory()->create(['asesor_id' => $asesor->id]);
    $prestamo = Prestamo::factory()->create([
        'grupo_id' => $grupo->id,
        'estado' => Prestamo::ESTADO_ACTIVO,
    ]);
    $cuota = CuotasGrupales::factory()->create([
        'prestamo_id' => $prestamo->id,
        'estado_pago' => 'parcial',
        'estado_cuota_grupal' => 'vigente',
    ]);

    Pago::factory()->create([
        'cuota_grupal_id' => $cuota->id,
        'origen_pago' => 'cobranza',
        'monto_pagado' => 100,
        'estado_pago' => 'aprobado',
        'fecha_pago' => now(),
    ]);
    Pago::factory()->create([
        'cuota_grupal_id' => $cuota->id,
        'origen_pago' => 'retanqueo',
        'monto_pagado' => 50,
        'estado_pago' => 'aprobado',
        'fecha_pago' => now(),
    ]);

    return [$asesor, $cuota];
}

it('getDashboardStatsAsesor excludes retanqueo-tipo pagos from monto_cobrado_mes', function () {
    [$asesor] = cacheServiceMakeCuotaConPagosMixtos();

    $stats = app(CacheServiceInterface::class)->getDashboardStatsAsesor($asesor->id);

    expect((float) $stats['monto_cobrado_mes'])->toEqual(100.0);
});

it('getDashboardStatsJO excludes retanqueo-tipo pagos from monto_cobrado_mes', function () {
    cacheServiceMakeCuotaConPagosMixtos();

    $stats = app(CacheServiceInterface::class)->getDashboardStatsJO();

    expect((float) $stats['monto_cobrado_mes'])->toEqual(100.0);
});
