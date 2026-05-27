<?php

namespace App\Contracts;

use Illuminate\Database\Eloquent\Model;

interface AuditServiceInterface
{
    /**
     * Registers an auditable action.
     *
     * @param string $evento          Action identifier (e.g. 'revertir_pago')
     * @param Model  $modelo          Affected model (polymorphic relation)
     * @param array  $antes           State before the change
     * @param array  $despues         State after the change
     * @param string $motivo          Justification for the action
     */
    public function registrar(
        string $evento,
        Model $modelo,
        array $antes,
        array $despues,
        string $motivo = ''
    ): void;
}
