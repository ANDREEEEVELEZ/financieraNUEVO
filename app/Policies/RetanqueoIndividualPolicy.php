<?php

namespace App\Policies;

use App\Models\RetanqueoIndividual;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * RetanqueoIndividual currently has ZERO existing authorization gate
 * anywhere in the codebase (design D7 / spec Requirement 4.3): it IS
 * referenced in `RetanqueoResource\Pages\EditRetanqueo.php`, but only as a
 * plain data write (`::create([...])`) inside a Filament action callback —
 * never behind a hasRole/hasAnyRole/visible/hidden/authorize/canX gate.
 * That is not a Bucket-A authorization site per design D6.
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
class RetanqueoIndividualPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('view_any_retanqueo_individual');
    }

    public function view(User $user, RetanqueoIndividual $retanqueoIndividual): bool
    {
        return $user->can('view_retanqueo_individual');
    }

    public function create(User $user): bool
    {
        return $user->can('create_retanqueo_individual');
    }

    public function update(User $user, RetanqueoIndividual $retanqueoIndividual): bool
    {
        return $user->can('update_retanqueo_individual');
    }

    public function delete(User $user, RetanqueoIndividual $retanqueoIndividual): bool
    {
        return $user->can('delete_retanqueo_individual');
    }
}
