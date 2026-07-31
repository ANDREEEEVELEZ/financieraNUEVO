<?php

namespace App\Policies;

use App\Models\SeparacionCliente;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * SeparacionCliente currently has ZERO existing authorization gate anywhere
 * in the codebase (design D7 / spec Requirement 4.3): it has no Filament
 * Resource of its own — no hasRole/hasAnyRole/visible/hidden/authorize/canX
 * call site references this model anywhere under app/Filament. It is
 * written only by `App\Domain\Grupos\MorosoSeparationService`, an ungated
 * domain service.
 *
 * Parity-preserving choice: Shield-style CRUD permission checks, consistent
 * with the other 16 existing Policies (mirrors PrestamoPolicy's shape per
 * D7's instruction for models with no inline check). PermissionSeeder
 * grants every permission below to ALL FOUR existing roles, so this is
 * functionally equivalent to "allow any authenticated user" — the current
 * de-facto behavior — while staying idiomatically consistent with the
 * codebase (Requirement 4.3 forbids silently tightening access).
 *
 * Not yet referenced by any Filament Policy call site — that migration is
 * Slice 5c-5g (later PRs). This Policy is additive-only in this change.
 */
class SeparacionClientePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('view_any_separacion_cliente');
    }

    public function view(User $user, SeparacionCliente $separacionCliente): bool
    {
        return $user->can('view_separacion_cliente');
    }

    public function create(User $user): bool
    {
        return $user->can('create_separacion_cliente');
    }

    public function update(User $user, SeparacionCliente $separacionCliente): bool
    {
        return $user->can('update_separacion_cliente');
    }

    public function delete(User $user, SeparacionCliente $separacionCliente): bool
    {
        return $user->can('delete_separacion_cliente');
    }
}
