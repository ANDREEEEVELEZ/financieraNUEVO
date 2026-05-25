<?php

use Illuminate\Support\Facades\RateLimiter;

it('filament-login rate limiter is registered', function () {
    expect(RateLimiter::limiter('filament-login'))->not->toBeNull();
});

it('api-auth rate limiter is registered', function () {
    expect(RateLimiter::limiter('api-auth'))->not->toBeNull();
});

it('password-reset rate limiter is registered', function () {
    expect(RateLimiter::limiter('password-reset'))->not->toBeNull();
});

it('filament-login limiter returns a Limit instance for unauthenticated request', function () {
    $callback = RateLimiter::limiter('filament-login');

    $request = \Illuminate\Http\Request::create('/dashboard/login', 'POST', ['email' => 'test@example.com']);
    $request->server->set('REMOTE_ADDR', '1.2.3.4');

    $limit = $callback($request);

    expect($limit)->toBeInstanceOf(\Illuminate\Cache\RateLimiting\Limit::class);
});
