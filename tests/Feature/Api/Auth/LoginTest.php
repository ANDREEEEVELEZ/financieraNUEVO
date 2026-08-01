<?php

use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\PersonalAccessToken;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    RateLimiter::clear('api-auth');
});

it('returns 200 with an access/refresh token pair and user roles on valid credentials', function () {
    $user = User::factory()->create([
        'password' => bcrypt('password'),
        'active'   => true,
    ]);

    $response = $this->postJson('/api/auth/login', [
        'email'       => $user->email,
        'password'    => 'password',
        'device_name' => 'test-device',
    ]);

    $response->assertOk()
             ->assertJsonStructure([
                 'success',
                 'data' => [
                     'access_token',
                     'refresh_token',
                     'token_type',
                     'expires_in',
                     'user' => ['id', 'name', 'email', 'roles'],
                 ],
             ])
             ->assertJson(['success' => true]);

    expect($response->json('data.user.roles'))->toBeArray();
    expect($response->json('data.access_token'))->not->toBeEmpty();
    expect($response->json('data.refresh_token'))->not->toBeEmpty();
    expect($response->json('data.token_type'))->toBe('Bearer');
});

it('returns 401 when password is wrong', function () {
    $user = User::factory()->create([
        'password' => bcrypt('correct-password'),
        'active'   => true,
    ]);

    $response = $this->postJson('/api/auth/login', [
        'email'       => $user->email,
        'password'    => 'wrong-password',
        'device_name' => 'test-device',
    ]);

    $response->assertStatus(401)
             ->assertJson(['success' => false]);
});

it('returns 403 when user is inactive', function () {
    $user = User::factory()->create([
        'password' => bcrypt('password'),
        'active'   => false,
    ]);

    $response = $this->postJson('/api/auth/login', [
        'email'       => $user->email,
        'password'    => 'password',
        'device_name' => 'test-device',
    ]);

    $response->assertForbidden()
             ->assertJson(['success' => false]);
});

it('returns 422 when required fields are missing', function () {
    $response = $this->postJson('/api/auth/login', []);

    $response->assertUnprocessable()
             ->assertJson(['success' => false])
             ->assertJsonStructure(['errors']);
});

it('issued access token authenticates a subsequent auth:sanctum request', function () {
    $user = User::factory()->create([
        'password' => bcrypt('password'),
        'active'   => true,
    ]);
    $user->assignRole('super_admin');

    $login = $this->postJson('/api/auth/login', [
        'email'       => $user->email,
        'password'    => 'password',
        'device_name' => 'test-device',
    ]);

    $token = $login->json('data.access_token');

    $response = $this->withToken($token)->getJson('/api/v1/dashboard');

    $response->assertOk()->assertJson(['success' => true]);
});

it('returns 429 after exceeding rate limit', function () {
    $user = User::factory()->create([
        'password' => bcrypt('password'),
        'active'   => true,
    ]);

    // The api-auth limiter is 10/min. Send 10 valid requests to exhaust the bucket.
    for ($i = 0; $i < 10; $i++) {
        $this->postJson('/api/auth/login', [
            'email'       => $user->email,
            'password'    => 'password',
            'device_name' => 'test-device',
        ]);
    }

    // 11th request must be throttled.
    $response = $this->postJson('/api/auth/login', [
        'email'       => $user->email,
        'password'    => 'password',
        'device_name' => 'test-device',
    ]);

    $response->assertStatus(429);
});

it('issues an access token with an approximately 2 hour TTL (Scenario 1.1.b)', function () {
    $user = User::factory()->create([
        'password' => bcrypt('password'),
        'active'   => true,
    ]);

    $response = $this->postJson('/api/auth/login', [
        'email'       => $user->email,
        'password'    => 'password',
        'device_name' => 'test-device',
    ]);

    $expectedTtlMinutes = (int) config('api_auth.access_token_ttl_minutes');

    expect($response->json('data.expires_in'))->toBe($expectedTtlMinutes * 60);

    $accessToken = PersonalAccessToken::findToken($response->json('data.access_token'));

    expect($accessToken)->not->toBeNull();
    expect($accessToken->expires_at)->not->toBeNull();
    expect($accessToken->expires_at->diffInSeconds(now()->addMinutes($expectedTtlMinutes), false))
        ->toBeGreaterThan(-5)->toBeLessThan(5);
});

it('issues a refresh token with an approximately 30 day TTL (Scenario 1.1.c)', function () {
    $user = User::factory()->create([
        'password' => bcrypt('password'),
        'active'   => true,
    ]);

    $this->postJson('/api/auth/login', [
        'email'       => $user->email,
        'password'    => 'password',
        'device_name' => 'test-device',
    ])->assertOk();

    $refreshToken = RefreshToken::where('user_id', $user->id)->firstOrFail();

    $expectedTtlDays = (int) config('api_auth.refresh_token_ttl_days');

    expect($refreshToken->expires_at->diffInSeconds(now()->addDays($expectedTtlDays), false))
        ->toBeGreaterThan(-5)->toBeLessThan(5);
});

it('never returns expires_in as zero even though sanctum.expiration is null (Scenario 1.1.d)', function () {
    expect(config('sanctum.expiration'))->toBeNull();

    $user = User::factory()->create([
        'password' => bcrypt('password'),
        'active'   => true,
    ]);

    $response = $this->postJson('/api/auth/login', [
        'email'       => $user->email,
        'password'    => 'password',
        'device_name' => 'test-device',
    ]);

    expect($response->json('data.expires_in'))->not->toBe(0);
    expect($response->json('data.expires_in'))->toBeGreaterThan(0);
});
