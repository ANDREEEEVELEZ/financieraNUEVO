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

    /**
     * Rechazar pago pendiente: JO or SA only.
     *
     * Slice 5c (design D6): verbatim copy of the `hasAnyRole(['super_admin',
     * 'Jefe de operaciones'])` closure previously inlined at
     * `PagoResource.php`, `EditPago.php`, and `GrupoDetallePagos.php`'s
     * "rechazar"/"rechazarPago" action `->visible()` sites. State validity
     * (`estado_pago === 'pendiente'`) is checked separately by each caller,
     * same convention as `PrestamoPolicy::aprobar/firmar/desembolsar`.
     */
    public function rechazar(User $user, Pago $pago): bool
    {
        return $user->hasAnyRole(['Jefe de operaciones', 'super_admin']);
    }

    /**
     * Aprobar en lote (bulk action, sin instancia de Pago): JO or SA only.
     *
     * Slice 5c (design D6): verbatim copy of the `hasAnyRole(['super_admin',
     * 'Jefe de operaciones'])` closure previously inlined at
     * `GrupoDetallePagos.php`'s `aprobar_masivo` bulk-action `->visible()`
     * site. No model instance is available at that call site (it visibility-
     * gates the whole bulk action, not a specific record), so this method
     * takes only `$user` — resolved via `$user->can('aprobarMasivo',
     * Pago::class)`.
     */
    public function aprobarMasivo(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'Jefe de operaciones']);
    }

    /**
     * Crear un pago desde la vista de detalle de grupo: Asesor only.
     *
     * Slice 5c (design D6): verbatim copy of the `hasRole('Asesor')` closure
     * previously inlined at `GrupoDetallePagos.php`'s "crear_pago" header
     * action `->visible()` site. Deliberately distinct from `create()`
     * above (which delegates to Shield permissions) to avoid silently
     * changing behavior — the two checks were never the same mechanism
     * before this migration, and Requirement 4.1 forbids re-deriving one
     * from the other without evidence they were already equivalent.
     */
    public function crearParaGrupo(User $user): bool
    {
        return $user->hasRole('Asesor');
    }

    /**
     * Ver/editar el detalle de un pago desde `GrupoDetallePagos` (la vista de
     * pagos por grupo): super_admin/Jefe de operaciones/Jefe de creditos ven
     * todos; Asesor solo si el grupo del pago es el suyo.
     *
     * Slice 5c (design D6): verbatim copy of the compound closure previously
     * inlined at `GrupoDetallePagos.php`'s "Ver Detalles" `EditAction`
     * `->visible()` site (lines 828-845 pre-migration). The Asesor-ownership
     * branch's `Asesor::where('user_id', ...)->first()` lookup is a real
     * Eloquent query the original closure already performed — this was
     * never testable without a database, migration or not.
     */
    public function verEnGrupoDetalle(User $user, Pago $pago): bool
    {
        if ($user->hasAnyRole(['super_admin', 'Jefe de operaciones', 'Jefe de creditos'])) {
            return true;
        }

        if ($user->hasRole('Asesor')) {
            $asesor = \App\Models\Asesor::where('user_id', $user->id)->first();
            $grupo = $pago->cuotaGrupal?->prestamo?->grupo;

            return $asesor && $grupo && $grupo->asesor_id === $asesor->id;
        }

        return false;
    }
}
