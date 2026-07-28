<?php

use App\Domain\Pagos\PagoService;
use App\Models\CuotasGrupales;
use App\Models\Ingreso;
use App\Models\Pago;
use App\Models\Prestamo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * Regresión — CRITICAL-2 (sdd/backend-completion-pagos-auth verify-report #278):
 * PagoService's guard clauses (aprobarPago/rechazarPago/revertirPago) compared
 * estado_pago with a strict `!==` against a lowercase literal. Historical rows
 * written before the Pago::setEstadoPagoAttribute() mutator existed may still
 * hold mixed-case values (e.g. 'Pendiente'), which silently short-circuited
 * approval: no Ingreso created, no PagoAprobado dispatched, and the caller
 * got back the untouched Pago with no error at all.
 */
function crearCuotaGrupalActiva(): CuotasGrupales
{
    $prestamo = Prestamo::factory()->create([
        'estado' => Prestamo::ESTADO_ACTIVO,
    ]);

    return CuotasGrupales::factory()->create([
        'prestamo_id' => $prestamo->id,
        'monto_cuota_grupal' => 100.00,
        'estado_cuota_grupal' => 'vigente',
        'estado_pago' => 'pendiente',
    ]);
}

it('normalizes estado_pago to lowercase on write', function () {
    $cuota = crearCuotaGrupalActiva();

    $pago = Pago::create([
        'cuota_grupal_id' => $cuota->id,
        'tipo_pago' => 'completo',
        'codigo_operacion' => 'TEST-CASING-001',
        'monto_pagado' => 100.00,
        'monto_mora_pagada' => 0,
        'fecha_pago' => now(),
        'estado_pago' => 'Pendiente',
    ]);

    expect($pago->fresh()->estado_pago)->toBe('pendiente');
});

it('approves a pago written with canonical lowercase pendiente', function () {
    $cuota = crearCuotaGrupalActiva();

    $pago = Pago::factory()->create([
        'cuota_grupal_id' => $cuota->id,
        'monto_pagado' => 100.00,
        'monto_mora_pagada' => 0,
        'estado_pago' => 'pendiente',
    ]);

    app(PagoService::class)->aprobarPago($pago);

    expect($pago->fresh()->estado_pago)->toBe('aprobado');
    expect(Ingreso::where('pago_id', $pago->id)->exists())->toBeTrue();
});

it('approves a pago written with legacy mixed-case Pendiente', function () {
    $cuota = crearCuotaGrupalActiva();

    // Raw insert bypasses Pago::setEstadoPagoAttribute(), simulating a row
    // written before the mutator existed (legacy mixed-case 'Pendiente').
    $pagoId = DB::table('pagos')->insertGetId([
        'cuota_grupal_id' => $cuota->id,
        'tipo_pago' => 'completo',
        'codigo_operacion' => 'TEST-CASING-LEGACY',
        'monto_pagado' => 100.00,
        'monto_mora_pagada' => 0,
        'fecha_pago' => now(),
        'estado_pago' => 'Pendiente',
        'observaciones' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $pago = Pago::find($pagoId);

    app(PagoService::class)->aprobarPago($pago);

    expect($pago->fresh()->estado_pago)->toBe('aprobado');
    expect(Ingreso::where('pago_id', $pago->id)->exists())->toBeTrue();
});

it('does not double-approve an already-approved pago', function () {
    $cuota = crearCuotaGrupalActiva();

    $pago = Pago::factory()->create([
        'cuota_grupal_id' => $cuota->id,
        'monto_pagado' => 100.00,
        'monto_mora_pagada' => 0,
        'estado_pago' => 'pendiente',
    ]);

    app(PagoService::class)->aprobarPago($pago);
    app(PagoService::class)->aprobarPago($pago->fresh());

    expect(Ingreso::where('pago_id', $pago->id)->count())->toBe(1);
});

it('rejects a pago written with legacy mixed-case Pendiente', function () {
    $cuota = crearCuotaGrupalActiva();

    $pagoId = DB::table('pagos')->insertGetId([
        'cuota_grupal_id' => $cuota->id,
        'tipo_pago' => 'completo',
        'codigo_operacion' => 'TEST-CASING-RECHAZO',
        'monto_pagado' => 100.00,
        'monto_mora_pagada' => 0,
        'fecha_pago' => now(),
        'estado_pago' => 'Pendiente',
        'observaciones' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $pago = Pago::find($pagoId);

    app(PagoService::class)->rechazarPago($pago);

    expect($pago->fresh()->estado_pago)->toBe('rechazado');
});

it('reverts a pago written with legacy mixed-case Aprobado', function () {
    $cuota = crearCuotaGrupalActiva();

    $pagoId = DB::table('pagos')->insertGetId([
        'cuota_grupal_id' => $cuota->id,
        'tipo_pago' => 'completo',
        'codigo_operacion' => 'TEST-CASING-REVERTIR',
        'monto_pagado' => 100.00,
        'monto_mora_pagada' => 0,
        'fecha_pago' => now(),
        'estado_pago' => 'Aprobado',
        'observaciones' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $pago = Pago::find($pagoId);

    $resultado = app(PagoService::class)->revertirPago($pago);

    expect($resultado)->toBeTrue();
    expect($pago->fresh()->estado_pago)->toBe('pendiente');
});
