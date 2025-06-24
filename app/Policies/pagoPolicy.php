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
        if ($user->hasAnyRole(['super_admin', 'Jefe de operaciones', 'Jefe de creditos'])) {
            return true;
        }

        if ($user->hasRole('Asesor')) {
            $asesor = \App\Models\Asesor::where('user_id', $user->id)->first();
            $grupo = optional($pago->cuotaGrupal?->prestamo?->grupo);
            return $asesor && $grupo && $grupo->asesor_id === $asesor->id;
        }

        return false;
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
     *
     * En este método permitimos que:
     * - Los asesores puedan acceder a la pantalla de edición (para ver el registro)
     *   siempre que pertenezcan al grupo, sin importar el estado. La restricción real para editar
     *   se impone en la lógica de la página (que deshabilita el formulario si el pago no está pendiente).
     * - Los jefes y super_admin puedan ver el registro.
     */
    public function update(User $user, Pago $pago): bool
    {
        // Para asesores: si pertenece al grupo, le permitimos acceder a la pantalla
        if ($user->hasRole('Asesor')) {
            $asesor = \App\Models\Asesor::where('user_id', $user->id)->first();
            $grupo = optional($pago->cuotaGrupal?->prestamo?->grupo);
            return $asesor && $grupo && $grupo->asesor_id === $asesor->id;
        }

        // Para jefes y super_admin (acceso para ver la pantalla, sin edición en el formulario)
        if ($user->hasAnyRole(['super_admin', 'Jefe de operaciones', 'Jefe de creditos'])) {
            return true;
        }

        return false;
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
     * Determine whether the user can permanently delete the model.
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
     * Determine whether the user can restore the model.
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
     * Determine whether the user can replicate the model.
     */
    public function replicate(User $user, Pago $pago): bool
    {
        return $user->can('replicate_pago');
    }

    /**
     * Determine whether the user can reorder models.
     */
    public function reorder(User $user): bool
    {
        return $user->can('reorder_pago');
    }
}
