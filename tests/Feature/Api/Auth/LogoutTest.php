<?php

use App\Models\User;
use Laravel\Sanctum\Sanctum;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('returns 200 and revokes the token on logout', function () {
    $user = User::factory()->create(['active' => true]);
    $token = $user->createToken('test-device')->plainTextToken;

    $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
                     ->postJson('/api/auth/logout');

    $response->assertOk()
             ->assertJson(['success' => true]);

    // Reset Sanctum's in-memory guard cache so the next request re-resolves
    // the token from the database (where it is now deleted).
    $this->app['auth']->forgetGuards();

    // Subsequent request with the same token must be rejected.
    $this->withHeaders(['Authorization' => "Bearer {$token}"])
         ->postJson('/api/auth/logout')
         ->assertUnauthorized();
});

it('returns 401 when no token is provided', function () {
    $this->postJson('/api/auth/logout')
         ->assertUnauthorized();
});

it('revokes only the current token not all user tokens', function () {
    $user = User::factory()->create(['active' => true]);

    $tokenA = $user->createToken('device-a')->plainTextToken;
    $tokenB = $user->createToken('device-b')->plainTextToken;

    // Logout with token A.
    $this->withHeaders(['Authorization' => "Bearer {$tokenA}"])
         ->postJson('/api/auth/logout')
         ->assertOk();

    // Reset Sanctum's in-memory guard cache between requests.
    $this->app['auth']->forgetGuards();

    // Token A is now invalid.
    $this->withHeaders(['Authorization' => "Bearer {$tokenA}"])
         ->postJson('/api/auth/logout')
         ->assertUnauthorized();

    $this->app['auth']->forgetGuards();

    // Token B must still work — it logs out successfully (200).
    $this->withHeaders(['Authorization' => "Bearer {$tokenB}"])
         ->postJson('/api/auth/logout')
         ->assertOk();
});
