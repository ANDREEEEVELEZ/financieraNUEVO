<?php

use Tests\TestCase;
use App\Models\Pago;
use App\Models\CuotasGrupales;
use App\Models\Prestamo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);
uses(Tests\TestCase::class);

it('verifica que un pago esté asociado a una cuota', function () {
    $prestamo = Prestamo::factory()->create([
        'estado' => 'aprobado',
    ]);

    $cuota = CuotasGrupales::factory()->create([
        'prestamo_id' => $prestamo->id,
    ]);

    $pago = Pago::factory()->create([
        'cuota_grupal_id' => $cuota->id,
        'monto_pagado' => 100,
    ]);

    expect($pago->cuotaGrupal)->toBeInstanceOf(CuotasGrupales::class);
    expect($pago->cuotaGrupal->id)->toBe($cuota->id);
});

it('verifica si un pago cubre totalmente la cuota', function () {
    $prestamo = Prestamo::factory()->create([
        'estado' => 'aprobado',
    ]);

    $cuota = CuotasGrupales::factory()->create([
        'prestamo_id' => $prestamo->id,
        'monto_cuota_grupal' => 150,
    ]);

    $pago = Pago::factory()->create([
        'cuota_grupal_id' => $cuota->id,
        'monto_pagado' => 150,
    ]);

    $estaPagado = $pago->monto_pagado >= $cuota->monto_cuota_grupal;

    expect($estaPagado)->toBeTrue();
});

it('detecta que el pago no cubre totalmente la cuota', function () {
    $prestamo = Prestamo::factory()->create([
        'estado' => 'aprobado',
    ]);

    $cuota = CuotasGrupales::factory()->create([
        'prestamo_id' => $prestamo->id,
        'monto_cuota_grupal' => 200,
    ]);

    $pago = Pago::factory()->create([
        'cuota_grupal_id' => $cuota->id,
        'monto_pagado' => 150,
    ]);

    $estaPagado = $pago->monto_pagado >= $cuota->monto_cuota_grupal;

    expect($estaPagado)->toBeFalse();
});
