<?php

namespace App\Policies;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * AuditLog has no Filament UI of its own — it is read via
 * `AuditLog::delModelo(...)` (e.g. `PrestamoResource\Pages\ViewPrestamo.php`)
 * and written internally by domain services, with ZERO authorization check
 * anywhere (design D7 / spec Requirement 4.3; confirmed by grep: the only
 * Filament reference is a plain `::delModelo(...)->count()` read, never a
 * hasRole/hasAnyRole/visible/hidden/authorize/canX gate).
 *
 * Parity-preserving choice: Shield-style CRUD permission checks, consistent
 * with the other 20 existing Policies (mirrors PrestamoPolicy's shape per
 * design D7's instruction for models with no inline check). PermissionSeeder
 * grants every permission below to ALL FOUR existing roles, so this is
 * functionally equivalent to "allow any authenticated user" — the current
 * de-facto behavior — while staying idiomatically consistent with the
 * codebase (Requirement 4.3 forbids silently tightening access).
 *
 * Not yet referenced by any call site — this Policy is additive-only in
 * this change (Slice 5b-catalog).
 */
class AuditLogPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('view_any_audit_log');
    }

    public function view(User $user, AuditLog $auditLog): bool
    {
        return $user->can('view_audit_log');
    }

    public function create(User $user): bool
    {
        return $user->can('create_audit_log');
    }

    public function update(User $user, AuditLog $auditLog): bool
    {
        return $user->can('update_audit_log');
    }

    public function delete(User $user, AuditLog $auditLog): bool
    {
        return $user->can('delete_audit_log');
    }
}
