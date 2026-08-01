<?php

use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use App\Notifications\ApiPasswordResetNotification;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    RateLimiter::clear('password-reset');
});

it('returns the same generic success message whether the email exists or not', function () {
    Notification::fake();

    $user = User::factory()->create(['active' => true]);

    $existing = $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email]);
    $nonExisting = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'nobody@example.com']);

    $existing->assertOk()->assertJson([
        'success' => true,
        'message' => 'If that email exists, a reset code was sent.',
    ]);

    $nonExisting->assertOk()->assertJson([
        'success' => true,
        'message' => 'If that email exists, a reset code was sent.',
    ]);

    expect($existing->json())->toBe($nonExisting->json());

    Notification::assertSentTo($user, ApiPasswordResetNotification::class);
});

it('validates the email field on forgot-password', function () {
    $this->postJson('/api/v1/auth/forgot-password', [])->assertUnprocessable();
});

it('resets the password with a valid token and invalidates prior sessions', function () {
    $user = User::factory()->create([
        'password' => bcrypt('old-password'),
        'active'   => true,
    ]);

    $oldAccessToken = $user->createToken('old-device')->plainTextToken;

    $token = Password::createToken($user);

    $response = $this->postJson('/api/v1/auth/reset-password', [
        'email'                 => $user->email,
        'token'                 => $token,
        'password'              => 'New-Strong-Password9',
        'password_confirmation' => 'New-Strong-Password9',
    ]);

    $response->assertOk()->assertJson(['success' => true]);

    $user->refresh();
    expect(\Illuminate\Support\Facades\Hash::check('New-Strong-Password9', $user->password))->toBeTrue();

    $this->app['auth']->forgetGuards();

    $this->withToken($oldAccessToken)
         ->getJson('/api/v1/dashboard')
         ->assertUnauthorized();
});

it('rejects an invalid or garbage reset token', function () {
    $user = User::factory()->create(['active' => true]);

    $response = $this->postJson('/api/v1/auth/reset-password', [
        'email'                 => $user->email,
        'token'                 => 'garbage-token',
        'password'              => 'new-strong-password',
        'password_confirmation' => 'new-strong-password',
    ]);

    $response->assertStatus(422)->assertJson(['success' => false]);
});

it('rejects an expired reset token', function () {
    $user = User::factory()->create(['active' => true]);

    $token = Password::createToken($user);

    // Force the underlying password_reset_tokens row to look old (>60min default expiry).
    \Illuminate\Support\Facades\DB::table('password_reset_tokens')
        ->where('email', $user->email)
        ->update(['created_at' => now()->subHours(2)]);

    $response = $this->postJson('/api/v1/auth/reset-password', [
        'email'                 => $user->email,
        'token'                 => $token,
        'password'              => 'new-strong-password',
        'password_confirmation' => 'new-strong-password',
    ]);

    $response->assertStatus(422)->assertJson(['success' => false]);
});

it('validates required fields on reset-password', function () {
    $this->postJson('/api/v1/auth/reset-password', [])->assertUnprocessable();
});

it('returns 429 after exceeding the password-reset rate limit', function () {
    for ($i = 0; $i < 3; $i++) {
        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'someone@example.com']);
    }

    $this->postJson('/api/v1/auth/forgot-password', ['email' => 'someone@example.com'])
         ->assertStatus(429);
});
