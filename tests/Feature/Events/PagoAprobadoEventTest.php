<?php

use App\Events\Domain\PagoAprobado;
use App\Models\Cliente;
use App\Models\CuotaIndividual;
use App\Models\CuotasGrupales;
use App\Models\Pago;
use App\Models\PrestamoIndividual;
use App\Models\Prestamo;
use App\Domain\Pagos\PagoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

/**
 * PR3-T1: PagoAprobado domain event dispatched on aprobarPago().
 *
 * RED: must fail until PagoAprobado event exists and is dispatched in PagoService.
 */
it('dispatches PagoAprobado event when aprobarPago succeeds', function () {
    Event::fake([PagoAprobado::class]);

    $prestamo = Prestamo::factory()->create([
        'estado'          => Prestamo::ESTADO_ACTIVO,
        'cantidad_cuotas' => 4,
    ]);

    $cuotaGrupal = CuotasGrupales::create([
        'prestamo_id'         => $prestamo->id,
        'numero_cuota'        => 1,
        'monto_cuota_grupal'  => 100.00,
        'saldo_pendiente'     => 100.00,
        'fecha_vencimiento'   => now()->addDays(7)->toDateString(),
        'estado_cuota_grupal' => 'vigente',
        'estado_pago'         => 'pendiente',
    ]);

    $cliente = Cliente::factory()->create();
    PrestamoIndividual::factory()->create([
        'prestamo_id'                    => $prestamo->id,
        'cliente_id'                     => $cliente->id,
        'monto_prestado_individual'      => 100.00,
        'monto_cuota_prestamo_individual' => 25.00,
        'monto_devolver_individual'      => 110.00,
        'estado'                         => 'Activo',
    ]);
    CuotaIndividual::create([
        'prestamo_id'            => $prestamo->id,
        'cliente_id'             => $cliente->id,
        'numero_cuota'           => 1,
        'monto_capital_original' => 100.00,
        'monto_interes_original' => 10.00,
        'saldo_capital'          => 100.00,
        'saldo_interes'          => 10.00,
        'estado'                 => 'pendiente',
        'fecha_vencimiento'      => now()->addDays(7)->toDateString(),
    ]);

    $pago = Pago::create([
        'cuota_grupal_id'  => $cuotaGrupal->id,
        'tipo_pago'        => 'efectivo',
        'codigo_operacion' => 'TEST-PAGO-APROBADO',
        'monto_pagado'     => 100.00,
        'fecha_pago'       => now(),
        'estado_pago'      => 'pendiente',
    ]);

    app(PagoService::class)->aprobarPago($pago);

    Event::assertDispatched(PagoAprobado::class);
});
