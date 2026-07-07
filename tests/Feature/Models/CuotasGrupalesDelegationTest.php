<?php

use App\Domain\Pagos\SaldoCuotaService;
use App\Models\CuotasGrupales;
use App\Models\Mora;
use App\Models\Pago;
use App\Models\Prestamo;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Task 1.5/1.6 — RED/GREEN: CuotasGrupales legacy accessors/methods delegate to
 * SaldoCuotaService. Req 3 Scenario 3.1/3.2 — one service, one answer, no
 * independent computation logic left in the model.
 */
it('getMontoTotalAPagarAttribute delegates to SaldoCuotaService::saldoTotal', function () {
    $prestamo = Prestamo::factory()->create(['estado' => Prestamo::ESTADO_ACTIVO]);
    $cuota = CuotasGrupales::factory()->create([
        'prestamo_id' => $prestamo->id,
        'monto_cuota_grupal' => 200,
        'saldo_pendiente' => 999999.99,
    ]);
    Pago::factory()->create([
        'cuota_grupal_id' => $cuota->id,
        'monto_pagado' => 50,
        'monto_mora_pagada' => 0,
        'estado_pago' => 'aprobado',
    ]);
    $cuota = $cuota->fresh();

    $service = new SaldoCuotaService;

    expect((string) $cuota->getMontoTotalAPagarAttribute())->toBe($service->saldoTotal($cuota));
});

it('getSaldoTotalPendienteAttribute delegates to SaldoCuotaService::saldoTotal', function () {
    $prestamo = Prestamo::factory()->create(['estado' => Prestamo::ESTADO_ACTIVO]);
    $cuota = CuotasGrupales::factory()->create([
        'prestamo_id' => $prestamo->id,
        'monto_cuota_grupal' => 150,
        'saldo_pendiente' => 999999.99,
    ]);
    Pago::factory()->create([
        'cuota_grupal_id' => $cuota->id,
        'monto_pagado' => 30,
        'monto_mora_pagada' => 0,
        'estado_pago' => 'aprobado',
    ]);
    $cuota = $cuota->fresh();

    $service = new SaldoCuotaService;

    expect((string) $cuota->getSaldoTotalPendienteAttribute())->toBe($service->saldoTotal($cuota));
});

it('saldoPendiente() delegates to SaldoCuotaService::saldoTotal', function () {
    $prestamo = Prestamo::factory()->create(['estado' => Prestamo::ESTADO_ACTIVO]);
    $cuota = CuotasGrupales::factory()->create([
        'prestamo_id' => $prestamo->id,
        'monto_cuota_grupal' => 300,
        'saldo_pendiente' => 999999.99,
        'estado_cuota_grupal' => 'mora',
        'fecha_vencimiento' => now()->subDays(10)->toDateString(),
    ]);
    Mora::factory()->create([
        'cuota_grupal_id' => $cuota->id,
        'estado_mora' => 'pendiente',
        'fecha_atraso' => now(),
    ]);
    Pago::factory()->create([
        'cuota_grupal_id' => $cuota->id,
        'monto_pagado' => 60,
        'monto_mora_pagada' => 0,
        'estado_pago' => 'aprobado',
    ]);
    $cuota = $cuota->fresh();

    $service = new SaldoCuotaService;

    expect((string) $cuota->saldoPendiente())->toBe($service->saldoTotal($cuota));
});

it('getSaldoCuotaPendiente() delegates to SaldoCuotaService::saldoCuota', function () {
    $prestamo = Prestamo::factory()->create(['estado' => Prestamo::ESTADO_ACTIVO]);
    $cuota = CuotasGrupales::factory()->create([
        'prestamo_id' => $prestamo->id,
        'monto_cuota_grupal' => 200,
        'saldo_pendiente' => 999999.99,
    ]);
    Pago::factory()->create([
        'cuota_grupal_id' => $cuota->id,
        'monto_pagado' => 75,
        'monto_mora_pagada' => 0,
        'estado_pago' => 'aprobado',
    ]);
    $cuota = $cuota->fresh();

    $service = new SaldoCuotaService;

    expect((string) $cuota->getSaldoCuotaPendiente())->toBe($service->saldoCuota($cuota));
});

it('getSaldoMoraPendiente() delegates to SaldoCuotaService::saldoMora', function () {
    $prestamo = Prestamo::factory()->create(['estado' => Prestamo::ESTADO_ACTIVO]);
    $cuota = CuotasGrupales::factory()->create([
        'prestamo_id' => $prestamo->id,
        'monto_cuota_grupal' => 200,
        'saldo_pendiente' => 999999.99,
        'estado_cuota_grupal' => 'mora',
        'fecha_vencimiento' => now()->subDays(10)->toDateString(),
    ]);
    Mora::factory()->create([
        'cuota_grupal_id' => $cuota->id,
        'estado_mora' => 'pendiente',
        'fecha_atraso' => now(),
    ]);
    $cuota = $cuota->fresh();

    $service = new SaldoCuotaService;

    expect((string) $cuota->getSaldoMoraPendiente())->toBe($service->saldoMora($cuota));
});

it('getMoraPagada() delegates to SaldoCuotaService::moraPagada', function () {
    $prestamo = Prestamo::factory()->create(['estado' => Prestamo::ESTADO_ACTIVO]);
    $cuota = CuotasGrupales::factory()->create([
        'prestamo_id' => $prestamo->id,
        'monto_cuota_grupal' => 200,
        'saldo_pendiente' => 999999.99,
    ]);
    Pago::factory()->create([
        'cuota_grupal_id' => $cuota->id,
        'monto_pagado' => 40,
        'monto_mora_pagada' => 15,
        'estado_pago' => 'aprobado',
    ]);
    $cuota = $cuota->fresh();

    $service = new SaldoCuotaService;

    expect((string) $cuota->getMoraPagada())->toBe($service->moraPagada($cuota));
});
