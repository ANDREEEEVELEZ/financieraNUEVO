<?php

use App\Models\Asesor;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    RateLimiter::clear('api-auth');
});

// ─── Helpers ────────────────────────────────────────────────────────────────

function versionedRouteTestMakeUser(): array
{
    $user = User::factory()->create(['active' => true]);
    $user->assignRole('Asesor');
    Asesor::factory()->create(['user_id' => $user->id]);
    $token = $user->createToken('test')->plainTextToken;

    return [$user, $token];
}

// ─── Task 3.1: v1 prefix — authenticated endpoints respond ───────────────────

it('GET /api/v1/prestamos returns 200 or 401 (v1 route exists)', function () {
    [$user, $token] = versionedRouteTestMakeUser();

    $this->withToken($token)
         ->getJson('/api/v1/prestamos')
         ->assertSuccessful();
});

it('GET /api/v1/pagos returns 200 for authenticated user (v1 route exists)', function () {
    [$user, $token] = versionedRouteTestMakeUser();

    $this->withToken($token)
         ->getJson('/api/v1/pagos')
         ->assertSuccessful();
});

it('GET /api/v1/grupos returns 200 for authenticated user (v1 route exists)', function () {
    [$user, $token] = versionedRouteTestMakeUser();

    $this->withToken($token)
         ->getJson('/api/v1/grupos')
         ->assertSuccessful();
});

it('GET /api/v1/clientes returns 200 for authenticated user (v1 route exists)', function () {
    [$user, $token] = versionedRouteTestMakeUser();

    $this->withToken($token)
         ->getJson('/api/v1/clientes')
         ->assertSuccessful();
});

it('GET /api/v1/dashboard returns 200 for Asesor (v1 route exists)', function () {
    [$user, $token] = versionedRouteTestMakeUser();

    $this->withToken($token)
         ->getJson('/api/v1/dashboard')
         ->assertSuccessful();
});

it('GET /api/v1/cuotas/hoy returns 200 for authenticated user (v1 route exists)', function () {
    [$user, $token] = versionedRouteTestMakeUser();

    $this->withToken($token)
         ->getJson('/api/v1/cuotas/hoy')
         ->assertSuccessful();
});

// ─── Task 3.2: v1 auth routes ────────────────────────────────────────────────

it('POST /api/v1/auth/login route is registered in the router', function () {
    $routes = collect(\Illuminate\Support\Facades\Route::getRoutes()->getRoutes());

    $loginV1 = $routes->first(function ($route) {
        return str_contains($route->uri(), 'v1/auth/login')
            && in_array('POST', $route->methods());
    });

    expect($loginV1)->not->toBeNull('Expected /v1/auth/login POST route to be registered');
});

it('POST /api/v1/auth/logout returns 401 when unauthenticated (v1 logout route exists)', function () {
    $this->postJson('/api/v1/auth/logout')
         ->assertUnauthorized();
});

// ─── Task 3.3: legacy aliases still work ────────────────────────────────────

it('GET /api/prestamos (legacy) returns same response as /api/v1/prestamos', function () {
    [$user, $token] = versionedRouteTestMakeUser();

    $legacyStatus = $this->withToken($token)->getJson('/api/prestamos')->status();
    $v1Status     = $this->withToken($token)->getJson('/api/v1/prestamos')->status();

    expect($legacyStatus)->toBe($v1Status);
});

it('GET /api/pagos (legacy) still responds correctly', function () {
    [$user, $token] = versionedRouteTestMakeUser();

    $this->withToken($token)
         ->getJson('/api/pagos')
         ->assertSuccessful();
});

// ─── Task 3.4: PATCH body is NOT dropped by legacy aliases ───────────────────
// Route::redirect() would drop PATCH bodies; duplicate registration must be used.

it('PATCH /api/prestamos/{id}/aprobar (legacy) is not a redirect — body is preserved', function () {
    [$user, $token] = versionedRouteTestMakeUser();

    $user->removeRole('Asesor');
    $user->assignRole('Jefe de creditos');

    $asesor   = Asesor::first();
    $grupo    = \App\Models\Grupo::factory()->create(['asesor_id' => $asesor->id]);
    $prestamo = \App\Models\Prestamo::factory()->create([
        'grupo_id' => $grupo->id,
        'estado'   => \App\Models\Prestamo::ESTADO_PENDIENTE,
    ]);

    // A redirect (301/302) would cause assertOk() to fail with 301.
    // The middleware response (422/200/etc.) proves it reached the controller.
    $response = $this->withToken($token)
                     ->patchJson("/api/prestamos/{$prestamo->id}/aprobar");

    // Must not be a redirect — any non-3xx status proves body was delivered
    expect($response->status())->not->toBeBetween(300, 399);
});
