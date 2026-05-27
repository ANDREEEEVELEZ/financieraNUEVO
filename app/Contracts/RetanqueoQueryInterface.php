<?php

namespace App\Contracts;

use Illuminate\Support\Collection;

interface RetanqueoQueryInterface
{
    /**
     * Returns groups eligible for retanqueo.
     * A group is eligible when its active loan has exactly 1 pending cuota.
     */
    public function obtenerGruposElegibles(?int $asesorId = null): Collection;

    /**
     * Returns the current loan state summary needed by the retanqueo flow.
     *
     * @throws \Exception when prestamo is not found or not eligible
     */
    public function calcularEstadoPrestamo(int $prestamoId): array;
}
