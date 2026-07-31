<?php

namespace App\Policies;

use App\Models\MetaMensual;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * MetaMensual has no Filament Resource of its own — it is read inline by
 * `AsesorDashboard` (`::where('anio', ...)`), with ZERO authorization check
 * anywhere (design D7 / spec Requirement 4.3; confirmed by grep: every
 * reference is a plain read, never a
 * hasRole/hasAnyRole/visible/hidden/authorize/canX gate).
 *
 * Parity-preserving choice: Shield-style CRUD permission checks, consistent
 * with the other 25 existing Policies (mirrors PrestamoPolicy's shape per
 * design D7's instruction for models with no inline check). PermissionSeeder
 * grants every permission below to ALL FOUR existing roles, so this is
 * functionally equivalent to "allow any authenticated user" — the current
 * de-facto behavior — while staying idiomatically consistent with the
 * codebase (Requirement 4.3 forbids silently tightening access).
 *
 * Not yet referenced by any call site — this Policy is additive-only in
 * this change (Slice 5b-catalog).
 */
class MetaMensualPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('view_any_meta_mensual');
    }

    public function view(User $user, MetaMensual $metaMensual): bool
    {
        return $user->can('view_meta_mensual');
    }

    public function create(User $user): bool
    {
        return $user->can('create_meta_mensual');
    }

    public function update(User $user, MetaMensual $metaMensual): bool
    {
        return $user->can('update_meta_mensual');
    }

    public function delete(User $user, MetaMensual $metaMensual): bool
    {
        return $user->can('delete_meta_mensual');
    }
}
