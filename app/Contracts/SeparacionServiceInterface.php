<?php

namespace App\Contracts;

use App\Models\SeparacionCliente;

interface SeparacionServiceInterface
{
    /**
     * Executes the separation of a moroso client from a group loan.
     *
     * @param int    $prestamoOrigenId  ID of the active group loan.
     * @param int    $clienteId         ID of the client to separate.
     * @param int    $ejecutadoPorId    ID of the user executing the action (JC or JO).
     * @param string $motivo            Justification for the separation.
     *
     * @return SeparacionCliente The audit record created with loaded relations.
     *
     * @throws \RuntimeException
     */
    public function separar(
        int    $prestamoOrigenId,
        int    $clienteId,
        int    $ejecutadoPorId,
        string $motivo,
    ): SeparacionCliente;
}
