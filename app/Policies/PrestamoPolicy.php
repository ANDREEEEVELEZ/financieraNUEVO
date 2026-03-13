<?php

namespace App\Policies;

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

    public function view(User $user, Prestamo $prestamo): bool
    {
        return $user->can('view_prestamo');
    }

    public function create(User $user): bool
    {
        return $user->can('create_prestamo');
    }

    public function update(User $user, Prestamo $prestamo): bool
    {
        return $user->can('update_prestamo');
    }

    public function delete(User $user, Prestamo $prestamo): bool
    {
        return $user->can('delete_prestamo');
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

    // ─── Transiciones de estado ─────────────────────────────────────

    /**
     * Aprobar: Pendiente → Aprobado (JC, super_admin).
     */
    public function aprobar(User $user, Prestamo $prestamo): bool
    {
        return $prestamo->puedeSerAprobado()
            && $user->hasAnyRole(['Jefe de creditos', 'super_admin']);
    }

    /**
     * Firmar contrato: Aprobado → Firmado (Asesor).
     */
    public function firmar(User $user, Prestamo $prestamo): bool
    {
        return $prestamo->puedeFirmar()
            && $user->hasAnyRole(['Asesor', 'super_admin']);
    }

    /**
     * Desembolsar: Firmado → Activo (JO, super_admin).
     */
    public function desembolsar(User $user, Prestamo $prestamo): bool
    {
        return $prestamo->puedeDesembolsar()
            && $user->hasAnyRole(['Jefe de operaciones', 'super_admin']);
    }

    /**
     * Rechazar: Pendiente → Rechazado (JC, super_admin).
     */
    public function rechazar(User $user, Prestamo $prestamo): bool
    {
        return $prestamo->puedeSerRechazado()
            && $user->hasAnyRole(['Jefe de creditos', 'super_admin']);
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
