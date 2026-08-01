<?php

namespace App\Policies;

use App\Contracts\CacheServiceInterface;
use App\Models\User;
use App\Models\Prestamo;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Autorización granular para Préstamos.
 *
 * Cada método de transición de estado verifica:
 *   1. Permiso base (via Spatie) — ej. 'update_prestamo'
 *   2. Rol del usuario — quién tiene el derecho operativo
 *   3. Estado actual del préstamo — cuándo es válida la acción
 */
class PrestamoPolicy
{
    use HandlesAuthorization;

    // ─── CRUD estándar (Spatie permissions) ─────────────────────────

    public function viewAny(User $user): bool
    {
        return $user->can('view_any_prestamo');
    }

    /**
     * View (Resource drift consolidation, Slice 5d): verbatim copy of
     * `PrestamoResource::canView()`'s body, which was the actual authorization
     * source for the Filament panel — the pre-existing Spatie-permission-based
     * body above was never reachable (the Resource's own `canView()` override
     * short-circuited before Filament could fall back to this Policy).
     * Asesor is scoped to préstamos of their own grupo; other privileged roles
     * see any préstamo.
     */
    public function view(User $user, Prestamo $prestamo): bool
    {
        if ($user->hasRole('Asesor')) {
            $asesor = app(CacheServiceInterface::class)->getAsesorByUserId($user->id);

            return (bool) ($asesor && $prestamo->grupo && $prestamo->grupo->asesor_id === $asesor->id);
        }

        return $user->hasAnyRole(['super_admin', 'Jefe de operaciones', 'Jefe de creditos']);
    }

    /**
     * Create (Resource drift consolidation, Slice 5d): verbatim copy of
     * `PrestamoResource::canCreate()`'s body. Deliberately distinct from the
     * Spatie-permission check this method previously had — `create_prestamo`
     * is never assigned/checked anywhere for this Resource; the role list
     * below is what actually governed panel access.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'Jefe de operaciones', 'Jefe de creditos', 'Asesor']);
    }

    /**
     * Update (Resource drift consolidation, Slice 5d): verbatim copy of
     * `PrestamoResource::canEdit()`'s body — see `view()`'s docblock for why
     * this replaces (not calls) the pre-existing Spatie-permission body.
     */
    public function update(User $user, Prestamo $prestamo): bool
    {
        if ($prestamo->es_retanqueo) {
            return false;
        }

        if ($prestamo->estado !== Prestamo::ESTADO_PENDIENTE) {
            return false;
        }

        if ($user->hasRole('Asesor')) {
            $asesor = app(CacheServiceInterface::class)->getAsesorByUserId($user->id);

            return (bool) ($asesor && $prestamo->grupo && $prestamo->grupo->asesor_id === $asesor->id);
        }

        return $user->hasAnyRole(['super_admin', 'Jefe de operaciones', 'Jefe de creditos']);
    }

    /**
     * Delete (Resource drift consolidation, Slice 5d): verbatim copy of
     * `PrestamoResource::canDelete()`'s body — super_admin only, and never a
     * retanqueo.
     */
    public function delete(User $user, Prestamo $prestamo): bool
    {
        if ($prestamo->es_retanqueo) {
            return false;
        }

        return $user->hasRole('super_admin');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_prestamo');
    }

    public function forceDelete(User $user, Prestamo $prestamo): bool
    {
        return $user->can('force_delete_prestamo');
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_prestamo');
    }

