<?php

use App\Models\RefreshToken;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('revokes all tokens and refresh tokens across multiple devices for the same user', function () {
    $user = User::factory()->create(['active' => true]);
    $user->assignRole('super_admin');
    $otherUser = User::factory()->create(['active' => true]);
    $otherUser->assignRole('super_admin');

    $loginA = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email, 'password' => 'password', 'device_name' => 'device-a',
    ]);
    $this->app['auth']->forgetGuards();

    $loginB = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email, 'password' => 'password', 'device_name' => 'device-b',
    ]);
    $this->app['auth']->forgetGuards();

    $otherLogin = $this->postJson('/api/v1/auth/login', [
        'email' => $otherUser->email, 'password' => 'password', 'device_name' => 'other-device',
    ]);
    $this->app['auth']->forgetGuards();

    $accessTokenA = $loginA->json('data.access_token');
    $refreshTokenA = $loginA->json('data.refresh_token');
    $accessTokenB = $loginB->json('data.access_token');

    $response = $this->withToken($accessTokenA)->postJson('/api/v1/auth/logout-all');
    $response->assertOk()->assertJson(['success' => true]);

    $this->app['auth']->forgetGuards();

    // Both device tokens for $user must now be invalid.
    $this->withToken($accessTokenA)->getJson('/api/v1/dashboard')->assertUnauthorized();
    $this->app['auth']->forgetGuards();
    $this->withToken($accessTokenB)->getJson('/api/v1/dashboard')->assertUnauthorized();
    $this->app['auth']->forgetGuards();

    // The revoked refresh token cannot be used to get a new pair.
    $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $refreshTokenA])
         ->assertStatus(401);

    expect(RefreshToken::active()->where('user_id', $user->id)->count())->toBe(0);

    // The other user's session must be untouched.
    $otherAccessToken = $otherLogin->json('data.access_token');
    $this->withToken($otherAccessToken)->getJson('/api/v1/dashboard')->assertOk();
    expect(RefreshToken::active()->where('user_id', $otherUser->id)->count())->toBe(1);
});

it('returns 401 when calling logout-all without a token', function () {
    $this->postJson('/api/v1/auth/logout-all')->assertUnauthorized();
});
