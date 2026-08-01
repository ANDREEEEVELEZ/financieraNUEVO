<?php

declare(strict_types=1);

use App\Contracts\AuthEventLoggerInterface;
use App\Listeners\Auth\LogSuccessfulLogout;
use App\Models\User;
use Illuminate\Auth\Events\Logout;

/**
 * Design D8 / spec Scenario 5.1.d: `Logout` MUST produce an `audit_logs`
 * row (`accion = auth_logout`) referencing the user who logged out.
 */
uses(Illuminate\Foundation\Testing\TestCase::class);

it('logs a logout with accion=auth_logout, referencing the user model', function () {
    $user = new User();
    $user->id = 13;

    $logger = Mockery::mock(AuthEventLoggerInterface::class);
    $logger->shouldReceive('log')
        ->once()
        ->withArgs(function (string $accion, $modelo, array $datos) use ($user) {
            return $accion === 'auth_logout'
                && $modelo === $user
                && $datos['guard'] === 'sanctum'
                && array_key_exists('ip', $datos)
                && array_key_exists('user_agent', $datos)
                && ! array_key_exists('token', $datos)
                && ! array_key_exists('access_token', $datos);
        });

    (new LogSuccessfulLogout($logger))->handle(new Logout('sanctum', $user));
});
