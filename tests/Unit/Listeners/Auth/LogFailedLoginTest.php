<?php

declare(strict_types=1);

use App\Contracts\AuthEventLoggerInterface;
use App\Listeners\Auth\LogFailedLogin;
use App\Models\User;
use Illuminate\Auth\Events\Failed;

/**
 * Design D8 / spec Scenarios 5.1.b and 5.1.c: a `Failed` login event MUST
 * produce an `audit_logs` row whether or not the attempted email resolves
 * to an existing User. `Failed::$user` is already null (set by
 * `SessionGuard::retrieveByCredentials()`) when no user matches the
 * attempted email — this listener's nullable-model branch is exercised
 * directly here, DB-free via a mocked `AuthEventLoggerInterface`.
 */
uses(Illuminate\Foundation\Testing\TestCase::class);

it('logs a failed login for a KNOWN user, referencing that user model, without the password', function () {
    $user = new User();
    $user->id = 7;

    $logger = Mockery::mock(AuthEventLoggerInterface::class);
    $logger->shouldReceive('log')
        ->once()
        ->withArgs(function (string $accion, $modelo, array $datos) use ($user) {
            return $accion === 'auth_login_failed'
                && $modelo === $user
                && $datos['email_attempted'] === 'known@example.com'
                && array_key_exists('guard', $datos)
                && ! array_key_exists('password', $datos);
        });

    (new LogFailedLogin($logger))->handle(new Failed('web', $user, [
        'email' => 'known@example.com',
        'password' => 'super-secret-should-never-be-logged',
    ]));
});

it('still logs a failed login for an UNKNOWN email via the nullable-auditable path', function () {
    $logger = Mockery::mock(AuthEventLoggerInterface::class);
    $logger->shouldReceive('log')
        ->once()
        ->withArgs(function (string $accion, $modelo, array $datos) {
            return $accion === 'auth_login_failed'
                && $modelo === null
                && $datos['email_attempted'] === 'ghost@example.com'
                && ! array_key_exists('password', $datos);
        });

    (new LogFailedLogin($logger))->handle(new Failed('web', null, [
        'email' => 'ghost@example.com',
        'password' => 'whatever-was-typed',
    ]));
});
