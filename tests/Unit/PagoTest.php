<?php

use App\Models\Pago;
use App\Models\CuotasGrupales;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('verifica que un pago esté asociado a una cuota', function () {
    $cuota = CuotasGrupales::factory()->create();
    $pago = Pago::factory()->create([
        'cuota_id' => $cuota->id,
        'monto' => 100,
    ]);

    expect($pago->cuota)->toBeInstanceOf(CuotasGrupales::class);
    expect($pago->cuota->id)->toBe($cuota->id);
});

it('verifica si un pago cubre totalmente la cuota', function () {
    $cuota = CuotasGrupales::factory()->create([
        'monto' => 150,
    ]);

    $pago = Pago::factory()->create([
        'cuota_id' => $cuota->id,
        'monto' => 150,
    ]);

    $estaPagado = $pago->monto >= $cuota->monto;

    expect($estaPagado)->toBeTrue();
});

it('detecta que el pago no cubre totalmente la cuota', function () {
    $cuota = CuotasGrupales::factory()->create([
        'monto' => 200,
    ]);

    $pago = Pago::factory()->create([
        'cuota_id' => $cuota->id,
        'monto' => 150,
    ]);

    $estaPagado = $pago->monto >= $cuota->monto;

    expect($estaPagado)->toBeFalse();
});
