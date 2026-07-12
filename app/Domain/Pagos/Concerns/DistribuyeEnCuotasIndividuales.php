<?php

namespace App\Domain\Pagos\Concerns;

use App\Models\AplicacionPago;
use App\Models\CuotaIndividual;
use App\Models\CuotasGrupales;
use App\Models\Pago;
use Illuminate\Support\Facades\Log;

/**
 * Waterfall applicator (interés primero, luego capital) compartido entre
 * PagoService (cobranza real) y RegistrarCoberturaRetanqueoAction (cobertura
 * de retanqueo) — SDD core-contable-seguridad, Slice C, decision D6. Evita que
 * ambos flujos diverjan en cómo se distribuye un monto entre las
 * CuotaIndividual de una misma CuotasGrupales.
 */
trait DistribuyeEnCuotasIndividuales
{
    /**
     * Distribuye el monto de la cuota pagada entre las CuotaIndividual del mismo número.
     * Crea un AplicacionPago por cada integrante y actualiza saldos (capital + interés).
     *
     * @param  string  $tipoAplicacion  'cobranza' (pago real) o 'retanqueo' (cobertura).
     */
    private function distribuirEnCuotasIndividuales(
        Pago $pago,
        CuotasGrupales $cuotaGrupal,
        string $montoParaCuota,
        string $montoParaMora,
        string $tipoAplicacion = 'cobranza'
    ): void {
        $cuotasIndividuales = CuotaIndividual::where('prestamo_id', $cuotaGrupal->prestamo_id)
            ->where('numero_cuota', $cuotaGrupal->numero_cuota)
            ->where('estado', '!=', 'pagada')
            ->lockForUpdate()
            ->get();

        if ($cuotasIndividuales->isEmpty()) {
            Log::warning('DistribuyeEnCuotasIndividuales: Sin CuotaIndividual para distribuir', [
                'prestamo_id' => $cuotaGrupal->prestamo_id,
                'numero_cuota' => $cuotaGrupal->numero_cuota,
                'tipo_aplicacion' => $tipoAplicacion,
            ]);

            return;
        }

        // Total de capital + interés de todas las cuotas individuales de este número
        $totalSaldo = $cuotasIndividuales->sum(
            fn ($ci) => (float) $ci->saldo_capital + (float) $ci->saldo_interes
        );

        if ($totalSaldo <= 0) {
            return;
        }

        foreach ($cuotasIndividuales as $cuotaInd) {
            $saldoEsteIntegrante = (float) $cuotaInd->saldo_capital + (float) $cuotaInd->saldo_interes;
            $proporcion = $saldoEsteIntegrante / $totalSaldo;

            // — Capital e interés proporcional —
            $montoAplicadoCapital = '0.00';
            $montoAplicadoInteres = '0.00';
            $montoAplicadoMora = '0.00';

            if (bccomp($montoParaCuota, '0.00', 2) > 0) {
                $montoTotal = bcmul($montoParaCuota, number_format($proporcion, 10, '.', ''), 2);

                // Primero cubre el interés, el resto va a capital
                $saldoInteres = (string) $cuotaInd->saldo_interes;
                if (bccomp($montoTotal, $saldoInteres, 2) >= 0) {
                    $montoAplicadoInteres = $saldoInteres;
                    $montoAplicadoCapital = bcsub($montoTotal, $saldoInteres, 2);
                    // No puede exceder el saldo de capital
                    if (bccomp($montoAplicadoCapital, (string) $cuotaInd->saldo_capital, 2) > 0) {
                        $montoAplicadoCapital = (string) $cuotaInd->saldo_capital;
                    }
                } else {
                    $montoAplicadoInteres = $montoTotal;
                }
            }

            if (bccomp($montoParaMora, '0.00', 2) > 0) {
                $montoAplicadoMora = bcmul($montoParaMora, number_format($proporcion, 10, '.', ''), 2);
            }

            // Crear AplicacionPago
            AplicacionPago::create([
                'pago_id' => $pago->id,
                'cuota_id' => $cuotaInd->id,
                'tipo_aplicacion' => $tipoAplicacion,
                'monto_aplicado_capital' => $montoAplicadoCapital,
                'monto_aplicado_interes' => $montoAplicadoInteres,
                'monto_aplicado_mora' => $montoAplicadoMora,
                'fecha_aplicacion' => $pago->fecha_pago ?? now(),
            ]);

            // Actualizar saldos de la CuotaIndividual
            $nuevoCapital = max(0.0, (float) bcsub((string) $cuotaInd->saldo_capital, $montoAplicadoCapital, 2));
            $nuevoInteres = max(0.0, (float) bcsub((string) $cuotaInd->saldo_interes, $montoAplicadoInteres, 2));

            $cuotaInd->saldo_capital = $nuevoCapital;
            $cuotaInd->saldo_interes = $nuevoInteres;

            if ($nuevoCapital == 0.0 && $nuevoInteres == 0.0) {
                $cuotaInd->estado = 'pagada';
            }

            $cuotaInd->save();
        }

        Log::info('DistribuyeEnCuotasIndividuales: Pago distribuido entre integrantes', [
            'pago_id' => $pago->id,
            'cuota_grupal' => $cuotaGrupal->numero_cuota,
            'integrantes' => $cuotasIndividuales->count(),
            'tipo_aplicacion' => $tipoAplicacion,
        ]);
    }
}
