<?php

namespace App\Domain\Prestamos\Strategies;

use App\Models\Prestamo;

interface ElegibilidadRetanqueoStrategy
{
    /**
     * Returns true when the given prestamo is eligible for retanqueo.
     */
    public function esElegible(Prestamo $prestamo): bool;
}
