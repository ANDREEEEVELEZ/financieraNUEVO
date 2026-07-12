<?php

declare(strict_types=1);

use App\Models\CuotasGrupales;
use App\Models\Grupo;
use App\Models\Pago;
use App\Models\Prestamo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class, RefreshDatabase::class);

/**
 * Creates a Pago bypassing the boot() FSM guard by inserting at DB level.
 * The guard checks cuotaGrupal->prestamo->estado is in ESTADOS_ACTIVOS.
 */
function makePagoWithEstado(string $estadoPago): \App\Models\Pago
{
    $grupo = Grupo::factory()->create();
    $prestamo = Prestamo::factory()->create(['grupo_id' => $grupo->id, 'estado' => 'Activo']);

    $cuotaGrupal = CuotasGrupales::factory()->create(['prestamo_id' => $prestamo->id]);

    $id = DB::table('pagos')->insertGetId([
        'cuota_grupal_id' => $cuotaGrupal->id,
        'tipo_pago' => 'completo',
        'codigo_operacion' => strtoupper(fake()->uuid()),
        'monto_pagado' => 150.00,
        'fecha_pago' => now(),
        'estado_pago' => $estadoPago,
        'observaciones' => 'TEST',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return Pago::findOrFail($id);
}

it('scopePendientes returns only pagos with estado_pago pendiente', function () {
    $pendiente = makePagoWithEstado('pendiente');
    $aprobado = makePagoWithEstado('aprobado');

    $result = Pago::pendientes()->pluck('id');

    expect($result)->toContain($pendiente->id)->not->toContain($aprobado->id);
});

it('scopeAprobados returns only pagos with estado_pago aprobado', function () {
    $pendiente = makePagoWithEstado('pendiente');
    $aprobado = makePagoWithEstado('aprobado');

    $result = Pago::aprobados()->pluck('id');

    expect($result)->toContain($aprobado->id)->not->toContain($pendiente->id);
});

/**
 * Task 3.3 — Pago::scopeCobranza() / scopeDeRetanqueo() (Req 2, decision D1).
 */
function makePagoWithOrigen(string $origenPago): \App\Models\Pago
{
    $grupo = Grupo::factory()->create();
    $prestamo = Prestamo::factory()->create(['grupo_id' => $grupo->id, 'estado' => 'Activo']);

    $cuotaGrupal = CuotasGrupales::factory()->create(['prestamo_id' => $prestamo->id]);

    $id = DB::table('pagos')->insertGetId([
        'cuota_grupal_id' => $cuotaGrupal->id,
        'origen_pago' => $origenPago,
        'tipo_pago' => 'completo',
        'codigo_operacion' => strtoupper(fake()->uuid()),
        'monto_pagado' => 150.00,
        'fecha_pago' => now(),
        'estado_pago' => 'aprobado',
        'observaciones' => 'TEST',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return Pago::findOrFail($id);
}

it('scopeCobranza returns only pagos with origen_pago cobranza', function () {
    $cobranza = makePagoWithOrigen('cobranza');
    $retanqueo = makePagoWithOrigen('retanqueo');

    $result = Pago::cobranza()->pluck('id');

    expect($result)->toContain($cobranza->id)->not->toContain($retanqueo->id);
});

it('scopeDeRetanqueo returns only pagos with origen_pago retanqueo', function () {
    $cobranza = makePagoWithOrigen('cobranza');
    $retanqueo = makePagoWithOrigen('retanqueo');

    $result = Pago::deRetanqueo()->pluck('id');

    expect($result)->toContain($retanqueo->id)->not->toContain($cobranza->id);
});

it('existing pagos default to origen_pago cobranza (forward-only backfill safety)', function () {
    $pago = makePagoWithEstado('aprobado');

    expect($pago->fresh()->origen_pago)->toBe('cobranza');
});
