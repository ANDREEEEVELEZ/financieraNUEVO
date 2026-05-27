<?php

namespace App\Contracts;

use App\Models\Retanqueo;

interface RetanqueoEjecucionInterface
{
    /**
     * Executes an approved retanqueo: activates / creates the new loan,
     * generates group cuotas, manages group membership, and updates the old loan.
     *
     * @param int   $retanqueoId
     * @param array $datosCuenta  Optional: [titular_cuenta_desembolso, numero_cuenta_desembolso]
     *
     * @throws \Exception when retanqueo not found or not in 'aprobado' state
     */
    public function ejecutarRetanqueo(int $retanqueoId, array $datosCuenta = []): Retanqueo;
}
