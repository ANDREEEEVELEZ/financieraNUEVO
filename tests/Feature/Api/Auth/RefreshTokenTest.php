<?php

use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    RateLimiter::clear('api-auth');
});

function loginAndGetTokens(string $email = null): array
{
    $user = $email
        ? User::where('email', $email)->first()
        : User::factory()->create(['password' => bcrypt('password'), 'active' => true]);

    $response = test()->postJson('/api/v1/auth/login', [
        'email'       => $user->email,
        'password'    => 'password',
        'device_name' => 'test-device',
    ]);

    return [
        'user'          => $user,
        'access_token'  => $response->json('data.access_token'),
        'refresh_token' => $response->json('data.refresh_token'),
    ];
}

it('returns a new token pair when refreshing with a valid refresh token', function () {
    $tokens = loginAndGetTokens();

    $response = $this->postJson('/api/v1/auth/refresh', [
        'refresh_token' => $tokens['refresh_token'],
    ]);

    $response->assertOk()
             ->assertJsonStructure([
                 'success',
                 'data' => ['access_token', 'refresh_token', 'token_type', 'expires_in', 'user'],
             ]);

    expect($response->json('data.access_token'))->not->toBe($tokens['access_token']);
    expect($response->json('data.refresh_token'))->not->toBe($tokens['refresh_token']);
});

it('invalidates the old access token after a refresh', function () {
    $tokens = loginAndGetTokens();

    $this->postJson('/api/v1/auth/refresh', [
        'refresh_token' => $tokens['refresh_token'],
    ])->assertOk();

    $this->app['auth']->forgetGuards();

    $this->withToken($tokens['access_token'])
         ->getJson('/api/v1/dashboard')
         ->assertUnauthorized();
});

it('rejects reuse of an already-rotated refresh token', function () {
    $tokens = loginAndGetTokens();

    $this->postJson('/api/v1/auth/refresh', [
        'refresh_token' => $tokens['refresh_token'],
    ])->assertOk();

    // Reusing the same (now-revoked) refresh token must fail.
    $this->postJson('/api/v1/auth/refresh', [
        'refresh_token' => $tokens['refresh_token'],
    ])->assertStatus(401);
});

it('rejects an expired refresh token', function () {
    $tokens = loginAndGetTokens();

    RefreshToken::where('user_id', $tokens['user']->id)->update([
        'expires_at' => now()->subDay(),
    ]);

    $this->postJson('/api/v1/auth/refresh', [
        'refresh_token' => $tokens['refresh_token'],
    ])->assertStatus(401);
});

it('rejects a revoked refresh token', function () {
    $tokens = loginAndGetTokens();

    RefreshToken::where('user_id', $tokens['user']->id)->update([
        'revoked_at' => now(),
    ]);

    $this->postJson('/api/v1/auth/refresh', [
        'refresh_token' => $tokens['refresh_token'],
    ])->assertStatus(401);
});

it('rejects a garbage refresh token', function () {
    $this->postJson('/api/v1/auth/refresh', [
        'refresh_token' => 'not-a-real-token',
    ])->assertStatus(401);
});

it('requires refresh_token field', function () {
    $this->postJson('/api/v1/auth/refresh', [])
         ->assertUnprocessable();
});

it('returns 429 after exceeding the refresh rate limit', function () {
    $tokens = loginAndGetTokens();

    for ($i = 0; $i < 10; $i++) {
        $this->postJson('/api/v1/auth/refresh', ['refresh_token' => 'garbage']);
    }

    $this->postJson('/api/v1/auth/refresh', ['refresh_token' => 'garbage'])
         ->assertStatus(429);
});
