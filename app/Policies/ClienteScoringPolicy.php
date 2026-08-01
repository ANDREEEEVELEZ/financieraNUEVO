<?php

namespace App\Policies;

use App\Models\ClienteScoring;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * ClienteScoring has no Filament Resource of its own — it is read inline by
 * `AsesorDashboard` (`::whereIn(...)`, `::where('vigente', true)`), with
 * ZERO authorization check anywhere (design D7 / spec Requirement 4.3;
 * confirmed by grep: every reference is a plain read, never a
 * hasRole/hasAnyRole/visible/hidden/authorize/canX gate).
 *
 * Parity-preserving choice: Shield-style CRUD permission checks, consistent
 * with the other 23 existing Policies (mirrors PrestamoPolicy's shape per
 * design D7's instruction for models with no inline check). PermissionSeeder
 * grants every permission below to ALL FOUR existing roles, so this is
 * functionally equivalent to "allow any authenticated user" — the current
 * de-facto behavior — while staying idiomatically consistent with the
 * codebase (Requirement 4.3 forbids silently tightening access).
 *
 * Not yet referenced by any call site — this Policy is additive-only in
 * this change (Slice 5b-catalog).
 */
class ClienteScoringPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('view_any_cliente_scoring');
    }

    public function view(User $user, ClienteScoring $clienteScoring): bool
    {
        return $user->can('view_cliente_scoring');
    }

    public function create(User $user): bool
    {
        return $user->can('create_cliente_scoring');
    }

    public function update(User $user, ClienteScoring $clienteScoring): bool
    {
        return $user->can('update_cliente_scoring');
    }

    public function delete(User $user, ClienteScoring $clienteScoring): bool
    {
        return $user->can('delete_cliente_scoring');
    }
}
