<?php

use App\Domain\Pagos\SaldoCuotaService;
use App\Models\CuotasGrupales;
use App\Models\Grupo;
use App\Models\Mora;
use App\Models\Pago;
use App\Models\Prestamo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);
uses(Tests\TestCase::class);

/**
 * Task 1.1 — RED: SaldoCuotaService unit tests.
 * Req 3 (single saldo service), Req 3 Scenario 3.1/3.3, Req 7.4 (bcmath precision).
 * Ledger-derived: no reads of the (still-alive) saldo_pendiente column.
 */
function makeCuotaConPagos(array $cuotaAttrs = [], array $pagos = []): CuotasGrupales
{
    $prestamo = Prestamo::factory()->create(['estado' => Prestamo::ESTADO_ACTIVO]);

    $cuota = CuotasGrupales::factory()->create(array_merge([
        'prestamo_id' => $prestamo->id,
        'monto_cuota_grupal' => 200,
        // saldo_pendiente is intentionally set to a WRONG/stale value to prove
        // the service never reads it (ledger-derived only).
        'saldo_pendiente' => 999999.99,
        'estado_pago' => 'pendiente',
        'estado_cuota_grupal' => 'vigente',
    ], $cuotaAttrs));

    foreach ($pagos as $pagoAttrs) {
        Pago::factory()->create(array_merge([
            'cuota_grupal_id' => $cuota->id,
        ], $pagoAttrs));
    }

    return $cuota->fresh();
}

/**
 * Builds a cuota with a KNOWN mora amount by controlling the grupo's
 * numero_integrantes and the venc/atraso dates. monto_mora_calculado is a
 * computed accessor (numero_integrantes * diasAtraso); with fecha_vencimiento
 * 2025-01-01 (mora base = +1 day = 2025-01-02) and fecha_atraso 2025-01-12,
 * diasAtraso = 10, so mora = numero_integrantes * 10.
 */
function makeCuotaConMoraControlada(int $integrantes, float $montoCuota, array $pagos = []): CuotasGrupales
{
    $grupo = Grupo::factory()->create(['numero_integrantes' => $integrantes]);
    $prestamo = Prestamo::factory()->create([
        'grupo_id' => $grupo->id,
        'estado' => Prestamo::ESTADO_ACTIVO,
    ]);

    $cuota = CuotasGrupales::factory()->create([
        'prestamo_id' => $prestamo->id,
        'monto_cuota_grupal' => $montoCuota,
        'saldo_pendiente' => 999999.99,
        'estado_cuota_grupal' => 'mora',
        'fecha_vencimiento' => '2025-01-01',
    ]);

    Mora::factory()->create([
        'cuota_grupal_id' => $cuota->id,
        'estado_mora' => 'pendiente',
        'fecha_atraso' => '2025-01-12',
    ]);

    foreach ($pagos as $pagoAttrs) {
        Pago::factory()->create(array_merge([
            'cuota_grupal_id' => $cuota->id,
        ], $pagoAttrs));
    }

    return $cuota->fresh();
}

it('saldoCuota returns full monto when no pagos exist', function () {
    $cuota = makeCuotaConPagos(['monto_cuota_grupal' => 200]);

    $service = new SaldoCuotaService;

    expect($service->saldoCuota($cuota))->toBe('200.00');
});

it('saldoCuota decreases by approved pagos net of mora', function () {
    $cuota = makeCuotaConPagos(['monto_cuota_grupal' => 200], [
        ['monto_pagado' => 80, 'monto_mora_pagada' => 0, 'estado_pago' => 'aprobado'],
    ]);

    $service = new SaldoCuotaService;

    expect($service->saldoCuota($cuota))->toBe('120.00');
});

it('saldoCuota ignores pending and rechazado pagos', function () {
    $cuota = makeCuotaConPagos(['monto_cuota_grupal' => 200], [
        ['monto_pagado' => 80, 'monto_mora_pagada' => 0, 'estado_pago' => 'pendiente'],
        ['monto_pagado' => 50, 'monto_mora_pagada' => 0, 'estado_pago' => 'rechazado'],
    ]);

    $service = new SaldoCuotaService;

    expect($service->saldoCuota($cuota))->toBe('200.00');
});

it('saldoMora returns mora generada minus mora pagada', function () {
    $cuota = makeCuotaConPagos([
        'monto_cuota_grupal' => 200,
        'estado_cuota_grupal' => 'mora',
        'fecha_vencimiento' => now()->subDays(10)->toDateString(),
    ]);
    Mora::factory()->create([
        'cuota_grupal_id' => $cuota->id,
        'estado_mora' => 'pendiente',
        'fecha_atraso' => now(),
    ]);
    $cuota = $cuota->fresh();
    $montoMoraGenerada = abs($cuota->mora->monto_mora_calculado);
    expect($montoMoraGenerada)->toBeGreaterThan(10);

    Pago::factory()->create([
        'cuota_grupal_id' => $cuota->id,
        'monto_pagado' => 10,
        'monto_mora_pagada' => 10,
        'estado_pago' => 'aprobado',
    ]);

    $service = new SaldoCuotaService;

    $expected = bcsub(number_format($montoMoraGenerada, 2, '.', ''), '10.00', 2);
    expect($service->saldoMora($cuota->fresh()))->toBe($expected);
});

