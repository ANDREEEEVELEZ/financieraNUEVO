<?php

namespace App\Contracts;

use App\Models\CuotasGrupales;
use App\Models\Prestamo;

/**
 * Single authoritative source for CuotasGrupales saldo/mora figures.
 *
 * All values are ledger-derived (approved Pago rows), never read from the
 * (legacy, write-only) cuotas_grupales.saldo_pendiente column. Returned as
 * bcmath-precision strings (scale 2) — callers cast to float only at the UI edge.
 */
interface SaldoCuotaServiceInterface
{
    /**
     * Saldo pendiente de la cuota (capital), sin mora.
     */
    public function saldoCuota(CuotasGrupales $cuota): string;

    /**
     * Saldo pendiente de mora para la cuota.
     */
    public function saldoMora(CuotasGrupales $cuota): string;

    /**
     * Saldo total pendiente: saldoCuota + saldoMora.
     */
    public function saldoTotal(CuotasGrupales $cuota): string;

    /**
     * Monto de mora ya pagado (histórico) para la cuota.
     */
    public function moraPagada(CuotasGrupales $cuota): string;

    /**
     * Agregado a nivel de préstamo: suma de saldoTotal de sus cuotas.
     */
    public function saldoTotalGrupo(Prestamo $prestamo): string;
}
