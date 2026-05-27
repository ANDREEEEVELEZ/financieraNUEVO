<?php

use App\Http\Resources\Api\CuotaResource;
use App\Models\CuotaIndividual;
use Illuminate\Http\Request;

uses(\Tests\TestCase::class);

/**
 * Build a CuotaResource from a plain model (no DB) and resolve it.
 */
function cuotaResourceResolve(array $attributes): array
{
    $cuota = new CuotaIndividual($attributes);

    $resource = new CuotaResource($cuota);
    $request  = Request::create('/api/cuotas', 'GET');

    return $resource->resolve($request);
}

it('includes monto_capital_original in the response', function () {
    $data = cuotaResourceResolve([
        'id'                     => 1,
        'prestamo_id'            => 10,
        'cliente_id'             => 5,
        'numero_cuota'           => 1,
        'monto_capital_original' => '500.00',
        'monto_interes_original' => '50.00',
        'monto_seguro'           => '10.00',
        'saldo_capital'          => '500.00',
        'saldo_interes'          => '50.00',
        'fecha_vencimiento'      => now()->addDays(10)->toDateString(),
        'estado'                 => 'pendiente',
    ]);

    expect($data)->toHaveKey('monto_capital_original');
});

it('includes monto_seguro in the response', function () {
    $data = cuotaResourceResolve([
        'id'                     => 1,
        'prestamo_id'            => 10,
        'cliente_id'             => 5,
        'numero_cuota'           => 1,
        'monto_capital_original' => '100.00',
        'monto_interes_original' => '10.00',
        'monto_seguro'           => '15.00',
        'saldo_capital'          => '100.00',
        'saldo_interes'          => '10.00',
        'fecha_vencimiento'      => now()->addDays(10)->toDateString(),
        'estado'                 => 'pendiente',
    ]);

    expect($data)->toHaveKey('monto_seguro');
});

it('saldo_total equals saldo_capital + saldo_interes (monto_seguro excluded)', function () {
    $data = cuotaResourceResolve([
        'id'                     => 1,
        'prestamo_id'            => 10,
        'cliente_id'             => 5,
        'numero_cuota'           => 1,
        'monto_capital_original' => '500.00',
        'monto_interes_original' => '50.00',
        'monto_seguro'           => '10.00',
        'saldo_capital'          => '500.00',
        'saldo_interes'          => '50.00',
        'fecha_vencimiento'      => now()->addDays(10)->toDateString(),
        'estado'                 => 'pendiente',
    ]);

    // 500 + 50 = 550 — monto_seguro (10) must NOT be included
    expect($data['saldo_total'])->toBe('550.00');
});

it('saldo_total avoids float drift (0.10 + 0.20 = 0.30 via bcadd)', function () {
    $data = cuotaResourceResolve([
        'id'                     => 1,
        'prestamo_id'            => 10,
        'cliente_id'             => 5,
        'numero_cuota'           => 1,
        'monto_capital_original' => '0.10',
        'monto_interes_original' => '0.20',
        'monto_seguro'           => '0.00',
        'saldo_capital'          => '0.10',
        'saldo_interes'          => '0.20',
        'fecha_vencimiento'      => now()->addDays(10)->toDateString(),
        'estado'                 => 'pendiente',
    ]);

    // Float arithmetic: 0.1 + 0.2 = 0.30000000000000004 — bcadd must give exactly '0.30'
    expect($data['saldo_total'])->toBe('0.30');
});

it('dias_mora is 0 for future cuota', function () {
    $data = cuotaResourceResolve([
        'id'                     => 1,
        'prestamo_id'            => 10,
        'cliente_id'             => 5,
        'numero_cuota'           => 1,
        'monto_capital_original' => '100.00',
        'monto_interes_original' => '10.00',
        'monto_seguro'           => '5.00',
        'saldo_capital'          => '100.00',
        'saldo_interes'          => '10.00',
        'fecha_vencimiento'      => now()->addDays(10)->toDateString(),
        'estado'                 => 'pendiente',
    ]);

    expect($data['dias_mora'])->toBe(0);
});

it('dias_mora is a positive integer for overdue cuota', function () {
    $data = cuotaResourceResolve([
        'id'                     => 1,
        'prestamo_id'            => 10,
        'cliente_id'             => 5,
        'numero_cuota'           => 1,
        'monto_capital_original' => '100.00',
        'monto_interes_original' => '10.00',
        'monto_seguro'           => '5.00',
        'saldo_capital'          => '100.00',
        'saldo_interes'          => '10.00',
        'fecha_vencimiento'      => now()->subDays(5)->toDateString(),
        'estado'                 => 'pendiente',
    ]);

    expect($data['dias_mora'])->toBe(5);
    expect($data['dias_mora'])->toBeInt();
});
