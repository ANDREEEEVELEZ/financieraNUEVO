<?php

namespace App\Domain\Pagos;

use App\Contracts\SaldoCuotaServiceInterface;
use App\Models\CuotasGrupales;
use App\Models\Prestamo;

/**
 * Única fuente autoritativa de saldo/mora para CuotasGrupales.
 *
 * Ledger-derived: toda cifra se deriva de Pago rows con estado_pago='aprobado'
 * (la columna legacy cuotas_grupales.saldo_pendiente fue eliminada en Slice D
 * — nunca fue leída por este servicio). bcmath, escala 2, sin caching.
 */
class SaldoCuotaService implements SaldoCuotaServiceInterface
{
    public function saldoCuota(CuotasGrupales $cuota): string
    {
        $montoCuota = (string) $cuota->monto_cuota_grupal;
        [$pagosAprobados, $moraPagada] = $this->totalesPagosAprobados($cuota);

        // Los pagos se aplican primero a la mora, el resto va a la cuota.
        $aplicadoACuota = bcsub($pagosAprobados, $moraPagada, 2);
        if (bccomp($aplicadoACuota, '0.00', 2) < 0) {
            $aplicadoACuota = '0.00';
        }

        $saldo = bcsub($montoCuota, $aplicadoACuota, 2);

        return bccomp($saldo, '0.00', 2) < 0 ? '0.00' : $saldo;
    }

    public function saldoMora(CuotasGrupales $cuota): string
    {
        if (! $cuota->mora) {
            return '0.00';
        }

        $moraGenerada = number_format(abs($cuota->mora->monto_mora_calculado), 2, '.', '');
        $moraPagada = $this->moraPagada($cuota);

        $saldo = bcsub($moraGenerada, $moraPagada, 2);

        return bccomp($saldo, '0.00', 2) < 0 ? '0.00' : $saldo;
    }

    /**
     * Saldo total pendiente = saldoCuota + saldoMora.
     *
     * Comportamiento INTENCIONAL (decisión de arquitectura del SDD
     * core-contable-seguridad): capital y mora son dos buckets independientes
     * que hacen floor en 0.00 por separado; NO se subsidian entre sí. Un exceso
     * de pago de capital NO absorbe deuda de mora (ni viceversa). Esto difiere
     * de la fórmula legacy pooled-remainder de CuotasGrupales::saldoPendiente(),
     * que agrupaba todo el pago en un único bucket y podía cancelar
     * silenciosamente una deuda de un bucket distinto. Una "fuente única de
     * verdad" no debe hacer esa magia implícita cross-bucket.
     *
     * Devuelve string bcmath (escala 2), no float.
     */
    public function saldoTotal(CuotasGrupales $cuota): string
    {
        return bcadd($this->saldoCuota($cuota), $this->saldoMora($cuota), 2);
    }

    /**
     * Mora ya pagada (histórico) = Σ monto_mora_pagada de los pagos aprobados.
     *
     * Comportamiento INTENCIONAL (decisión de arquitectura del SDD
     * core-contable-seguridad): devuelve la suma real del ledger SIN el guard
     * legacy que retornaba 0 cuando no existía fila Mora. El monto_mora_pagada
     * de cada Pago aprobado es la fuente de verdad de lo efectivamente cobrado
     * en mora; ocultarlo porque la fila Mora fue borrada/reseteada después
     * falsearía el histórico cobrado.
     *
     * Devuelve string bcmath (escala 2), no float.
     */
    public function moraPagada(CuotasGrupales $cuota): string
    {
        [, $moraPagada] = $this->totalesPagosAprobados($cuota);

        return $moraPagada;
    }

    public function saldoTotalGrupo(Prestamo $prestamo): string
    {
        $total = '0.00';

        // withSum evita N+1: una sola query trae, por cuota, la suma de pagos
        // aprobados (monto_pagado / monto_mora_pagada), en vez de 1 query por cuota.
        $cuotas = $prestamo->cuotasGrupales()
            ->with('mora')
            ->withSum(['pagos as monto_pagado_aprobado_sum' => function ($q) {
                $q->where('estado_pago', 'aprobado');
            }], 'monto_pagado')
            ->withSum(['pagos as monto_mora_pagada_aprobado_sum' => function ($q) {
                $q->where('estado_pago', 'aprobado');
            }], 'monto_mora_pagada')
            ->get();

        foreach ($cuotas as $cuota) {
            $total = bcadd($total, $this->saldoTotal($cuota), 2);
        }

        return $total;
    }

    /**
     * Devuelve [pagosAprobados, moraPagada] para la cuota con precisión bcmath.
     *
     * Usa los atributos precomputados por withSum (monto_pagado_aprobado_sum /
     * monto_mora_pagada_aprobado_sum) cuando la cuota fue cargada vía
     * saldoTotalGrupo(); si no existen, cae a una sola query agregada.
     */
    private function totalesPagosAprobados(CuotasGrupales $cuota): array
    {
        // withSum expone el atributo agregado aunque su valor sea NULL (cuota sin
        // pagos aprobados: SUM(...) devuelve NULL). isset() lo trataría como "no
        // eager-loaded" y caería al fallback N+1 en el caso más común (cuota
        // impaga), anulando la optimización batch. array_key_exists distingue
        // "presente-pero-null" (=> 0) de "no cargado".
        $atributos = $cuota->getAttributes();
        if (array_key_exists('monto_pagado_aprobado_sum', $atributos)
            || array_key_exists('monto_mora_pagada_aprobado_sum', $atributos)) {
            return [
                number_format((float) $cuota->monto_pagado_aprobado_sum, 2, '.', ''),
                number_format((float) $cuota->monto_mora_pagada_aprobado_sum, 2, '.', ''),
            ];
        }

        $fila = $cuota->pagos()
            ->where('estado_pago', 'aprobado')
            ->selectRaw('COALESCE(SUM(monto_pagado), 0) as total_pagado, COALESCE(SUM(monto_mora_pagada), 0) as total_mora_pagada')
            ->first();

        return [
            number_format((float) ($fila->total_pagado ?? 0), 2, '.', ''),
            number_format((float) ($fila->total_mora_pagada ?? 0), 2, '.', ''),
        ];
    }
}
