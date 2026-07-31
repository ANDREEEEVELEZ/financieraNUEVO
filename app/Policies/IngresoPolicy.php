<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Ingreso;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Slice 5g (design D6, spec Requirement 4.1): `IngresosResource` overrode
 * `canAccess()`/`shouldRegisterNavigation()` directly with an inline
 * `Auth::user()->hasRole([...])` check (Bucket A), bypassing this Policy
 * entirely — same confirmed drift as `EgresoPolicy` (see its docblock for
 * the full explanation). `view_any_ingresos` was never seeded in
 * `database/seeders/PermissionSeeder.php`, so this Policy's `viewAny()` body
 * was 100% unreachable AND would have denied everyone had it ever been
 * reached. Resolution: the Resource's two overrides are deleted; `viewAny()`
 * below is now the verbatim role check that was the actual, live
 * authorization path. `view`/`create`/`update`/`delete`/etc. are UNCHANGED —
 * `IngresosResource` has no competing override for any of them.
 */
class IngresoPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['super_admin', 'Jefe de operaciones']);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Ingreso $ingreso): bool
    {
        return $user->can('view_ingresos');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create_ingresos');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Ingreso $ingreso): bool
    {
        return $user->can('update_ingresos');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Ingreso $ingreso): bool
    {
        return $user->can('delete_ingresos');
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_ingresos');
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User $user, Ingreso $ingreso): bool
    {
        return $user->can('force_delete_ingresos');
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_ingresos');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user, Ingreso $ingreso): bool
    {
        return $user->can('restore_ingresos');
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_ingresos');
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User $user, Ingreso $ingreso): bool
    {
        return $user->can('replicate_ingresos');
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return $user->can('reorder_ingresos');
    }
}
