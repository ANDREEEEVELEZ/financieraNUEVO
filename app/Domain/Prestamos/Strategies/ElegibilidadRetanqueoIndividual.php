<?php

namespace App\Domain\Prestamos\Strategies;

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
    public function esElegible(Prestamo $prestamo): bool
    {
        $cuotasPendientes = $prestamo->cuotasGrupales()
            ->where('estado_pago', '!=', 'pagado')
            ->where('saldo_pendiente', '>', 0)
            ->count();

        return $cuotasPendientes === 1;
    }
}
