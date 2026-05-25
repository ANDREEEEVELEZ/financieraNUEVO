<?php

declare(strict_types=1);

use App\Models\MetaMensual;
use App\Models\MetricaDiariaAsesor;
use App\Models\User;
use App\Services\MetaCobranzaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class, RefreshDatabase::class);

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
