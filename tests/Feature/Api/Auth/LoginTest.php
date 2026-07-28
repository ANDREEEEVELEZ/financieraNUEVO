<?php

use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    RateLimiter::clear('api-auth');
});

it('returns 200 with token and user roles on valid credentials', function () {
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
                     'token',
                     'user' => ['id', 'name', 'email', 'roles'],
                 ],
             ])
             ->assertJson(['success' => true]);

    expect($response->json('data.user.roles'))->toBeArray();
    expect($response->json('data.token'))->not->toBeEmpty();
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

it('issued token authenticates a subsequent auth:sanctum request', function () {
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

    $token = $login->json('data.token');

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
