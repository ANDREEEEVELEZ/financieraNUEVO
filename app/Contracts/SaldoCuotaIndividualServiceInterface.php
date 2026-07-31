<?php

namespace App\Contracts;

use App\Models\CuotaIndividual;

/**
 * Single authoritative source for CuotaIndividual saldo/mora figures.
 *
 * Sibling of SaldoCuotaServiceInterface (grupal) rather than a shared
 * interface: SaldoCuotaServiceInterface's methods are typed against
 * CuotasGrupales/Prestamo and additionally expose saldoTotalGrupo(), a
 * préstamo-level aggregate that has no CuotaIndividual equivalent — forcing
 * a shared contract would mean either widening every parameter to a union
 * type (losing the compile-time guarantee that a CuotasGrupales method never
 * receives a CuotaIndividual) or leaving saldoTotalGrupo() unimplementable
 * here. A sibling interface keeps both contracts precise while mirroring
 * the same method-naming convention and bcmath-string return contract.
 *
 * capital+interés: leídos de saldo_capital/saldo_interes — ya son
 * ledger-derived porque el waterfall del Eje 3
 * (DistribuyeEnCuotasIndividuales::aplicarWaterfallCuotaIndividual()) las
 * muta consistentemente en cada AplicacionPago, nunca las deja negativas.
 * mora: vía CuotaIndividual::moraCalculada() (Eje 4), ya neta de lo pagado
 * (ledger-derived contra AplicacionPago.monto_aplicado_mora).
 * Returned as bcmath-precision strings (scale 2) — callers cast to float
 * only at the UI edge, same contract as SaldoCuotaServiceInterface.
 */
interface SaldoCuotaIndividualServiceInterface
{
    /**
     * Saldo pendiente de capital + interés de la cuota, sin mora.
     */
    public function saldoCapitalInteres(CuotaIndividual $cuota): string;

    /**
     * Saldo pendiente de mora para la cuota (neto de lo ya pagado).
     */
    public function saldoMora(CuotaIndividual $cuota): string;

    /**
     * Saldo total pendiente: saldoCapitalInteres + saldoMora.
     *
     * Mismo comportamiento INTENCIONAL que SaldoCuotaService::saldoTotal()
     * (grupal): capital+interés y mora son dos buckets independientes que
     * hacen floor en 0.00 por separado; no se subsidian entre sí.
     */
    public function saldoTotal(CuotaIndividual $cuota): string;
}
