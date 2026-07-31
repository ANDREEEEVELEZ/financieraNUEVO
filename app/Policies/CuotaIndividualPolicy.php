<?php

namespace App\Policies;

use App\Models\CuotaIndividual;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * CuotaIndividual currently has ZERO existing authorization gate anywhere
 * in the codebase (design D7 / spec Requirement 4.3): no hasRole/hasAnyRole/
 * visible/hidden/authorize/canX call site references this model directly —
 * it is only read as a relationship from Pago-related Filament pages
 * (already gated by PagoPolicy) and written by domain services
 * (CondonacionMoraService, CuotaIndividual::condonarMora()) with no
 * authorization check at all.
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
class CuotaIndividualPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('view_any_cuota_individual');
    }

    public function view(User $user, CuotaIndividual $cuotaIndividual): bool
    {
        return $user->can('view_cuota_individual');
    }

    public function create(User $user): bool
    {
        return $user->can('create_cuota_individual');
    }

    public function update(User $user, CuotaIndividual $cuotaIndividual): bool
    {
        return $user->can('update_cuota_individual');
    }

    public function delete(User $user, CuotaIndividual $cuotaIndividual): bool
    {
        return $user->can('delete_cuota_individual');
    }
}
