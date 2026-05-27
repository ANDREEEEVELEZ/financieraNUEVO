<?php

namespace App\Contracts;

use App\Models\Pago;
use App\Models\Prestamo;

interface PagoServiceInterface
{
    /**
     * Approves a pending pago, distributes in AplicacionPago records,
     * and registers the Ingreso (double-entry bookkeeping).
     *
     * @throws \Exception
     */
    public function aprobarPago(Pago $pago): Pago;

    /**
     * Rejects a pending pago and restores cuota saldos.
     *
     * @throws \Exception
     */
    public function rechazarPago(Pago $pago): Pago;

    /**
     * Reverts an approved pago, removes AplicacionPago records,
     * restores saldos, and creates an inverse Ingreso.
     *
     * @throws \Exception
     */
    public function revertirPago(Pago $pago): bool;

    /**
     * Registers a canonical pago directly against individual cuotas
     * without requiring a CuotaGrupal.
     *
     * @throws \Exception
     */
    public function registrarCanonico(
        Prestamo $prestamo,
        string $montoPagado,
        ?string $codigoOperacion = null,
        ?string $tipoPago = 'efectivo',
        ?string $observaciones = null
    ): Pago;
}
