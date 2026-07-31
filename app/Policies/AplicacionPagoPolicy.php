<?php

namespace App\Policies;

use App\Models\AplicacionPago;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * AplicacionPago currently has ZERO existing authorization gate anywhere in
 * the codebase (design D7 / spec Requirement 4.3): no hasRole/hasAnyRole/
 * visible/hidden/authorize/canX call site references this model directly —
 * it is only read/written as part of PagoResource's payment-distribution
 * flow (already gated by PagoPolicy at the Pago level).
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
class AplicacionPagoPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('view_any_aplicacion_pago');
    }

    public function view(User $user, AplicacionPago $aplicacionPago): bool
    {
        return $user->can('view_aplicacion_pago');
    }

    public function create(User $user): bool
    {
        return $user->can('create_aplicacion_pago');
    }

    public function update(User $user, AplicacionPago $aplicacionPago): bool
    {
        return $user->can('update_aplicacion_pago');
    }

    public function delete(User $user, AplicacionPago $aplicacionPago): bool
    {
        return $user->can('delete_aplicacion_pago');
    }
}
