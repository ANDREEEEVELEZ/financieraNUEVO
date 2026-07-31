<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Cliente;
use Illuminate\Auth\Access\HandlesAuthorization;

class ClientePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_cliente');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Cliente $cliente): bool
    {
        return $user->can('view_cliente');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create_cliente');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Cliente $cliente): bool
    {
        return $user->can('update_cliente');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Cliente $cliente): bool
    {
        return $user->can('delete_cliente');
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_cliente');
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User $user, Cliente $cliente): bool
    {
        return $user->can('force_delete_cliente');
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_cliente');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user, Cliente $cliente): bool
    {
        return $user->can('restore_cliente');
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_cliente');
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User $user, Cliente $cliente): bool
    {
        return $user->can('replicate_cliente');
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return $user->can('reorder_cliente');
    }

    /**
     * Slice 5g (design D6, spec Requirement 4.1): gates the `asesor_id`
     * form field's required/visible state in `ClienteResource::form()` —
     * verbatim copy of the closure it replaces.
     */
    public function verCampoAsesor(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'Jefe de operaciones']);
    }

    /**
     * Slice 5g: gates the asesor `SelectFilter::visible()` in
     * `ClienteResource::table()` — verbatim copy of the closure it replaces.
     * Kept as its own method (not reused with `verCampoAsesor`) per D6's
     * "one line per site, no restructuring" rule, even though the bodies
     * currently coincide — same convention as `GrupoPolicy::verCampoAsesor`/
     * `cambiarAsesorMasivo` (PR11).
     */
    public function verFiltroAsesor(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'Jefe de operaciones']);
    }

    /**
     * Slice 5g: gates the per-row `trasladar_cliente` Action::visible() in
     * `ClienteResource::table()` — verbatim copy of the closure it replaces.
     */
    public function trasladarCliente(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'Jefe de operaciones']);
    }

    /**
     * Slice 5g: gates the header `trasladar_clientes_masivo` Action::visible()
     * in `ListClientes::getHeaderActions()` — verbatim copy of the closure it
     * replaces. Distinct role set from the other three methods above (adds
     * `Jefe de creditos`).
     */
    public function trasladarClientesMasivo(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'Jefe de operaciones', 'Jefe de creditos']);
    }
}
