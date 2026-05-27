<?php

use App\Models\Asesor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (['super_admin', 'Jefe de creditos', 'Jefe de operaciones', 'Asesor'] as $rol) {
        Role::firstOrCreate(['name' => $rol, 'guard_name' => 'web']);
    }

    // Register a protected test route for token-based requests.
    Route::middleware(['auth:sanctum', \App\Http\Middleware\CheckUserActive::class])
        ->get('/test-api-active', fn () => response()->json(['ok' => true]));
});

// ─────────────────────────────────────────────────────────
// Token (stateless / API) scenarios
// ─────────────────────────────────────────────────────────

it('active user with token passes through (200)', function () {
    $user = User::factory()->create(['active' => true]);
    $user->assignRole('Asesor');

    Sanctum::actingAs($user, ['*']);

    $this->getJson('/test-api-active')->assertOk();
});

it('inactive user with token returns 403 JSON envelope without session crash', function () {
    $user = User::factory()->create(['active' => false]);
    $user->assignRole('Asesor');

    Sanctum::actingAs($user, ['*']);

    $response = $this->getJson('/test-api-active')->assertForbidden();

    $body = $response->json();
    expect($body['success'])->toBeFalse();
    expect($body['message'])->toBe('Account deactivated.');
    expect($body)->toHaveKey('data');
    expect($body)->toHaveKey('errors');
});

it('inactive asesor (estado_asesor=inactivo) with token returns 403 JSON', function () {
    $user   = User::factory()->create(['active' => true]);
    $asesor = Asesor::factory()->create(['user_id' => $user->id, 'estado_asesor' => 'inactivo']);
    $user->assignRole('Asesor');

    // Seed the cache as CacheService does.
    Cache::put('ec_asesor_user_' . $user->id, $asesor, 300);

    Sanctum::actingAs($user, ['*']);

    $response = $this->getJson('/test-api-active')->assertForbidden();

    $body = $response->json();
    expect($body['success'])->toBeFalse();
    expect($body['message'])->toBe('Account deactivated.');
});

// ─────────────────────────────────────────────────────────
// Web (session-based) scenarios
// ─────────────────────────────────────────────────────────

it('active user on web request passes through', function () {
    $user = User::factory()->create(['active' => true]);
    $user->assignRole('Asesor');

    // The existing /dashboard route is protected via web middleware.
    $response = $this->actingAs($user)->get('/dashboard');

    expect($response->headers->get('Location'))->not->toBe('/dashboard/login');
});

it('inactive user on web request redirects to login', function () {
    $user = User::factory()->create(['active' => false]);
    $user->assignRole('Asesor');

    $this->actingAs($user)->get('/dashboard')->assertRedirect('/dashboard/login');
    $this->assertGuest();
});
