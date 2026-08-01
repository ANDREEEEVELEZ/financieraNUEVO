<?php

namespace App\Listeners\Auth;

use App\Contracts\AuthEventLoggerInterface;
use Illuminate\Auth\Events\Failed;
use Illuminate\Database\Eloquent\Model;

/**
 * Design D8 / spec Scenarios 5.1.b and 5.1.c: a failed
 * `Illuminate\Auth\Events\Failed` MUST produce an `audit_logs` row
 * (`accion = auth_login_failed`) whether or not the attempted email
 * resolves to an existing User.
 *
 * `Failed::$user` is already null when the guard could not resolve any user
 * for the attempted credentials (unknown email) — the nullable-auditable
 * path in `AuthEventLogger` handles that case without any extra lookup here.
 *
 * The submitted password is intentionally never read from
 * `$event->credentials` — only the attempted email is recorded.
 */
class LogFailedLogin
{
    public function __construct(private AuthEventLoggerInterface $logger) {}

    public function handle(Failed $event): void
    {
        $modelo = $event->user instanceof Model ? $event->user : null;

        $this->logger->log('auth_login_failed', $modelo, [
            'guard' => $event->guard,
            'email_attempted' => $event->credentials['email'] ?? null,
            'ip' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ]);
    }
}
