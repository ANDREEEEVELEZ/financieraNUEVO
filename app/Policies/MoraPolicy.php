<?php

namespace App\Policies;

use App\Models\Mora;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Mora currently has ZERO existing authorization gate anywhere in the
 * codebase (design D7 / spec Requirement 4.3): no hasRole/hasAnyRole/
 * visible/hidden/authorize/canX call site references this model directly;
 * the Moras.php dashboard page has no canAccess() override, only a
 * data-scoping (Bucket B) query filter for role-based visibility of records.
 *
 * Parity-preserving choice: Shield-style CRUD permission checks, consistent
 * with the other 11 existing Policies (mirrors PrestamoPolicy's shape per
 * D7's instruction for models with no inline check). PermissionSeeder
 * grants every permission below to ALL FOUR existing roles, so this is
 * functionally equivalent to "allow any authenticated user" — the current
 * de-facto behavior — while staying idiomatically consistent with the
 * codebase (Requirement 4.3 forbids silently tightening access).
 *
 * Not yet referenced by any Filament call site — that migration is Slice
 * 5c-5g (later PRs). This Policy is additive-only in this change.
 */
class MoraPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('view_any_mora');
    }

    public function view(User $user, Mora $mora): bool
    {
        return $user->can('view_mora');
    }

    public function create(User $user): bool
    {
        return $user->can('create_mora');
    }

    public function update(User $user, Mora $mora): bool
    {
        return $user->can('update_mora');
    }

    public function delete(User $user, Mora $mora): bool
    {
        return $user->can('delete_mora');
    }
}
