<?php

namespace App\Listeners\Auth;

use App\Contracts\AuthEventLoggerInterface;
use Illuminate\Auth\Events\Login;
use Illuminate\Database\Eloquent\Model;

/**
 * Design D8 / spec Scenario 5.1.a: a successful `Illuminate\Auth\Events\Login`
 * MUST produce an `audit_logs` row (`accion = auth_login`) referencing the
 * authenticated user. Log-only — no email/Slack/webhook side effect.
 */
class LogSuccessfulLogin
{
    public function __construct(private AuthEventLoggerInterface $logger) {}

    public function handle(Login $event): void
    {
        $modelo = $event->user instanceof Model ? $event->user : null;

        $this->logger->log('auth_login', $modelo, [
            'guard' => $event->guard,
            'ip' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'remember' => $event->remember,
        ]);
    }
}
