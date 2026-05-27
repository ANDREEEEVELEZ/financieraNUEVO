<?php

namespace App\Contracts;

use App\Models\Retanqueo;

interface RetanqueoWorkflowInterface
{
    /**
     * Creates a new retanqueo solicitud for the given prestamo.
     *
     * @param int   $prestamoId
     * @param array $participantes       Each entry: [cliente_id, participacion_tipo, monto_solicitado]
     * @param array $datosRetanqueo      Optional: [cantidad_cuotas, monto_cuota, datos_cuenta]
     *
     * @throws \Exception when prestamo not found or not in 'Aprobado' state
     */
    public function crearSolicitudRetanqueo(int $prestamoId, array $participantes, array $datosRetanqueo = []): Retanqueo;

    /**
     * Approves a pending retanqueo solicitud.
     *
     * @throws \Exception when retanqueo not found, not in solicitud_pendiente, or financial guard fails
     */
    public function aprobarRetanqueo(int $retanqueoId, string $observaciones = ''): Retanqueo;

    /**
     * Rejects a pending retanqueo solicitud.
     *
     * @throws \Exception when retanqueo not found or not in solicitud_pendiente state
     */
    public function rechazarRetanqueo(int $retanqueoId, string $motivo = ''): Retanqueo;
}
