<?php

namespace App\Listeners\Auth;

use App\Contracts\AuthEventLoggerInterface;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Database\Eloquent\Model;

/**
 * Design D8 / spec Scenario 5.1.e: `Illuminate\Auth\Events\PasswordReset`
 * MUST produce EXACTLY ONE `audit_logs` row (`accion = auth_password_reset`).
 *
 * This listener becomes the single source of truth for that log entry —
 * any direct/inline `AuditServiceInterface::registrar('password_reset', ...)`
 * call elsewhere in a password-reset flow MUST be removed to avoid
 * double-logging (see apply-progress for this slice: `ResetPasswordController`
 * does not exist on this branch's ancestry, so that removal is a tracked
 * follow-up once the branches converge, not something this listener can
 * silently paper over).
 */
class LogPasswordReset
{
    public function __construct(private AuthEventLoggerInterface $logger) {}

    public function handle(PasswordReset $event): void
    {
        $modelo = $event->user instanceof Model ? $event->user : null;

        $this->logger->log('auth_password_reset', $modelo, [
            'ip' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ]);
    }
}
