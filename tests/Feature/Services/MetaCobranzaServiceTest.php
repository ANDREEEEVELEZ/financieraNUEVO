<?php

declare(strict_types=1);

use App\Models\AplicacionPago;
use App\Models\Asesor;
use App\Models\CuotaIndividual;
use App\Models\CuotasGrupales;
use App\Models\Grupo;
use App\Models\MetaMensual;
use App\Models\MetricaDiariaAsesor;
use App\Models\Pago;
use App\Models\Prestamo;
use App\Models\User;
use App\Domain\Cartera\MetaCobranzaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('returns correct shape with meta cobrado and pct keys', function () {
    $user    = User::factory()->create();
    $service = app(MetaCobranzaService::class);
    $result  = $service->calcular($user, Carbon::now());

    expect($result)->toHaveKeys(['meta', 'cobrado', 'pct']);
    expect($result['meta'])->toBeFloat();
    expect($result['cobrado'])->toBeFloat();
    expect($result['pct'])->toBeFloat();
});

it('uses snapshot when daily metrics exist for the month', function () {
    $user = User::factory()->create();

    MetricaDiariaAsesor::create([
        'asesor_id' => $user->id,
        'fecha'     => today()->toDateString(),
        'cobrado'   => 3000,
    ]);
    MetricaDiariaAsesor::create([
        'asesor_id' => $user->id,
        'fecha'     => today()->subDay()->toDateString(),
        'cobrado'   => 2000,
    ]);

    $result = app(MetaCobranzaService::class)->calcular($user, Carbon::now());

    expect($result['cobrado'])->toBe(5000.0);
});

it('defaults meta to 25000 when no MetaMensual exists', function () {
    $user    = User::factory()->create();
    $service = app(MetaCobranzaService::class);
    $result  = $service->calcular($user, Carbon::now());

    expect($result['meta'])->toBe(25000.0);
});

it('uses MetaMensual meta_monto when a record exists', function () {
    $user = User::factory()->create();

    MetaMensual::create([
        'asesor_id'   => $user->id,
        'anio'        => now()->year,
        'mes'         => now()->month,
        'meta_monto'  => 40000,
    ]);

    $result = app(MetaCobranzaService::class)->calcular($user, Carbon::now());

    expect($result['meta'])->toBe(40000.0);
});

it('computes pct correctly when meta and cobrado are known', function () {
    $user = User::factory()->create();

    MetaMensual::create([
        'asesor_id'  => $user->id,
        'anio'       => now()->year,
        'mes'        => now()->month,
        'meta_monto' => 20000,
    ]);
    MetricaDiariaAsesor::create([
        'asesor_id' => $user->id,
        'fecha'     => today()->toDateString(),
        'cobrado'   => 10000,
    ]);

    $result = app(MetaCobranzaService::class)->calcular($user, Carbon::now());

    expect($result['pct'])->toBe(50.0);
});

/**
 * SDD core-contable-seguridad Req 2 — this service's live-JOIN fallback path
 * (used when no MetricaDiariaAsesor snapshot exists yet for the month) is a
 * SEPARATE reader from ActualizarMetricasDiariasJob and was found unfiltered
 * during Slice C's apply-time grep sweep. Must exclude tipo_aplicacion
 * retanqueo rows from the recomputed "cobrado" total.
 */
it('fallback JOIN path excludes retanqueo-tipo aplicaciones from cobrado', function () {
    $user = User::factory()->create();
    $asesor = Asesor::factory()->create(['user_id' => $user->id]);
    $grupo = Grupo::factory()->create(['asesor_id' => $asesor->id]);
    $prestamo = Prestamo::factory()->create([
        'grupo_id' => $grupo->id,
        'estado' => Prestamo::ESTADO_ACTIVO,
    ]);
    $cuotaGrupal = CuotasGrupales::factory()->create(['prestamo_id' => $prestamo->id]);
    $cuotaIndividual = CuotaIndividual::factory()->create(['prestamo_id' => $prestamo->id]);

    $pagoCobranza = Pago::factory()->create([
        'cuota_grupal_id' => $cuotaGrupal->id,
        'origen_pago' => 'cobranza',
        'estado_pago' => 'aprobado',
        'fecha_pago' => now(),
    ]);
    AplicacionPago::create([
        'pago_id' => $pagoCobranza->id,
        'cuota_id' => $cuotaIndividual->id,
        'tipo_aplicacion' => 'cobranza',
        'monto_aplicado_capital' => 60.00,
        'monto_aplicado_interes' => 20.00,
        'monto_aplicado_mora' => 0.00,
        'fecha_aplicacion' => now(),
    ]);

    $pagoRetanqueo = Pago::factory()->create([
        'cuota_grupal_id' => $cuotaGrupal->id,
        'origen_pago' => 'retanqueo',
        'estado_pago' => 'aprobado',
        'fecha_pago' => now(),
    ]);
    AplicacionPago::create([
        'pago_id' => $pagoRetanqueo->id,
        'cuota_id' => $cuotaIndividual->id,
        'tipo_aplicacion' => 'retanqueo',
        'monto_aplicado_capital' => 40.00,
        'monto_aplicado_interes' => 0.00,
        'monto_aplicado_mora' => 0.00,
        'fecha_aplicacion' => now(),
    ]);

    // No MetricaDiariaAsesor snapshot rows exist for this month, so calcular()
    // falls through to the live-JOIN recompute path.
    $result = app(MetaCobranzaService::class)->calcular($user, Carbon::now());

    expect($result['cobrado'])->toBe(80.0);
});

it('caches result so second call skips database queries', function () {
    $user    = User::factory()->create();
    $service = app(MetaCobranzaService::class);

    // warm up cache
    $service->calcular($user, Carbon::now());

    DB::flushQueryLog();
    DB::enableQueryLog();

    $service->calcular($user, Carbon::now());

    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    expect(count($queries))->toBe(0);
});