    public function restore(User $user, Prestamo $prestamo): bool
    {
        return $user->can('restore_prestamo');
    }

    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_prestamo');
    }

    public function replicate(User $user, Prestamo $prestamo): bool
    {
        return $user->can('replicate_prestamo');
    }

    public function reorder(User $user): bool
    {
        return $user->can('reorder_prestamo');
    }

    // ─── Visibilidad de UI (no es CRUD ni transición de estado) ─────

    /**
     * Ver el filtro de Asesor en la tabla de préstamos (JC/JO/SA lo ven,
     * Asesor no lo necesita — ya solo ve los préstamos de su propio grupo).
     * No recibe un `Prestamo` puntual, por eso se invoca como
     * `$user->can('verFiltroAsesor', Prestamo::class)`.
     */
    public function verFiltroAsesor(User $user): bool
    {
        return ! $user->hasRole('Asesor');
    }

    // ─── Transiciones de estado ─────────────────────────────────────

    /**
     * Aprobar (role gate only): JC or SA can perform this action.
     * State validity is checked separately by the controller (returns 422 on invalid state).
     */
    public function aprobar(User $user, Prestamo $prestamo): bool
    {
        return $user->hasAnyRole(['Jefe de creditos', 'super_admin']);
    }

    /**
     * Firmar contrato (role gate only): Asesor or SA.
     * State validity is checked separately by the controller (returns 422 on invalid state).
     */
    public function firmar(User $user, Prestamo $prestamo): bool
    {
        return $user->hasAnyRole(['Asesor', 'super_admin']);
    }

    /**
     * Desembolsar (role gate only): JO or SA.
     * State validity is checked separately by the controller (returns 422 on invalid state).
     */
    public function desembolsar(User $user, Prestamo $prestamo): bool
    {
        return $user->hasAnyRole(['Jefe de operaciones', 'super_admin']);
    }

    /**
     * Rechazar (role gate only): JC or SA.
     * State validity is checked separately by the controller (returns 422 on invalid state).
     */
    public function rechazar(User $user, Prestamo $prestamo): bool
    {
        return $user->hasAnyRole(['Jefe de creditos', 'super_admin']);
    }

    /**
     * Reformular: Rechazado → Reformulado (Asesor, JC, super_admin).
     */
    public function reformular(User $user, Prestamo $prestamo): bool
    {
        return $prestamo->puedeReformular()
            && $user->hasAnyRole(['Asesor', 'Jefe de creditos', 'super_admin']);
    }

    /**
     * Reenviar: Reformulado → Pendiente (Asesor, super_admin).
     * State check folded in, matching this Policy's established `reformular`/
     * `condonarMora` convention (unlike `aprobar/firmar/desembolsar/rechazar`,
     * which keep state checks inline at the caller).
     */
    public function reenviar(User $user, Prestamo $prestamo): bool
    {
        return $prestamo->estado === Prestamo::ESTADO_REFORMULADO
            && $user->hasAnyRole(['Asesor', 'super_admin']);
    }

    /**
     * Cancelar: Activo/Al_Día/En_Mora → Cancelado (JC, super_admin).
     */
    public function cancelar(User $user, Prestamo $prestamo): bool
    {
        return $prestamo->puedeCancelar()
            && $user->hasAnyRole(['Jefe de creditos', 'super_admin']);
    }

    /**
     * Reducir monto: Aprobado/Firmado (JC, JO, super_admin).
     */
    public function reducirMonto(User $user, Prestamo $prestamo): bool
    {
        return $prestamo->puedeReducirMonto()
            && $user->hasAnyRole(['Jefe de creditos', 'Jefe de operaciones', 'super_admin']);
    }

    /**
     * Separar cliente de grupo (JC, super_admin).
     */
    public function separarCliente(User $user, Prestamo $prestamo): bool
    {
        return in_array($prestamo->estado, Prestamo::ESTADOS_ACTIVOS)
            && $user->hasAnyRole(['Jefe de creditos', 'super_admin']);
    }

    /**
     * Reagrupar cliente en otro grupo (JC, super_admin).
     */
    public function reagrupar(User $user, Prestamo $prestamo): bool
    {
        return in_array($prestamo->estado, Prestamo::ESTADOS_ACTIVOS)
            && $user->hasAnyRole(['Jefe de creditos', 'super_admin']);
    }

    /**
     * Condonar mora (JC, super_admin).
     */
    public function condonarMora(User $user, Prestamo $prestamo): bool
    {
        return $prestamo->estado === Prestamo::ESTADO_EN_MORA
            && $user->hasAnyRole(['Jefe de creditos', 'super_admin']);
    }
}
