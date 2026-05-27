<?php

namespace App\Infrastructure\Audit;

use App\Contracts\AuditServiceInterface;
use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

/**
 * Servicio central de auditoría.
 *
 * Registra acciones críticas del sistema en la tabla audit_logs.
 * Debe ser invocado dentro de DB::transaction() para garantizar
 * que el registro de auditoría se crea atómicamente con la acción.
 *
 * Inject AuditServiceInterface via the container — do NOT call this class
 * statically in new code. Legacy static callers (CondonacionMoraService,
 * ReagrupacionParcialService) will be migrated in PR3.
 *
 * @example
 *   // DI (preferred)
 *   $this->auditService->registrar('revertir_pago', $pago, [...], [...]);
 *
 *   // Legacy static callers — will be removed in PR3
 *   AuditService::registrar('revertir_pago', $pago, [...], [...], 'motivo');
 */
class AuditService implements AuditServiceInterface
{
    /**
     * Registers an auditable action.
     *
     * {@inheritDoc}
     */
    public function registrar(
        string $evento,
        Model $modelo,
        array $antes,
        array $despues,
        string $motivo = ''
    ): void {
        AuditLog::create([
            'user_id'          => auth()->id(),
            'accion'           => $evento,
            'auditable_type'   => get_class($modelo),
            'auditable_id'     => $modelo->id,
            'datos_anteriores' => $antes,
            'datos_nuevos'     => $despues,
            'motivo'           => $motivo,
            'ip'               => request()?->ip(),
        ]);
    }
}
