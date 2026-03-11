<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

/**
 * Servicio central de auditoría.
 *
 * Registra acciones críticas del sistema en la tabla audit_logs.
 * Debe ser invocado dentro de DB::transaction() para garantizar
 * que el registro de auditoría se crea atómicamente con la acción.
 *
 * @example
 *   AuditService::registrar('revertir_pago', $pago,
 *       ['estado_pago' => 'aprobado'],
 *       ['estado_pago' => 'revertido'],
 *       'Pago duplicado detectado'
 *   );
 */
class AuditService
{
    /**
     * Registra una acción auditable.
     *
     * @param string $accion          Identificador de la acción (ej: 'revertir_pago')
     * @param Model  $modelo          Modelo afectado (relación polimórfica)
     * @param array  $datosAnteriores Estado previo al cambio
     * @param array  $datosNuevos     Estado posterior al cambio
     * @param string $motivo          Justificación de la acción
     */
    public static function registrar(
        string $accion,
        Model $modelo,
        array $datosAnteriores,
        array $datosNuevos,
        string $motivo = ''
    ): AuditLog {
        return AuditLog::create([
            'user_id' => auth()->id(),
            'accion' => $accion,
            'auditable_type' => get_class($modelo),
            'auditable_id' => $modelo->id,
            'datos_anteriores' => $datosAnteriores,
            'datos_nuevos' => $datosNuevos,
            'motivo' => $motivo,
            'ip' => request()?->ip(),
        ]);
    }
}
