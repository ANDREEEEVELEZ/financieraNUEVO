<?php

use App\Models\Prestamo;
use App\Models\PrestamoIndividual;
use App\Models\CuotasGrupales;
use App\Models\CuotaIndividual;
use App\Models\Cliente;
use App\Models\Pago;
use App\Models\AplicacionPago;
use App\Domain\Pagos\PagoService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * SR-1: PagoObserver Bug Fix — Double-Write Detection
 *
 * Approving a Pago via PagoService::aprobarPago() must produce
 * exactly N AplicacionPago records (one per CuotaIndividual participating
 * in this cuota grupal's numero_cuota).
 *
 * RED: This test MUST FAIL on current code because:
 *   - PagoObserver fires on pago.updated (estado→aprobado) and calls
 *     aplicarPagoACuotasIndividuales() which creates an AplicacionPago for
 *     the FIRST client's cuota using ALL of pago.monto_pagado (FIFO)
 *   - When the first client's cuota saldo > pago.monto_pagado, the cuota
 *     is NOT fully paid (estado stays 'pendiente')
 *   - PagoService::distribuirEnCuotasIndividuales() then ALSO creates an
 *     AplicacionPago for that same cuota (still 'pendiente')
 *   - Result: N+1 AplicacionPago records instead of N
 *
 * The fix is: remove aplicarPagoACuotasIndividuales() from PagoObserver.
 */
it('aprobarPago produces exactly N aplicacion_pago records — one per cuota individual', function () {
    // Arrange: first client has a very large debt (500 capital + 50 interest)
    // relative to clients 2 and 3 (50 capital + 5 interest each).
    // Payment monto = 200 = LESS than client 1's full saldo (550).
    // This ensures FIFO applies 200 to client 1 but leaves the cuota as 'pendiente'
    // (saldo_capital = 300, saldo_interes = 0 after partial payment).
    // Then the Service's distribuirEnCuotasIndividuales() also creates a record
    // for client 1's cuota → N+1 total.
    $n = 3;

    $prestamo = Prestamo::factory()->create([
        'estado'          => Prestamo::ESTADO_ACTIVO,
        'cantidad_cuotas' => 4,
    ]);

    $montoPago = 200.00; // Less than client 1's 550 debt but covers cuota grupal monto

    $cuotaGrupal = CuotasGrupales::create([
        'prestamo_id'         => $prestamo->id,
        'numero_cuota'        => 1,
        'monto_cuota_grupal'  => $montoPago,
        'fecha_vencimiento'   => now()->addDays(7)->toDateString(),
        'estado_cuota_grupal' => 'vigente',
        'estado_pago'         => 'pendiente',
    ]);

    // Client 1: large debt — FIFO won't fully pay it off with a 200 payment
    $cliente1 = Cliente::factory()->create();
    PrestamoIndividual::factory()->create([
        'prestamo_id'                    => $prestamo->id,
        'cliente_id'                     => $cliente1->id,
        'monto_prestado_individual'      => 500.00,
        'monto_cuota_prestamo_individual' => 125.00,
        'monto_devolver_individual'      => 550.00,
        'estado'                         => 'Activo',
    ]);
    CuotaIndividual::create([
        'prestamo_id'            => $prestamo->id,
        'cliente_id'             => $cliente1->id,
        'numero_cuota'           => 1,
        'monto_capital_original' => 500.00,
        'monto_interes_original' => 50.00,
        'saldo_capital'          => 500.00,
        'saldo_interes'          => 50.00,
        'estado'                 => 'pendiente',
        'fecha_vencimiento'      => now()->addDays(7)->toDateString(),
    ]);

    // Clients 2 and 3: small debt
    foreach ([2, 3] as $_) {
        $cliente = Cliente::factory()->create();
        PrestamoIndividual::factory()->create([
            'prestamo_id'                    => $prestamo->id,
            'cliente_id'                     => $cliente->id,
            'monto_prestado_individual'      => 50.00,
            'monto_cuota_prestamo_individual' => 12.50,
            'monto_devolver_individual'      => 55.00,
            'estado'                         => 'Activo',
        ]);
        CuotaIndividual::create([
            'prestamo_id'            => $prestamo->id,
            'cliente_id'             => $cliente->id,
            'numero_cuota'           => 1,
            'monto_capital_original' => 50.00,
            'monto_interes_original' => 5.00,
            'saldo_capital'          => 50.00,
            'saldo_interes'          => 5.00,
            'estado'                 => 'pendiente',
            'fecha_vencimiento'      => now()->addDays(7)->toDateString(),
        ]);
    }

    $pago = Pago::create([
        'cuota_grupal_id'  => $cuotaGrupal->id,
        'tipo_pago'        => 'parcial',
        'codigo_operacion' => 'TEST-DOUBLE-WRITE',
        'monto_pagado'     => $montoPago,
        'fecha_pago'       => now(),
        'estado_pago'      => 'pendiente',
    ]);

    expect(AplicacionPago::where('pago_id', $pago->id)->count())->toBe(0);

    // Act
    app(PagoService::class)->aprobarPago($pago);

    // Assert: exactly N AplicacionPago records — one per CuotaIndividual.
    // On buggy code: Observer creates 1 record for client 1's cuota (partial FIFO),
    // cuota stays 'pendiente', then Service also creates 1 for it → total N+1 = 4.
    $total = AplicacionPago::where('pago_id', $pago->id)->count();

    expect($total)->toBe($n);
});
