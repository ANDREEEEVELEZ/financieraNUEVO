<?php

namespace App\Domain\Prestamos\Strategies;

use App\Domain\Prestamos\Concerns\FiltraCuotasPendientesConSaldo;
use App\Models\Prestamo;

/**
 * Eligibility strategy for individual prestamos.
 *
 * A prestamo individual is eligible if the client has been punctual from
 * cuota 1 through cuota n-1 (second-to-last): no cuota has a fecha_pago
 * after its fecha_vencimiento for cuotas before the last one.
 *
 * In practice for the current domain, eligibility is controlled by the
 * "exactly 1 cuota pendiente" rule already enforced upstream in
 * RetanqueoQueryService::obtenerGruposElegibles(). This strategy enforces
 * the same rule at the single-prestamo level.
 */
final class ElegibilidadRetanqueoIndividual implements ElegibilidadRetanqueoStrategy
{
    use FiltraCuotasPendientesConSaldo;

    public function esElegible(Prestamo $prestamo): bool
    {
        return self::cuotasPendientesConSaldo($prestamo)->count() === 1;
    }
}