it('saldoMora returns 0.00 when cuota has no mora record', function () {
    $cuota = makeCuotaConPagos(['monto_cuota_grupal' => 200]);

    $service = new SaldoCuotaService;

    expect($service->saldoMora($cuota))->toBe('0.00');
});

it('saldoTotal equals saldoCuota plus saldoMora', function () {
    $cuota = makeCuotaConPagos([
        'monto_cuota_grupal' => 200,
        'estado_cuota_grupal' => 'mora',
        'fecha_vencimiento' => now()->subDays(10)->toDateString(),
    ]);
    Mora::factory()->create([
        'cuota_grupal_id' => $cuota->id,
        'estado_mora' => 'pendiente',
        'fecha_atraso' => now(),
    ]);
    $cuota = $cuota->fresh();
    $montoMoraGenerada = number_format(abs($cuota->mora->monto_mora_calculado), 2, '.', '');
    expect((float) $montoMoraGenerada)->toBeGreaterThan(0);

    Pago::factory()->create([
        'cuota_grupal_id' => $cuota->id,
        'monto_pagado' => 50,
        'monto_mora_pagada' => 0,
        'estado_pago' => 'aprobado',
    ]);

    $service = new SaldoCuotaService;
    $cuota = $cuota->fresh();

    $expectedSaldoCuota = '150.00'; // 200 - 50 (nada de mora pagada)
    $expectedSaldoMora = $montoMoraGenerada; // nada pagado a mora
    $expectedTotal = bcadd($expectedSaldoCuota, $expectedSaldoMora, 2);

    expect($service->saldoCuota($cuota))->toBe($expectedSaldoCuota);
    expect($service->saldoMora($cuota))->toBe($expectedSaldoMora);
    expect($service->saldoTotal($cuota))->toBe($expectedTotal);
});

it('moraPagada sums monto_mora_pagada across approved pagos only', function () {
    $cuota = makeCuotaConPagos(['monto_cuota_grupal' => 200], [
        ['monto_pagado' => 30, 'monto_mora_pagada' => 20, 'estado_pago' => 'aprobado'],
        ['monto_pagado' => 15, 'monto_mora_pagada' => 15, 'estado_pago' => 'aprobado'],
        ['monto_pagado' => 999, 'monto_mora_pagada' => 999, 'estado_pago' => 'pendiente'],
    ]);

    $service = new SaldoCuotaService;

    expect($service->moraPagada($cuota))->toBe('35.00');
});

it('saldoTotal floors capital and mora independently — capital overpayment does not absorb mora debt', function () {
    // Finding #3 (comportamiento intencional): capital sobrepagado por 50, mora de 20 impaga.
    // integrantes = 2, diasAtraso = 10 => monto_mora_calculado = 20.00 (precondición).
    // monto = 100; pago aprobado monto_pagado = 150 (sobrepago de 50 sobre el capital),
    // monto_mora_pagada = 0 (no se pagó mora).
    //   saldoCuota = 100 - max(0, 150 - 0) = max(0, -50) = 0.00
    //   saldoMora  = 20 - 0 = 20.00
    //   saldoTotal = 0.00 + 20.00 = 20.00   (NO 0.00: los buckets no se subsidian)
    $cuota = makeCuotaConMoraControlada(2, 100, [
        ['monto_pagado' => 150, 'monto_mora_pagada' => 0, 'estado_pago' => 'aprobado'],
    ]);

    expect(number_format(abs($cuota->mora->monto_mora_calculado), 2, '.', ''))->toBe('20.00');

    $service = new SaldoCuotaService;

    expect($service->saldoCuota($cuota))->toBe('0.00');
    expect($service->saldoMora($cuota))->toBe('20.00');
    expect($service->saldoTotal($cuota))->toBe('20.00');
});

it('moraPagada returns the real ledger sum even without a Mora row (no legacy null-guard)', function () {
    // Finding #4 (comportamiento intencional): la fórmula legacy devolvía 0 cuando no
    // existía fila Mora; la nueva devuelve la suma real de monto_mora_pagada del ledger.
    // Sin fila Mora; dos pagos aprobados con monto_mora_pagada 20 y 5 => moraPagada = 25.00.
    // saldoMora sigue siendo 0.00 porque no hay mora generada (sin fila Mora).
    $cuota = makeCuotaConPagos(['monto_cuota_grupal' => 200], [
        ['monto_pagado' => 30, 'monto_mora_pagada' => 20, 'estado_pago' => 'aprobado'],
        ['monto_pagado' => 10, 'monto_mora_pagada' => 5, 'estado_pago' => 'aprobado'],
    ]);

    expect($cuota->mora)->toBeNull();

    $service = new SaldoCuotaService;

    expect($service->moraPagada($cuota))->toBe('25.00');
    expect($service->saldoMora($cuota))->toBe('0.00');
});

