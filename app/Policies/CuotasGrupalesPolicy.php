<?php

namespace App\Policies;

use App\Models\CuotasGrupales;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * CuotasGrupales has a Filament Resource (CuotasResource) but it carries no
 * authorization gate — CuotasResource::getTableQuery() only data-scopes
 * (Bucket B: hasRole('Asesor') filters WHICH rows are returned, it never
 * denies access), CuotasResource::canCreate() is hardcoded false for
 * everyone (a product decision, not a role gate), and the one column-level
 * ->visible() call is UI cosmetics (Bucket C — hides the "Asesor" column
 * from Asesor users viewing their own data, not an authorization decision).
 * No hasRole/hasAnyRole/authorize/canEdit/canView/canDelete gate exists.
 *
 * Parity-preserving choice: Shield-style CRUD permission checks, consistent
 * with the other 11 existing Policies (mirrors PrestamoPolicy's shape per
 * design D7's instruction for models with no inline check). PermissionSeeder
 * grants every permission below to ALL FOUR existing roles, so this is
 * functionally equivalent to "allow any authenticated user" — the current
 * de-facto behavior — while staying idiomatically consistent with the
 * codebase (Requirement 4.3 forbids silently tightening access).
 *
 * Not yet referenced by any Filament call site — that migration is Slice
 * 5c-5g (later PRs). This Policy is additive-only in this change.
 */
class CuotasGrupalesPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('view_any_cuotas_grupales');
    }

    public function view(User $user, CuotasGrupales $cuotasGrupales): bool
    {
        return $user->can('view_cuotas_grupales');
    }

    public function create(User $user): bool
    {
        return $user->can('create_cuotas_grupales');
    }

    public function update(User $user, CuotasGrupales $cuotasGrupales): bool
    {
        return $user->can('update_cuotas_grupales');
    }

    public function delete(User $user, CuotasGrupales $cuotasGrupales): bool
    {
        return $user->can('delete_cuotas_grupales');
    }
}
