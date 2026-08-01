<?php

declare(strict_types=1);

use App\Contracts\AuthEventLoggerInterface;
use App\Listeners\Auth\LogSuccessfulLogin;
use App\Models\User;
use Illuminate\Auth\Events\Login;

/**
 * Design D8 / spec Scenario 5.1.a: a successful `Login` event MUST produce
 * an `audit_logs` row (`accion = auth_login`) referencing the authenticated
 * user. DB-free: exercises the listener in isolation against a mocked
 * `AuthEventLoggerInterface` (same convention as PR4-PR12's Mockery-based
 * parity tests) — the actual DB write is covered separately by
 * `tests/Unit/Domain/Auth/AuthEventLoggerTest.php`.
 */
uses(Illuminate\Foundation\Testing\TestCase::class);

it('logs a successful login with accion=auth_login, the user model, and a non-sensitive payload', function () {
    $user = new User();
    $user->id = 42;

    $logger = Mockery::mock(AuthEventLoggerInterface::class);
    $logger->shouldReceive('log')
        ->once()
        ->withArgs(function (string $accion, $modelo, array $datos) use ($user) {
            return $accion === 'auth_login'
                && $modelo === $user
                && array_key_exists('guard', $datos)
                && $datos['guard'] === 'web'
                && array_key_exists('ip', $datos)
                && array_key_exists('user_agent', $datos)
                && $datos['remember'] === false
                && ! array_key_exists('password', $datos)
                && ! array_key_exists('token', $datos)
                && ! array_key_exists('access_token', $datos)
                && ! array_key_exists('refresh_token', $datos);
        });

    (new LogSuccessfulLogin($logger))->handle(new Login('web', $user, false));
});
