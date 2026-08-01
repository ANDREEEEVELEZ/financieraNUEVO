<?php

namespace App\Policies;

use App\Models\MovimientoFinanciero;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * MovimientoFinanciero has no Filament Resource of its own and no
 * application call site found (design D7 / spec Requirement 4.3; confirmed
 * by grep: no reference under app/Filament or app/Domain), so it has ZERO
 * authorization gate anywhere.
 *
 * Parity-preserving choice: Shield-style CRUD permission checks, consistent
 * with the other 27 existing Policies (mirrors PrestamoPolicy's shape per
 * design D7's instruction for models with no inline check). PermissionSeeder
 * grants every permission below to ALL FOUR existing roles, so this is
 * functionally equivalent to "allow any authenticated user" — the current
 * de-facto behavior — while staying idiomatically consistent with the
 * codebase (Requirement 4.3 forbids silently tightening access).
 *
 * Not yet referenced by any call site — this Policy is additive-only in
 * this change (Slice 5b-catalog).
 */
class MovimientoFinancieroPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('view_any_movimiento_financiero');
    }

    public function view(User $user, MovimientoFinanciero $movimientoFinanciero): bool
    {
        return $user->can('view_movimiento_financiero');
    }

    public function create(User $user): bool
    {
        return $user->can('create_movimiento_financiero');
    }

    public function update(User $user, MovimientoFinanciero $movimientoFinanciero): bool
    {
        return $user->can('update_movimiento_financiero');
    }

    public function delete(User $user, MovimientoFinanciero $movimientoFinanciero): bool
    {
        return $user->can('delete_movimiento_financiero');
    }
}
