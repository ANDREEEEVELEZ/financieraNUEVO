<?php

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (['super_admin', 'Jefe de creditos', 'Jefe de operaciones', 'Asesor'] as $rol) {
        Role::firstOrCreate(['name' => $rol, 'guard_name' => 'web']);
    }

    // Register stub API routes to trigger specific exception types.
    Route::middleware(['auth:sanctum'])->prefix('api')->group(function () {
        Route::get('/test-401', fn () => abort(401));

        Route::get('/test-403', function () {
            throw new AuthorizationException('Forbidden.');
        });

        Route::get('/test-404-model', function () {
            throw (new ModelNotFoundException())->setModel(User::class, [999]);
        });

        Route::post('/test-422', function (\Illuminate\Http\Request $request) {
            $request->validate([
                'name' => ['required', 'string'],
                'email' => ['required', 'email'],
            ]);
            return response()->json(['ok' => true]);
        });
    });

    // Unauthenticated test route — requires auth:sanctum to throw AuthenticationException.
    Route::middleware(['auth:sanctum'])->prefix('api')->get('/test-unauthenticated', fn () => response()->json(['ok' => true]));
});

// ─────────────────────────────────────────────────────────
// 401 — Unauthenticated
// ─────────────────────────────────────────────────────────

it('returns 401 JSON envelope for unauthenticated requests to protected API routes', function () {
    $response = $this->getJson('/api/test-401');

    $response->assertStatus(401);
    $body = $response->json();

    expect($body['success'])->toBeFalse();
    expect($body)->toHaveKey('data');
    expect($body)->toHaveKey('message');
    expect($body)->toHaveKey('errors');
});

it('unauthenticated request has correct envelope shape', function () {
    $response = $this->getJson('/api/test-unauthenticated');

    $response->assertStatus(401);
    $body = $response->json();

    expect($body['success'])->toBeFalse();
    expect($body['data'])->toBeNull();
});

// ─────────────────────────────────────────────────────────
// 404 — Model not found
// ─────────────────────────────────────────────────────────

it('returns 404 JSON envelope when a model is not found', function () {
    $user = User::factory()->create(['active' => true]);
    Sanctum::actingAs($user, ['*']);

    $response = $this->getJson('/api/test-404-model');

    $response->assertNotFound();
    $body = $response->json();

    expect($body['success'])->toBeFalse();
    expect($body['message'])->toBe('Resource not found.');
    expect($body)->toHaveKey('data');
    expect($body)->toHaveKey('errors');
});

// ─────────────────────────────────────────────────────────
// 422 — Validation error
// ─────────────────────────────────────────────────────────

it('returns 422 JSON envelope with errors key on validation failure', function () {
    $user = User::factory()->create(['active' => true]);
    Sanctum::actingAs($user, ['*']);

    $response = $this->postJson('/api/test-422', []);

    $response->assertStatus(422);
    $body = $response->json();

    expect($body['success'])->toBeFalse();
    expect($body)->toHaveKey('errors');
    expect($body['errors'])->toHaveKey('name');
    expect($body['errors'])->toHaveKey('email');
});

it('validation error envelope message is correct', function () {
    $user = User::factory()->create(['active' => true]);
    Sanctum::actingAs($user, ['*']);

    $response = $this->postJson('/api/test-422', ['name' => 'John']);

    $response->assertStatus(422);
    $body = $response->json();

    expect($body['success'])->toBeFalse();
    expect($body['message'])->toBe('Validation failed.');
    expect($body['errors'])->toHaveKey('email');
    expect($body['errors'])->not->toHaveKey('name');
});
