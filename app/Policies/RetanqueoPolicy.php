<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Retanqueo;
use Illuminate\Auth\Access\HandlesAuthorization;

class RetanqueoPolicy
{
    use HandlesAuthorization;

    /**
     * View any (Resource drift consolidation, Slice 5e): verbatim copy of
     * `RetanqueoResource::canViewAny()`'s body, which was the actual
     * authorization source for the Filament panel — the pre-existing
     * Spatie-permission-based body above was never reachable (the Resource's
     * own `canViewAny()` override short-circuited before Filament could fall
     * back to this Policy). Confirmed drift: `PermissionSeeder` grants
     * `Asesor` none of `view_any_retanqueo`/`create_retanqueo`/
     * `update_retanqueo`/`delete_retanqueo`, yet the Resource's overrides
     * allowed Asesor for viewAny/create/edit(own group).
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'Jefe de operaciones', 'Jefe de creditos', 'Asesor']);
    }

    /**
     * Determine whether the user can view the model.
     *
     * UNCHANGED (Slice 5e): `RetanqueoResource` has no `canView()` override,
     * so this Policy method was already the sole, live authorization path.
     */
    public function view(User $user, Retanqueo $retanqueo): bool
    {
        return $user->can('view_retanqueo');
    }

    /**
     * Create (Resource drift consolidation, Slice 5e): verbatim copy of
     * `RetanqueoResource::canCreate()`'s body — see `viewAny()`'s docblock
     * for why this replaces (not calls) the pre-existing Spatie-permission
     * body.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'Jefe de operaciones', 'Jefe de creditos', 'Asesor']);
    }

    /**
     * Update (Resource drift consolidation, Slice 5e): verbatim copy of
     * `RetanqueoResource::canEdit()`'s body. Asesor is scoped to retanqueos
     * of their own grupo; other privileged roles can edit any pending
     * retanqueo.
     *
     * KNOWN GAP: the Asesor-ownership branch queries `Asesor` directly (no
     * DI seam), so it is verified untested in this DB-free suite — see
     * `RetanqueoPolicyMigrationParityTest`'s docblock.
     */
    public function update(User $user, Retanqueo $retanqueo): bool
    {
        if (! $retanqueo->esSolicitudPendiente()) {
            return false;
        }

        if ($user->hasRole('Asesor')) {
            $asesor = \App\Models\Asesor::where('user_id', $user->id)->first();
            if ($asesor) {
                $grupoAsesorId = $retanqueo->prestamoAntiguo?->grupo?->asesor_id;

                return $grupoAsesorId === $asesor->id;
            }

            return false;
        }

        return $user->hasAnyRole(['super_admin', 'Jefe de operaciones', 'Jefe de creditos']);
    }

    /**
     * Delete (Resource drift consolidation, Slice 5e): verbatim copy of
     * `RetanqueoResource::canDelete()`'s body — super_admin only, and only
     * while the retanqueo is still a pending solicitud. State check moved to
     * an early guard clause (same output as the original `&&` chain, but
     * matches this PR's own convention of short-circuiting on state before
     * checking role — see `PrestamoPolicy::delete()`'s Slice 5d precedent).
     */
    public function delete(User $user, Retanqueo $retanqueo): bool
    {
        if (! $retanqueo->esSolicitudPendiente()) {
            return false;
        }

        return $user->hasRole('super_admin');
    }

    /**
     * Determine whether the user can bulk delete.
     *
     * UNCHANGED (Slice 5e): verified clean against
     * `RetanqueoResource`'s bulk `DeleteBulkAction` closure
     * (`hasAnyRole(['super_admin'])`) — `PermissionSeeder` grants
     * `delete_any_retanqueo` to super_admin only, functionally identical.
     * Reused as-is, not modified.
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_retanqueo');
    }

    // ─── Visibilidad de UI (no es CRUD ni transicion de estado) ────

    /**
     * Ver la columna de Asesor en la tabla de retanqueos (JC/JO/SA la ven,
     * Asesor no la necesita — ya solo ve sus propios retanqueos).
     */
    public function verColumnaAsesor(User $user): bool
    {
        return ! $user->hasRole('Asesor');
    }

    // ─── Transiciones de estado ─────────────────────────────────────

    /**
     * Aprobar: solicitud_pendiente -> aprobado (JC, JO, super_admin). State
     * check folded INTO the Policy method, verbatim from the Resource's own
     * `->visible()` closure (state and role were combined in the SAME
     * boolean expression at the call site).
     */
    public function aprobar(User $user, Retanqueo $retanqueo): bool
    {
        return $retanqueo->esSolicitudPendiente()
            && $user->hasAnyRole(['super_admin', 'Jefe de operaciones', 'Jefe de creditos']);
    }

    /**
     * Rechazar: solicitud_pendiente -> rechazado (JC, JO, super_admin).
     */
    public function rechazar(User $user, Retanqueo $retanqueo): bool
    {
        return $retanqueo->esSolicitudPendiente()
            && $user->hasAnyRole(['super_admin', 'Jefe de operaciones', 'Jefe de creditos']);
    }

    /**
     * Ejecutar: aprobado -> ejecutado (JC, JO, super_admin).
     */
    public function ejecutar(User $user, Retanqueo $retanqueo): bool
    {
        return $retanqueo->estaAprobado()
            && $user->hasAnyRole(['super_admin', 'Jefe de operaciones', 'Jefe de creditos']);
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User $user, Retanqueo $retanqueo): bool
    {
        return $user->can('force_delete_retanqueo');
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_retanqueo');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user, Retanqueo $retanqueo): bool
    {
        return $user->can('restore_retanqueo');
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_retanqueo');
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User $user, Retanqueo $retanqueo): bool
    {
        return $user->can('replicate_retanqueo');
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return $user->can('reorder_retanqueo');
    }
}
