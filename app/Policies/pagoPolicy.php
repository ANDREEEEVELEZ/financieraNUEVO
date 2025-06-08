<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Pago;
use Illuminate\Auth\Access\HandlesAuthorization;

class PagoPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_pago');
    }

    /**
     * Determine whether the user can view the model.
     */
public function view(User $user, Pago $pago): bool
{
    $grupo = optional($pago->cuotaGrupal?->prestamo?->grupo);
    $asesor = \App\Models\Asesor::where('user_id', $user->id)->first();

    return $grupo && $asesor && $grupo->asesor_id === $asesor->id
        || $user->hasAnyRole(['super_admin', 'Jefe de operaciones', 'Jefe de creditos']);
}

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create_pago');
    }

    /**
     * Determine whether the user can update the model.
     * Solo permitirá acceder a la vista de edición, pero no guardar cambios.
     */
    public function update(User $user, Pago $pago): bool
    {
        return $this->view($user, $pago);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Pago $pago): bool
    {
        return $user->can('delete_pago');
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_pago');
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User $user, Pago $pago): bool
    {
        return $user->can('force_delete_pago');
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_pago');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user, Pago $pago): bool
    {
        return $user->can('restore_pago');
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_pago');
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User $user, Pago $pago): bool
    {
        return $user->can('replicate_pago');
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return $user->can('reorder_pago');
    }
}
