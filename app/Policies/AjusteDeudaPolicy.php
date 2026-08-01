<?php

namespace App\Policies;

use App\Models\AjusteDeuda;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * AjusteDeuda has no Filament UI at all — it is created exclusively by
 * domain services (CondonacionMoraService, CuotaIndividual::condonarMora())
 * with ZERO authorization check anywhere (design D7 / spec Requirement 4.3;
 * confirmed by grep: neither of those call sites is wired to any Filament
 * action, and neither checks a role or permission before writing a record).
 *
 * Parity-preserving choice: Shield-style CRUD permission checks, consistent
 * with the other 11 existing Policies (mirrors PrestamoPolicy's shape per
 * design D7's instruction for models with no inline check). PermissionSeeder
 * grants every permission below to ALL FOUR existing roles, so this is
 * functionally equivalent to "allow any authenticated user" — the current
 * de-facto behavior — while staying idiomatically consistent with the
 * codebase (Requirement 4.3 forbids silently tightening access).
 *
 * Not yet referenced by any call site — this Policy is additive-only in
 * this change. Wiring CondonacionMoraService/condonarMora() through it is
 * out of scope here (no call site today performs any authorization check,
 * so there is nothing to migrate for behavior parity in this slice).
 */
class AjusteDeudaPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('view_any_ajuste_deuda');
    }

    public function view(User $user, AjusteDeuda $ajusteDeuda): bool
    {
        return $user->can('view_ajuste_deuda');
    }

    public function create(User $user): bool
    {
        return $user->can('create_ajuste_deuda');
    }

    public function update(User $user, AjusteDeuda $ajusteDeuda): bool
    {
        return $user->can('update_ajuste_deuda');
    }

    public function delete(User $user, AjusteDeuda $ajusteDeuda): bool
    {
        return $user->can('delete_ajuste_deuda');
    }
}
