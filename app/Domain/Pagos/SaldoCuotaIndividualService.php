<?php

namespace App\Domain\Pagos;

use App\Contracts\SaldoCuotaIndividualServiceInterface;
use App\Models\CuotaIndividual;

/**
 * Única fuente autoritativa de saldo/mora para CuotaIndividual.
 *
 * Ledger-derived, consistente con el patrón de SaldoCuotaService (grupal):
 * capital+interés se leen de las columnas saldo_capital/saldo_interes
 * (mutadas por el waterfall único del Eje 3, nunca quedan negativas); mora
 * se lee de CuotaIndividual::moraCalculada() (Eje 4 — ya neta de lo pagado
 * vía AplicacionPago.monto_aplicado_mora). bcmath, escala 2, sin caching.
 */
class SaldoCuotaIndividualService implements SaldoCuotaIndividualServiceInterface
{
    public function saldoCapitalInteres(CuotaIndividual $cuota): string
    {
        $saldo = bcadd((string) $cuota->saldo_capital, (string) $cuota->saldo_interes, 2);

        return bccomp($saldo, '0.00', 2) < 0 ? '0.00' : $saldo;
    }

    public function saldoMora(CuotaIndividual $cuota): string
    {
        $mora = number_format($cuota->moraCalculada(), 2, '.', '');

        return bccomp($mora, '0.00', 2) < 0 ? '0.00' : $mora;
    }

    /**
     * Saldo total pendiente = saldoCapitalInteres + saldoMora.
     *
     * Comportamiento INTENCIONAL (mismo criterio que
     * SaldoCuotaService::saldoTotal() a nivel grupal): capital+interés y
     * mora son dos buckets independientes que hacen floor en 0.00 por
     * separado antes de sumarse; un exceso de pago de capital NO absorbe
     * deuda de mora (ni viceversa).
     */
    public function saldoTotal(CuotaIndividual $cuota): string
    {
        return bcadd($this->saldoCapitalInteres($cuota), $this->saldoMora($cuota), 2);
    }
}
