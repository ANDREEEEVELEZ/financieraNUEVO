<?php

namespace App\Listeners\Auth;

use App\Contracts\AuthEventLoggerInterface;
use Illuminate\Auth\Events\Logout;
use Illuminate\Database\Eloquent\Model;

/**
 * Design D8 / spec Scenario 5.1.d: `Illuminate\Auth\Events\Logout` MUST
 * produce an `audit_logs` row (`accion = auth_logout`) referencing the user
 * who logged out.
 */
class LogSuccessfulLogout
{
    public function __construct(private AuthEventLoggerInterface $logger) {}

    public function handle(Logout $event): void
    {
        $modelo = $event->user instanceof Model ? $event->user : null;

        $this->logger->log('auth_logout', $modelo, [
            'guard' => $event->guard,
            'ip' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ]);
    }
}
