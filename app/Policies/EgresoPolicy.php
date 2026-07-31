<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Egreso;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Slice 5g (design D6, spec Requirement 4.1): `EgresosResource` overrode
 * `canAccess()`/`shouldRegisterNavigation()` directly with an inline
 * `Auth::user()->hasRole([...])` check (Bucket A), bypassing this Policy
 * entirely — Filament's default `canAccess()`/`shouldRegisterNavigation()`
 * both delegate to `canViewAny()` -> `Gate::check('viewAny', ...)` ->
 * `viewAny()` below, but the Resource's own overrides always short-circuited
 * first. This is a CONFIRMED drift, stronger than PR9/PR10's precedent:
 * `view_any_egresos` is not just uncalled elsewhere, it was never even
 * seeded in `database/seeders/PermissionSeeder.php` — this Policy's
 * `viewAny()` body was 100% unreachable AND would have denied everyone had
 * it ever been reached. Resolution: the Resource's two overrides are
 * deleted; `viewAny()`'s body below is now the verbatim role check that was
 * the actual, live authorization path. `view`/`create`/`update`/`delete`/
 * etc. are UNCHANGED — `EgresosResource` has no competing override for any
 * of them, so they are out of this migration's scope.
 */
class EgresoPolicy
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
    public function view(User $user, Egreso $egreso): bool
    {
        return $user->can('view_egresos');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create_egresos');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Egreso $egreso): bool
    {
        return $user->can('update_egresos');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Egreso $egreso): bool
    {
        return $user->can('delete_egresos');
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_egresos');
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User $user, Egreso $egreso): bool
    {
        return $user->can('force_delete_egresos');
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_egresos');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user, Egreso $egreso): bool
    {
        return $user->can('restore_egresos');
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_egresos');
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User $user, Egreso $egreso): bool
    {
        return $user->can('replicate_egresos');
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return $user->can('reorder_egresos');
    }
}
