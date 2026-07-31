<?php

namespace App\Policies;

use App\Models\Subcategoria;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Subcategoria has no Filament Resource of its own — it is read/written
 * inline by `EgresosResource` and its Pages (`::find(...)`,
 * `::create(...)`), with ZERO authorization check anywhere (design D7 /
 * spec Requirement 4.3; confirmed by grep: every reference is a plain
 * attribute read/write, never a
 * hasRole/hasAnyRole/visible/hidden/authorize/canX gate).
 *
 * Parity-preserving choice: Shield-style CRUD permission checks, consistent
 * with the other 29 existing Policies (mirrors PrestamoPolicy's shape per
 * design D7's instruction for models with no inline check). PermissionSeeder
 * grants every permission below to ALL FOUR existing roles, so this is
 * functionally equivalent to "allow any authenticated user" — the current
 * de-facto behavior — while staying idiomatically consistent with the
 * codebase (Requirement 4.3 forbids silently tightening access).
 *
 * Not yet referenced by any call site — this Policy is additive-only in
 * this change (Slice 5b-catalog). This completes all 30 registered Policies
 * for Slice 5b (11 original + 5 financial + 4 lifecycle + 10 catalog); the
 * 31st model, GrupoCliente, is a justified exclusion (see
 * CatalogModelsPolicyTest.php's docblock and AuthServiceProvider's docblock
 * for the full rationale — Scenario 4.3.b).
 */
class SubcategoriaPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('view_any_subcategoria');
    }

    public function view(User $user, Subcategoria $subcategoria): bool
    {
        return $user->can('view_subcategoria');
    }

    public function create(User $user): bool
    {
        return $user->can('create_subcategoria');
    }

    public function update(User $user, Subcategoria $subcategoria): bool
    {
        return $user->can('update_subcategoria');
    }

    public function delete(User $user, Subcategoria $subcategoria): bool
    {
        return $user->can('delete_subcategoria');
    }
}
