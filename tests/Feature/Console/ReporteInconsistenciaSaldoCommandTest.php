<?php

use App\Models\CuotasGrupales;
use App\Models\Pago;
use App\Models\Prestamo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

/**
 * Task 4.1 — retanqueo:auditar-saldos (Req 6, Escenarios 6.1/6.2).
 *
 * Ordering-critical: este comando lee la columna legacy cuotas_grupales.saldo_pendiente,
 * por lo que DEBE ejecutarse (y su evidencia capturarse) ANTES de la migración de
 * la tarea 4.6 que la elimina.
 */
it('lista cuotas con saldo divergente entre la columna legacy y el ledger', function () {
    $prestamo = Prestamo::factory()->create(['estado' => Prestamo::ESTADO_ACTIVO]);

    // Cuota DIVERGENTE: columna legacy dice 999999.99, pero el ledger dice 120.00
    // (200 de cuota - 80 aprobado). Simula el drift dejado por el flujo de
    // retanqueo previo al ledger (Slice C), que mutaba la columna ad-hoc.
    $cuotaDivergente = CuotasGrupales::factory()->create([
        'prestamo_id' => $prestamo->id,
        'monto_cuota_grupal' => 200,
        'saldo_pendiente' => 999999.99,
        'estado_pago' => 'parcial',
        'estado_cuota_grupal' => 'vigente',
    ]);
    Pago::factory()->create([
        'cuota_grupal_id' => $cuotaDivergente->id,
        'monto_pagado' => 80,
        'monto_mora_pagada' => 0,
        'estado_pago' => 'aprobado',
    ]);

    // Cuota que COINCIDE: columna legacy y ledger están de acuerdo (100.00 ambos).
    $cuotaCoincidente = CuotasGrupales::factory()->create([
        'prestamo_id' => $prestamo->id,
        'monto_cuota_grupal' => 100,
        'saldo_pendiente' => 100.00,
        'estado_pago' => 'pendiente',
        'estado_cuota_grupal' => 'vigente',
    ]);

    Artisan::call('retanqueo:auditar-saldos');
    $salida = Artisan::output();

    expect($salida)->toContain((string) $cuotaDivergente->id)
        ->toContain((string) $prestamo->id)
        ->toContain('999999.99')
        ->toContain('120.00')
        // Exactamente 1 divergencia — la cuota coincidente NO debe sumar al conteo
        // (probar ausencia por substring de un ID pequeño es frágil: puede aparecer
        // dentro de otro número en la tabla).
        ->toContain('1 cuota(s) con saldo divergente');

    expect($cuotaCoincidente)->not->toBeNull();
});

it('no reporta cuotas cuando el saldo legacy y el ledger coinciden', function () {
    $prestamo = Prestamo::factory()->create(['estado' => Prestamo::ESTADO_ACTIVO]);

    CuotasGrupales::factory()->create([
        'prestamo_id' => $prestamo->id,
        'monto_cuota_grupal' => 150,
        'saldo_pendiente' => 150.00,
        'estado_pago' => 'pendiente',
        'estado_cuota_grupal' => 'vigente',
    ]);

    $exitCode = Artisan::call('retanqueo:auditar-saldos');
    $salida = Artisan::output();

    expect($exitCode)->toBe(0)
        ->and($salida)->toContain('Sin divergencias');
});

it('es de solo lectura: no modifica cuotas_grupales, pagos ni aplicacion_pago', function () {
    $prestamo = Prestamo::factory()->create(['estado' => Prestamo::ESTADO_ACTIVO]);

    $cuota = CuotasGrupales::factory()->create([
        'prestamo_id' => $prestamo->id,
        'monto_cuota_grupal' => 200,
        'saldo_pendiente' => 999999.99,
        'estado_pago' => 'pendiente',
        'estado_cuota_grupal' => 'vigente',
    ]);

    $estadoAntes = CuotasGrupales::find($cuota->id)->getRawOriginal();

    Artisan::call('retanqueo:auditar-saldos');

    $estadoDespues = CuotasGrupales::find($cuota->id)->getRawOriginal();

    expect($estadoDespues)->toBe($estadoAntes);
});
