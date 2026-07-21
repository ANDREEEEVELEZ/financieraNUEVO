<?php

use App\Models\CuotasGrupales;
use App\Models\Grupo;
use App\Models\Mora;
use App\Models\Pago;
use App\Models\Prestamo;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Characterization tests for the CuotasGrupales saldo accessors (Req 3 —
 * single saldo service).
 *
 * These assert against CONCRETE, hand-computed numeric values (arithmetic shown
 * in each test's comments), NOT against another call to the service the accessor
 * delegates to. This is deliberate: the previous version of this file called the
 * same SaldoCuotaService on both sides of the assertion, so it passed by
 * construction regardless of correctness (a tautology). Concrete pinned values
 * catch real regressions — including a regression back to the legacy
 * column-reading / live-mora-recompute behavior.
 *
 * Mora note: Mora::monto_mora_calculado is a computed accessor
 * (numero_integrantes * diasAtraso). Fixtures with mora pin a known value by
 * controlling the grupo's numero_integrantes and the venc/atraso dates, and
 * assert that known value as a precondition before exercising the saldo accessor.
 */
function delegacionCuota(int $integrantes, array $cuotaAttrs = [], array $pagos = [], ?array $mora = null): CuotasGrupales
{
    $grupo = Grupo::factory()->create(['numero_integrantes' => $integrantes]);
    $prestamo = Prestamo::factory()->create([
        'grupo_id' => $grupo->id,
        'estado' => Prestamo::ESTADO_ACTIVO,
    ]);

    $cuota = CuotasGrupales::factory()->create(array_merge([
        'prestamo_id' => $prestamo->id,
        'monto_cuota_grupal' => 200,
        // Stale/wrong on purpose: proves the accessor never reads this legacy column.
        'saldo_pendiente' => 999999.99,
        'estado_pago' => 'pendiente',
        'estado_cuota_grupal' => 'vigente',
    ], $cuotaAttrs));

    if ($mora !== null) {
        Mora::factory()->create(array_merge([
            'cuota_grupal_id' => $cuota->id,
            'estado_mora' => 'pendiente',
        ], $mora));
    }

    foreach ($pagos as $pagoAttrs) {
        Pago::factory()->create(array_merge([
            'cuota_grupal_id' => $cuota->id,
        ], $pagoAttrs));
    }

    return $cuota->fresh();
}

it('getMontoTotalAPagarAttribute returns the ledger-derived total, ignoring the stale saldo_pendiente column', function () {
    // monto_cuota_grupal = 200; one approved pago of 50 (nada a mora); no Mora row.
    // saldoCuota = 200 - max(0, 50 - 0) = 150.00 ; saldoMora = 0.00 (sin fila Mora)
    // total esperado = 150.00
    // La fórmula legacy habría devuelto la columna saldo_pendiente (999999.99) + mora
    // recalculada en vivo; fijar 150.00 detecta una regresión a leer la columna.
    $cuota = delegacionCuota(2, ['monto_cuota_grupal' => 200], [
        ['monto_pagado' => 50, 'monto_mora_pagada' => 0, 'estado_pago' => 'aprobado'],
    ]);

    expect((string) $cuota->getMontoTotalAPagarAttribute())->toBe('150.00');
});

it('getSaldoTotalPendienteAttribute returns the ledger-derived total', function () {
    // monto = 150; pago aprobado 30 (nada a mora); sin Mora.
    // saldoCuota = 150 - 30 = 120.00 ; saldoMora = 0.00 -> total 120.00
    $cuota = delegacionCuota(2, ['monto_cuota_grupal' => 150], [
        ['monto_pagado' => 30, 'monto_mora_pagada' => 0, 'estado_pago' => 'aprobado'],
    ]);

    expect((string) $cuota->getSaldoTotalPendienteAttribute())->toBe('120.00');
});

it('saldoPendiente() returns capital plus mora as two independent buckets', function () {
    // integrantes = 2, dias de atraso = 10 (venc 2025-01-01 -> +1 = 2025-01-02; atraso 2025-01-12)
    // => monto_mora_calculado = 2 * 10 = 20.00 (verificado abajo como precondición)
    // monto = 300; pago aprobado monto_pagado 60, monto_mora_pagada 0.
    // saldoCuota = 300 - max(0, 60 - 0) = 240.00 ; saldoMora = 20 - 0 = 20.00
    // total esperado = 240 + 20 = 260.00
    $cuota = delegacionCuota(
        2,
        [
            'monto_cuota_grupal' => 300,
            'estado_cuota_grupal' => 'mora',
            'fecha_vencimiento' => '2025-01-01',
        ],
        [
            ['monto_pagado' => 60, 'monto_mora_pagada' => 0, 'estado_pago' => 'aprobado'],
        ],
        ['fecha_atraso' => '2025-01-12'],
    );

    // Precondition: el fixture produce una mora conocida de 20.00.
    expect(number_format(abs($cuota->mora->monto_mora_calculado), 2, '.', ''))->toBe('20.00');

    expect((string) $cuota->saldoPendiente())->toBe('260.00');
});

it('getSaldoCuotaPendiente() returns only the capital saldo', function () {
    // monto = 200; pago aprobado 75 (nada a mora); sin Mora.
    // saldoCuota = 200 - max(0, 75 - 0) = 125.00
    $cuota = delegacionCuota(2, ['monto_cuota_grupal' => 200], [
        ['monto_pagado' => 75, 'monto_mora_pagada' => 0, 'estado_pago' => 'aprobado'],
    ]);

    expect((string) $cuota->getSaldoCuotaPendiente())->toBe('125.00');
});

it('getSaldoMoraPendiente() returns mora generada minus mora pagada', function () {
    // integrantes = 3, dias de atraso = 10 => monto_mora_calculado = 3 * 10 = 30.00
    // pago aprobado monto_mora_pagada = 12.
    // saldoMora = 30 - 12 = 18.00
    $cuota = delegacionCuota(
        3,
        [
            'monto_cuota_grupal' => 200,
            'estado_cuota_grupal' => 'mora',
            'fecha_vencimiento' => '2025-01-01',
        ],
        [
            ['monto_pagado' => 12, 'monto_mora_pagada' => 12, 'estado_pago' => 'aprobado'],
        ],
        ['fecha_atraso' => '2025-01-12'],
    );

    // Precondition: mora conocida de 30.00.
    expect(number_format(abs($cuota->mora->monto_mora_calculado), 2, '.', ''))->toBe('30.00');

    expect((string) $cuota->getSaldoMoraPendiente())->toBe('18.00');
});

it('getMoraPagada() returns the real paid-mora sum even when no Mora row exists', function () {
    // Edge case (finding #4): monto_mora_pagada > 0 on an approved pago but NO Mora row.
    // La fórmula legacy devolvía 0 cuando no existía fila Mora; la nueva devuelve la
    // suma real del ledger. pago aprobado monto_mora_pagada = 15 -> esperado 15.00
    $cuota = delegacionCuota(2, ['monto_cuota_grupal' => 200], [
        ['monto_pagado' => 40, 'monto_mora_pagada' => 15, 'estado_pago' => 'aprobado'],
    ]);

    // Sin fila Mora en el fixture.
    expect($cuota->mora)->toBeNull();

    expect((string) $cuota->getMoraPagada())->toBe('15.00');
});