it('bcmath precision: partial payment leaves exact 2-decimal remainder (0.01 edge case)', function () {
    $cuota = makeCuotaConPagos(['monto_cuota_grupal' => 100.01], [
        ['monto_pagado' => 33.34, 'monto_mora_pagada' => 0, 'estado_pago' => 'aprobado'],
        ['monto_pagado' => 33.33, 'monto_mora_pagada' => 0, 'estado_pago' => 'aprobado'],
    ]);

    $service = new SaldoCuotaService;

    expect($service->saldoCuota($cuota))->toBe('33.34');
});

it('saldoTotalGrupo sums saldoTotal across active cuotas of a prestamo', function () {
    $prestamo = Prestamo::factory()->create(['estado' => Prestamo::ESTADO_ACTIVO]);

    $cuota1 = CuotasGrupales::factory()->create([
        'prestamo_id' => $prestamo->id,
        'monto_cuota_grupal' => 100,
        'saldo_pendiente' => 0,
        'estado_cuota_grupal' => 'vigente',
    ]);
    $cuota2 = CuotasGrupales::factory()->create([
        'prestamo_id' => $prestamo->id,
        'monto_cuota_grupal' => 150,
        'saldo_pendiente' => 0,
        'estado_cuota_grupal' => 'mora',
    ]);
    // Cancelada cuotas are fully paid — must not distort the aggregate (saldo would be 0 anyway,
    // but this proves the aggregate iterates all cuotas passed by the query, not a hardcoded set).
    $cuotaCancelada = CuotasGrupales::factory()->create([
        'prestamo_id' => $prestamo->id,
        'monto_cuota_grupal' => 80,
        'saldo_pendiente' => 0,
        'estado_cuota_grupal' => 'cancelada',
    ]);
    Pago::factory()->create([
        'cuota_grupal_id' => $cuotaCancelada->id,
        'monto_pagado' => 80,
        'monto_mora_pagada' => 0,
        'estado_pago' => 'aprobado',
    ]);

    Pago::factory()->create([
        'cuota_grupal_id' => $cuota1->id,
        'monto_pagado' => 40,
        'monto_mora_pagada' => 0,
        'estado_pago' => 'aprobado',
    ]);

    $service = new SaldoCuotaService;

    // cuota1: 100 - 40 = 60.00; cuota2: 150 - 0 = 150.00; cuotaCancelada: 80 - 80 = 0.00
    expect($service->saldoTotalGrupo($prestamo->fresh()))->toBe('210.00');
});

it('saldoTotalGrupo does not N+1 across cuotas (withSum eager aggregate)', function () {
    $service = new SaldoCuotaService;

    $queryCountFor = function (int $numCuotas) use ($service) {
        $prestamo = Prestamo::factory()->create(['estado' => Prestamo::ESTADO_ACTIVO]);

        foreach (range(1, $numCuotas) as $n) {
            $cuota = CuotasGrupales::factory()->create([
                'prestamo_id' => $prestamo->id,
                'numero_cuota' => $n,
                'monto_cuota_grupal' => 100,
                'saldo_pendiente' => 0,
                'estado_cuota_grupal' => 'vigente',
            ]);
            // Only the FIRST cuota has an approved pago; the rest have ZERO approved
            // pagos, so withSum yields NULL for them. That NULL is exactly the case
            // the previous isset() detection masked: it treated a null sum as
            // "not eager-loaded" and fired a per-cuota N+1 fallback query. If the
            // detection is wrong, the query count grows with cuota count and the
            // assertion below fails.
            if ($n === 1) {
                Pago::factory()->create([
                    'cuota_grupal_id' => $cuota->id,
                    'monto_pagado' => 20,
                    'monto_mora_pagada' => 0,
                    'estado_pago' => 'aprobado',
                ]);
            }
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $service->saldoTotalGrupo($prestamo->fresh());
        $log = DB::getQueryLog();
        DB::disableQueryLog();

        return count($log);
    };

    $queriesWith4Cuotas = $queryCountFor(4);
    $queriesWith8Cuotas = $queryCountFor(8);

    // Query count must be CONSTANT regardless of cuota count — proves no per-cuota
    // query is issued (the N+1 this task guards against would make 8-cuota queries
    // roughly double the 4-cuota count).
    expect($queriesWith8Cuotas)->toBe($queriesWith4Cuotas);
});
