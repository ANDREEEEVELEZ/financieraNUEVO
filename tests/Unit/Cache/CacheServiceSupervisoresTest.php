<?php

use App\Contracts\CacheServiceInterface;
use App\Infrastructure\Cache\CacheService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
});

// ── T-1.1: getSupervisores() method exists on the interface ───────────────────

it('CacheServiceInterface declares getSupervisores() returning Collection', function () {
    $reflection = new ReflectionClass(CacheServiceInterface::class);

    expect($reflection->hasMethod('getSupervisores'))->toBeTrue(
        'CacheServiceInterface must declare getSupervisores()'
    );

    $method = $reflection->getMethod('getSupervisores');
    $returnType = $method->getReturnType();

    expect($returnType)->not->toBeNull('getSupervisores() must declare a return type');
    expect($returnType->getName())->toBe(\Illuminate\Support\Collection::class);
});

// ── T-1.1: CacheService implements getSupervisores() ─────────────────────────

it('CacheService implements getSupervisores()', function () {
    $reflection = new ReflectionClass(CacheService::class);

    expect($reflection->hasMethod('getSupervisores'))->toBeTrue(
        'CacheService must implement getSupervisores()'
    );
});

// ── T-1.1: getSupervisores() uses ec_supervisores as cache key ───────────────

it('getSupervisores() stores result in ec_supervisores key', function () {
    // Seed the cache manually — next call must return the cached sentinel without hitting DB
    Cache::put('ec_supervisores', collect(['cached_sentinel']), 300);

    $service = app(CacheServiceInterface::class);
    $result = $service->getSupervisores();

    // rememberWithLock early-return reads Cache::get first;
    // hitting the key means the implementation uses 'ec_supervisores' as the cache key
    expect($result)->toBeInstanceOf(Collection::class);
    expect($result->first())->toBe('cached_sentinel');
});

// ── Triangulation: getSupervisores() fetches users with supervisor roles ──────

it('getSupervisores() returns a Collection of users with supervisor roles', function () {
    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

    $user = \App\Models\User::factory()->create();
    $user->assignRole('super_admin');

    $service = app(CacheServiceInterface::class);
    $result = $service->getSupervisores();

    expect($result)->toBeInstanceOf(Collection::class);
    expect($result->pluck('id'))->toContain($user->id);
});

// ── Triangulation: getSupervisores() returns empty when no supervisor roles ───

it('getSupervisores() returns empty Collection when no supervisors exist', function () {
    // No users with supervisor roles seeded
    $service = app(CacheServiceInterface::class);
    $result = $service->getSupervisores();

    expect($result)->toBeInstanceOf(Collection::class);
    expect($result)->toHaveCount(0);
});
