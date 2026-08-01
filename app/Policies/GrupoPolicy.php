<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Grupo;
use Illuminate\Auth\Access\HandlesAuthorization;

class GrupoPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_grupo');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Grupo $grupo): bool
    {
        return $user->can('view_grupo');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create_grupo');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Grupo $grupo): bool
    {
        return $user->can('update_grupo');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Grupo $grupo): bool
    {
        return $user->can('delete_grupo');
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_grupo');
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User $user, Grupo $grupo): bool
    {
        return $user->can('force_delete_grupo');
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_grupo');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user, Grupo $grupo): bool
    {
        return $user->can('restore_grupo');
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_grupo');
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User $user, Grupo $grupo): bool
    {
        return $user->can('replicate_grupo');
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return $user->can('reorder_grupo');
    }

    // ─── Visibilidad de UI (no es CRUD ni transicion de estado) ────

    /**
     * Ver/editar el campo Asesor en el formulario del grupo (JO/SA lo ven,
     * el resto no lo necesita — Asesor tiene su propio asesor_id implícito,
     * Jefe de creditos no reasigna asesores desde este formulario). Verbatim
     * copy of `GrupoResource::form()`'s `asesor_id` field `->visible()`
     * closure (Slice 5f).
     */
    public function verCampoAsesor(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'Jefe de operaciones']);
    }

    /**
     * Ver el filtro de Asesor en la tabla de grupos (JC/JO/SA lo ven, Asesor
     * no lo necesita — ya solo ve sus propios grupos). Verbatim copy of
     * `GrupoResource::table()`'s asesor `SelectFilter::visible()` closure
     * (Slice 5f). No recibe un `Grupo` puntual, por eso se invoca como
     * `$user->can('verFiltroAsesor', Grupo::class)`.
     */
    public function verFiltroAsesor(User $user): bool
    {
        return ! $user->hasRole('Asesor');
    }

    /**
     * Cambiar el asesor de varios grupos a la vez (bulk action, JO/SA).
     * Verbatim copy of `GrupoResource::table()`'s `cambiar_asesor`
     * `BulkAction::visible()` closure (Slice 5f).
     */
    public function cambiarAsesorMasivo(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'Jefe de operaciones']);
    }
}
