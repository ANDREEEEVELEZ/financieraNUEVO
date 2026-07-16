<?php

use App\Http\Resources\Api\PagoResource;
use App\Models\CuotasGrupales;
use App\Models\Grupo;
use App\Models\Pago;
use App\Models\Prestamo;
use Illuminate\Http\Request;

uses(\Tests\TestCase::class, \Illuminate\Foundation\Testing\RefreshDatabase::class);

// ─── Helpers ────────────────────────────────────────────────────────────────

function pagoResourceMakePago(): Pago
{
    $grupo    = Grupo::factory()->create();
    $prestamo = Prestamo::factory()->create([
        'grupo_id' => $grupo->id,
        'estado'   => Prestamo::ESTADO_ACTIVO,
    ]);
    $cuota = CuotasGrupales::factory()->create(['prestamo_id' => $prestamo->id]);

    $id = \Illuminate\Support\Facades\DB::table('pagos')->insertGetId([
        'cuota_grupal_id'  => $cuota->id,
        'tipo_pago'        => 'efectivo',
        'codigo_operacion' => 'TEST-001',
        'monto_pagado'     => 150.00,
        'monto_mora_pagada' => 0,
        'fecha_pago'       => now()->toDateTimeString(),
        'estado_pago'      => 'pendiente',
        'observaciones'    => null,
        'created_at'       => now(),
        'updated_at'       => now(),
    ]);

    return Pago::findOrFail($id);
}

// ─── Tests ───────────────────────────────────────────────────────────────────

it('cuota_context is absent when cuotaGrupal is not loaded', function () {
    $pago    = pagoResourceMakePago();
    $request = Request::create('/');
    $data    = (new PagoResource($pago))->response($request)->getData(true)['data'];

    expect($data)->not->toHaveKey('cuota_context');
});

it('cuota_context is present with expected keys when cuotaGrupal is loaded', function () {
    $pago = pagoResourceMakePago();
    $pago->load('cuotaGrupal');
    $request = Request::create('/');
    $data    = (new PagoResource($pago))->response($request)->getData(true)['data'];

    expect($data)->toHaveKey('cuota_context');
    expect($data['cuota_context'])->toHaveKey('numero_cuota');
    expect($data['cuota_context'])->toHaveKey('fecha_vencimiento');
    expect($data['cuota_context'])->toHaveKey('prestamo_id');
    expect($data['cuota_context'])->toHaveKey('grupo_nombre');
});

it('existing top-level fields are present regardless of relation load state', function () {
    $pago    = pagoResourceMakePago();
    $request = Request::create('/');
    $data    = (new PagoResource($pago))->response($request)->getData(true)['data'];

    expect($data)->toHaveKey('id');
    expect($data)->toHaveKey('monto_pagado');
    expect($data)->toHaveKey('estado_pago');
    expect($data)->toHaveKey('tipo_pago');
    expect($data)->toHaveKey('cuota_grupal_id');
});

/**
 * Task 3.15 — expose origen_pago so the mobile client can distinguish
 * retanqueo-tipo records (Req 2 item 9). No server-side aggregate exists on
 * this resource/controller today, so this is exposure-only.
 */
it('exposes origen_pago so the client can distinguish retanqueo-tipo records', function () {
    $pago = pagoResourceMakePago();
    $pago->forceFill(['origen_pago' => 'retanqueo'])->save();

    $request = Request::create('/');
    $data    = (new PagoResource($pago->fresh()))->response($request)->getData(true)['data'];

    expect($data)->toHaveKey('origen_pago');
    expect($data['origen_pago'])->toBe('retanqueo');
});

it('defaults origen_pago to cobranza for cash payments', function () {
    $pago    = pagoResourceMakePago();
    $request = Request::create('/');
    $data    = (new PagoResource($pago))->response($request)->getData(true)['data'];

    expect($data['origen_pago'])->toBe('cobranza');
});
