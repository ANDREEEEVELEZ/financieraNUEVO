<?php

namespace App\Contracts;

use Illuminate\Database\Eloquent\Model;

interface AuthEventLoggerInterface
{
    /**
     * Writes a single `audit_logs` row for an authentication event.
     *
     * Unlike AuditServiceInterface::registrar(), $modelo MAY be null — a
     * failed login attempt against an email with no matching User has no
     * resolvable model to attach to (design D8 / spec Scenario 5.1.c).
     *
     * @param string     $accion Event identifier (e.g. 'auth_login', 'auth_login_failed').
     * @param Model|null $modelo The authenticated/attempted user, or null if unresolvable.
     * @param array      $datos  Non-sensitive context (guard, ip, user_agent, email_attempted, ...).
     *                           MUST NEVER contain a password, access token, or refresh token value.
     */
    public function log(string $accion, ?Model $modelo, array $datos): void;
}
