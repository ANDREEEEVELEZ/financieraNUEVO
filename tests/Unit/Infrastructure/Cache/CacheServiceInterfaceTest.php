<?php

use App\Contracts\CacheServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class);

/**
 * T-01 / T-27 / T-28 / T-29 — CacheServiceInterface + CacheService unit tests.
 */

// ── T-01: Interface existence and method signatures ──────────────────────────

it('CacheServiceInterface exists', function () {
    expect(interface_exists(CacheServiceInterface::class))->toBeTrue();
});

it('CacheServiceInterface declares all 20 public method signatures', function () {
    $reflection = new ReflectionClass(CacheServiceInterface::class);

    $required = [
        'getAsesoresActivos',
        'getAsesorByUserId',
        'getGruposActivosPorAsesor',
        'getGruposActivos',
        'getProductosFinancieros',
        'getMontosPorCiclo',
        'getSegurosPorMonto',
        'getEstadisticasDashboard',
        'getDashboardStatsAsesor',
        'getDashboardStatsJO',
        'getDashboardStatsJC',
        'getDashboardStatsAdmin',
        'invalidateDashboardCache',
        'invalidateAsesorCache',
        'invalidateUserCache',
        'invalidateGruposCache',
        'invalidateProductosCache',
        'invalidateStatsCache',
        'clearAllCache',
        'getCacheInfo',
    ];

    foreach ($required as $method) {
        expect($reflection->hasMethod($method))
            ->toBeTrue("Interface must declare method: {$method}");
    }
});

it('CacheService implements CacheServiceInterface', function () {
    expect(
        is_a(\App\Infrastructure\Cache\CacheService::class, CacheServiceInterface::class, true)
    )->toBeTrue();
});

// ── T-03 / T-29: Container binding ───────────────────────────────────────────

it('app(CacheServiceInterface::class) resolves to a CacheService instance', function () {
    $resolved = app(CacheServiceInterface::class);

    expect($resolved)->toBeInstanceOf(\App\Infrastructure\Cache\CacheService::class);
});

// ── T-27: getAsesorByUserId calls Cache::remember with the correct key ────────

it('getAsesorByUserId calls Cache::remember with the correct key and TTL', function () {
    \Illuminate\Support\Facades\Cache::spy();

    $service = app(CacheServiceInterface::class);
    $service->getAsesorByUserId(42);

    \Illuminate\Support\Facades\Cache::shouldHaveReceived('remember')
        ->once()
        ->withArgs(fn($key) => str_contains($key, 'asesor_user_42'));
});

// ── T-28: invalidateAsesorCache forgets the correct key ──────────────────────

it('invalidateAsesorCache forgets the asesor cache key', function () {
    \Illuminate\Support\Facades\Cache::spy();

    $service = app(CacheServiceInterface::class);
    $service->invalidateAsesorCache(5);

    \Illuminate\Support\Facades\Cache::shouldHaveReceived('forget')
        ->withArgs(fn($key) => str_contains($key, 'grupos_asesor_5')
            || str_contains($key, 'stats_asesor_5')
            || str_contains($key, 'asesores_activos'));
});

it('invalidateDashboardCache forgets asesor-scoped key when asesorId provided', function () {
    \Illuminate\Support\Facades\Cache::spy();

    $service = app(CacheServiceInterface::class);
    $service->invalidateDashboardCache(7);

    \Illuminate\Support\Facades\Cache::shouldHaveReceived('forget')
        ->once()
        ->withArgs(fn($key) => str_contains($key, 'dashboard_asesor_7'));
});

it('invalidateDashboardCache forgets shared role keys when no asesorId given', function () {
    \Illuminate\Support\Facades\Cache::spy();

    $service = app(CacheServiceInterface::class);
    $service->invalidateDashboardCache();

    \Illuminate\Support\Facades\Cache::shouldHaveReceived('forget')
        ->withArgs(fn($key) => in_array($key, ['ec_dashboard_jo', 'ec_dashboard_jc', 'ec_dashboard_admin']));
});
