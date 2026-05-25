<?php

declare(strict_types=1);

use App\Jobs\ActualizarMetricasDiariasJob;
use App\Models\Asesor;
use App\Models\MetricaDiariaAsesor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(Tests\TestCase::class, RefreshDatabase::class);

it('creates a metricas_diarias_asesor row for each asesor user', function () {
    $user   = User::factory()->create();
    $asesor = Asesor::factory()->create(['user_id' => $user->id]);

    (new ActualizarMetricasDiariasJob())->handle();

    expect(MetricaDiariaAsesor::where('asesor_id', $user->id)->count())->toBe(1);
});

it('is idempotent — re-running produces exactly one row per asesor per day', function () {
    $user   = User::factory()->create();
    $asesor = Asesor::factory()->create(['user_id' => $user->id]);

    (new ActualizarMetricasDiariasJob())->handle();
    (new ActualizarMetricasDiariasJob())->handle();

    expect(MetricaDiariaAsesor::where('asesor_id', $user->id)->where('fecha', today())->count())->toBe(1);
});

it('forgets meta_cobranza and cartera_resumen cache keys after write', function () {
    $user   = User::factory()->create();
    $asesor = Asesor::factory()->create(['user_id' => $user->id]);

    $cacheKey1 = "meta_cobranza:{$user->id}:" . now()->format('Y-m');
    $cacheKey2 = "cartera_resumen:{$user->id}";

    Cache::put($cacheKey1, ['meta' => 1.0, 'cobrado' => 1.0, 'pct' => 1.0], 300);
    Cache::put($cacheKey2, ['grupos_count' => 1], 300);

    (new ActualizarMetricasDiariasJob())->handle();

    expect(Cache::has($cacheKey1))->toBeFalse();
    expect(Cache::has($cacheKey2))->toBeFalse();
});
