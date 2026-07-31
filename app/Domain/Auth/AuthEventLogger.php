<?php

namespace App\Domain\Auth;

use App\Contracts\AuthEventLoggerInterface;
use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

/**
 * Writes authentication-event audit rows directly to `audit_logs`.
 *
 * Design D8: `AuditServiceInterface::registrar()` requires a non-null Model,
 * which cannot express a failed login attempt against an email with no
 * matching User. This logger writes `AuditLog` rows directly and tolerates a
 * null $modelo, relying on `audit_logs.auditable_type`/`auditable_id` being
 * nullable (see `database/migrations/2026_03_10_000002_create_audit_logs_table.php`).
 *
 * Never receives or persists password, access token, or refresh token
 * values — callers (the Auth listeners in app/Listeners/Auth/) are
 * responsible for keeping $datos free of those.
 */
class AuthEventLogger implements AuthEventLoggerInterface
{
    public function log(string $accion, ?Model $modelo, array $datos): void
    {
        AuditLog::create([
            'user_id' => auth()->id(),
            'accion' => $accion,
            'auditable_type' => $modelo ? get_class($modelo) : null,
            'auditable_id' => $modelo?->getKey(),
            'datos_anteriores' => [],
            'datos_nuevos' => $datos,
            'motivo' => '',
            'ip' => request()?->ip(),
        ]);
    }
}
