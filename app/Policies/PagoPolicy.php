<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Pago;
use Illuminate\Auth\Access\HandlesAuthorization;

class PagoPolicy
{
    use HandlesAuthorization;

    // ─── Filament Shield (CRUD base) ────────────────────────────────

    /**
     * Determine whether the user can view any models.
     * Asesor ve sus pagos, JC/JO ven todos.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_pago')
            || $user->checkPermissionTo('pagos.ver_todos')
            || $user->checkPermissionTo('pagos.crear');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Pago $pago): bool
    {
        if ($user->checkPermissionTo('pagos.ver_todos')) {
            return true;
        }

        // Asesor solo ve pagos de sus grupos
        if ($user->hasRole('Asesor')) {
            $asesor = \App\Models\Asesor::where('user_id', $user->id)->first();
            if ($asesor && $pago->cuotaGrupal && $pago->cuotaGrupal->prestamo) {
                return $pago->cuotaGrupal->prestamo->grupo->asesor_id === $asesor->id;
            }
        }

        return $user->can('view_pago');
    }

    /**
     * Determine whether the user can create models.
     * Asesor + JC pueden registrar solicitudes de pago.
     */
    public function create(User $user): bool
    {
        return $user->can('create_pago') || $user->checkPermissionTo('pagos.crear');
    }

    public function update(User $user, Pago $pago): bool
    {
        return $user->can('update_pago');
    }

    public function delete(User $user, Pago $pago): bool
    {
        return $user->can('delete_pago');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_pago');
    }

    public function forceDelete(User $user, Pago $pago): bool
    {
        return $user->can('force_delete_pago');
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_pago');
    }

    public function restore(User $user, Pago $pago): bool
    {
        return $user->can('restore_pago');
    }

    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_pago');
    }

    public function replicate(User $user, Pago $pago): bool
    {
        return $user->can('replicate_pago');
    }

    public function reorder(User $user): bool
    {
        return $user->can('reorder_pago');
    }

    // ─── Permisos de negocio (custom) ───────────────────────────────

    /**
     * Aprobar pago: JO or SA only.
     */
    public function aprobar(User $user, Pago $pago): bool
    {
        return $user->hasAnyRole(['Jefe de operaciones', 'super_admin']);
    }

    /**
     * Anular pago: JO or SA only.
     */
    public function anular(User $user, Pago $pago): bool
    {
        return $user->hasAnyRole(['Jefe de operaciones', 'super_admin']);
    }

    /**
     * Revertir pago aprobado: JO or SA only.
     */
    public function revertir(User $user, Pago $pago): bool
    {
        return $user->hasAnyRole(['Jefe de operaciones', 'super_admin']);
    }
}
