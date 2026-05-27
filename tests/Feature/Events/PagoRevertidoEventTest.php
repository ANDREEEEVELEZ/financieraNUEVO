<?php

use App\Events\Domain\PagoRevertido;
use App\Models\AplicacionPago;
use App\Models\Cliente;
use App\Models\CuotaIndividual;
use App\Models\CuotasGrupales;
use App\Models\Ingreso;
use App\Models\Pago;
use App\Models\PrestamoIndividual;
use App\Models\Prestamo;
use App\Services\PagoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

/**
 * PR3-T2: PagoRevertido domain event dispatched on revertirPago().
 *
 * RED: must fail until PagoRevertido event exists and is dispatched in PagoService.
 */
it('dispatches PagoRevertido event when revertirPago succeeds', function () {
    Event::fake([PagoRevertido::class]);

    $prestamo = Prestamo::factory()->create([
        'estado'          => Prestamo::ESTADO_ACTIVO,
        'cantidad_cuotas' => 4,
    ]);

    $cuotaGrupal = CuotasGrupales::create([
        'prestamo_id'         => $prestamo->id,
        'numero_cuota'        => 1,
        'monto_cuota_grupal'  => 100.00,
        'saldo_pendiente'     => 0.00,
        'fecha_vencimiento'   => now()->addDays(7)->toDateString(),
        'estado_cuota_grupal' => 'cancelada',
        'estado_pago'         => 'pagado',
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
    $cuotaInd = CuotaIndividual::create([
        'prestamo_id'            => $prestamo->id,
        'cliente_id'             => $cliente->id,
        'numero_cuota'           => 1,
        'monto_capital_original' => 100.00,
        'monto_interes_original' => 10.00,
        'saldo_capital'          => 0.00,
        'saldo_interes'          => 0.00,
        'estado'                 => 'pagada',
        'fecha_vencimiento'      => now()->addDays(7)->toDateString(),
    ]);

    // Create a pago already in 'aprobado' state
    $pago = Pago::create([
        'cuota_grupal_id'   => $cuotaGrupal->id,
        'tipo_pago'         => 'efectivo',
        'codigo_operacion'  => 'TEST-PAGO-REVERTIDO',
        'monto_pagado'      => 100.00,
        'monto_mora_pagada' => 0,
        'fecha_pago'        => now(),
        'estado_pago'       => 'aprobado',
    ]);

    AplicacionPago::create([
        'pago_id'                => $pago->id,
        'cuota_id'               => $cuotaInd->id,
        'monto_aplicado_capital' => 90.00,
        'monto_aplicado_interes' => 10.00,
        'monto_aplicado_mora'    => 0.00,
        'fecha_aplicacion'       => now(),
    ]);

    Ingreso::create([
        'tipo_ingreso' => 'pago de cuota de grupo',
        'pago_id'      => $pago->id,
        'grupo_id'     => $prestamo->grupo_id ?? null,
        'fecha_hora'   => now(),
        'descripcion'  => 'PAGO APROBADO CUOTA #1',
        'monto'        => 100.00,
    ]);

    app(PagoService::class)->revertirPago($pago);

    Event::assertDispatched(PagoRevertido::class);
});
