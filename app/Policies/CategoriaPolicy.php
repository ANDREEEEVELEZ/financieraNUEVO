<?php

namespace App\Policies;

use App\Models\Categoria;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Categoria has no Filament Resource of its own — it is read/written inline
 * by `EgresosResource` and its Pages (`::find(...)`, used to populate an
 * expense's category), with ZERO authorization check anywhere (design D7 /
 * spec Requirement 4.3; confirmed by grep: every reference is a plain
 * attribute read, never a hasRole/hasAnyRole/visible/hidden/authorize/canX
 * gate).
 *
 * Parity-preserving choice: Shield-style CRUD permission checks, consistent
 * with the other 22 existing Policies (mirrors PrestamoPolicy's shape per
 * design D7's instruction for models with no inline check). PermissionSeeder
 * grants every permission below to ALL FOUR existing roles, so this is
 * functionally equivalent to "allow any authenticated user" — the current
 * de-facto behavior — while staying idiomatically consistent with the
 * codebase (Requirement 4.3 forbids silently tightening access).
 *
 * Not yet referenced by any call site — this Policy is additive-only in
 * this change (Slice 5b-catalog).
 */
class CategoriaPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('view_any_categoria');
    }

    public function view(User $user, Categoria $categoria): bool
    {
        return $user->can('view_categoria');
    }

    public function create(User $user): bool
    {
        return $user->can('create_categoria');
    }

    public function update(User $user, Categoria $categoria): bool
    {
        return $user->can('update_categoria');
    }

    public function delete(User $user, Categoria $categoria): bool
    {
        return $user->can('delete_categoria');
    }
}
