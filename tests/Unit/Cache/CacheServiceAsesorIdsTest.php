<?php

use App\Contracts\CacheServiceInterface;
use App\Infrastructure\Cache\CacheService;
use App\Models\Asesor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
});

// ── T-1.4: invalidateDashboardCache(null) caches the asesor ID list ───────────

it('invalidateDashboardCache(null) reads asesor ids from ec_asesor_ids_all cache key on second call', function () {
    // Create two asesores so the pluck call has something to return
    \App\Models\Persona::factory()->count(2)->create()->each(function ($persona) {
        Asesor::factory()->create(['persona_id' => $persona->id]);
    });

    $service = app(CacheServiceInterface::class);

    // First call: cache miss — populates ec_asesor_ids_all
    $service->invalidateDashboardCache(null);

    // Verify the cache key is now populated
    expect(Cache::has('ec_asesor_ids_all'))->toBeTrue(
        'First invalidateDashboardCache(null) call must populate ec_asesor_ids_all'
    );
});

// ── Triangulation: second call reuses cached ids (no extra DB query) ──────────

it('invalidateDashboardCache(null) second call uses cached asesor ids without extra DB hit', function () {
    \App\Models\Persona::factory()->count(2)->create()->each(function ($persona) {
        Asesor::factory()->create(['persona_id' => $persona->id]);
    });

    $service = app(CacheServiceInterface::class);

    // Prime the cache
    $service->invalidateDashboardCache(null);

    // Track queries on the second call — should NOT include Asesor pluck
    $queries = [];
    DB::listen(function ($q) use (&$queries) {
        $queries[] = $q->sql;
    });

    $service->invalidateDashboardCache(null);

    $asesorQueries = array_filter($queries, fn($sql) => str_contains(strtolower($sql), 'asesores'));

    expect(count($asesorQueries))->toBe(0,
        'Second call must use cached asesor ids — no DB query on asesores table'
    );
});
