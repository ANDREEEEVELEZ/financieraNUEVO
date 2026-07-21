<?php

namespace App\Domain\Prestamos\Concerns;

use App\Contracts\SaldoCuotaServiceInterface;
use App\Models\Prestamo;
use Illuminate\Support\Collection;

/**
 * Filtro compartido de "cuotas grupales pendientes con saldo real".
 *
 * Reemplaza el filtro legacy `where('saldo_pendiente', '>', 0)` — la columna
 * fue eliminada en SDD core-contable-seguridad, Slice D (Req 4.1/6) — por una
 * evaluación ledger-derived vía SaldoCuotaService, preservando la semántica
 * exacta que usaban las verificaciones de elegibilidad de retanqueo: cuota con
 * estado_pago != 'pagado' Y saldo total (capital + mora) > 0.
 */
trait FiltraCuotasPendientesConSaldo
{
    protected static function cuotasPendientesConSaldo(Prestamo $prestamo): Collection
    {
        $saldoServicio = app(SaldoCuotaServiceInterface::class);

        return $prestamo->cuotasGrupales()
            ->where('estado_pago', '!=', 'pagado')
            ->get()
            ->filter(fn ($cuota) => bccomp($saldoServicio->saldoTotal($cuota), '0.00', 2) > 0)
            ->values();
    }
}
