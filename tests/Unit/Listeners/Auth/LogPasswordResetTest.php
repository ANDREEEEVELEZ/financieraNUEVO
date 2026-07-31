<?php

declare(strict_types=1);

use App\Contracts\AuthEventLoggerInterface;
use App\Listeners\Auth\LogPasswordReset;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;

/**
 * Design D8 / spec Scenario 5.1.e: a `PasswordReset` event MUST produce
 * EXACTLY ONE `audit_logs` row (`accion = auth_password_reset`) — the
 * `->once()` expectation below is the load-bearing assertion for "exactly
 * one", at the listener level. (The removal of `ResetPasswordController`'s
 * inline `registrar('password_reset', ...)` call — the other half of this
 * scenario's dedup requirement — is tracked separately: that controller
 * does not exist on this branch's ancestry; see apply-progress for this
 * slice.)
 */
uses(Illuminate\Foundation\Testing\TestCase::class);

it('logs a password reset exactly once, with accion=auth_password_reset, referencing the user model', function () {
    $user = new User();
    $user->id = 3;

    $logger = Mockery::mock(AuthEventLoggerInterface::class);
    $logger->shouldReceive('log')
        ->once()
        ->withArgs(function (string $accion, $modelo, array $datos) use ($user) {
            return $accion === 'auth_password_reset'
                && $modelo === $user
                && array_key_exists('ip', $datos)
                && array_key_exists('user_agent', $datos)
                && ! array_key_exists('password', $datos)
                && ! array_key_exists('password_confirmation', $datos)
                && ! array_key_exists('token', $datos);
        });

    (new LogPasswordReset($logger))->handle(new PasswordReset($user));
});
