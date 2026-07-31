<?php

namespace App\Domain\Pagos\Concerns;

use App\Models\AplicacionPago;
use App\Models\CuotaIndividual;
use App\Models\CuotasGrupales;
use App\Models\Pago;
use Illuminate\Support\Facades\Log;

/**
 * Waterfall applicator (interés primero, luego capital) compartido entre
 * PagoService (cobranza real y pago canónico) y RegistrarCoberturaRetanqueoAction
 * (cobertura de retanqueo) — SDD core-contable-seguridad, Slice C, decision D6, y
 * SDD dominio-pagos-mora-retanqueo, Eje 3. Evita que los flujos diverjan en cómo
 * se distribuye un monto entre las CuotaIndividual de una misma CuotasGrupales, y
 * en cómo se aplica interés→capital sobre una CuotaIndividual puntual.
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

            // — Capital, interés y mora proporcionales al integrante —
            $montoTotal = '0.00';
            if (bccomp($montoParaCuota, '0.00', 2) > 0) {
                $montoTotal = bcmul($montoParaCuota, number_format($proporcion, 10, '.', ''), 2);
            }

            $montoMoraProporcional = '0.00';
            if (bccomp($montoParaMora, '0.00', 2) > 0) {
                $montoMoraProporcional = bcmul($montoParaMora, number_format($proporcion, 10, '.', ''), 2);
            }

            // Waterfall interés→capital + AplicacionPago + saldos: primitivo compartido.
            $this->aplicarWaterfallCuotaIndividual(
                $pago,
                $cuotaInd,
                $montoTotal,
                $montoMoraProporcional,
                $tipoAplicacion,
                crearAplicacionSiVacia: true
            );
        }

        Log::info('DistribuyeEnCuotasIndividuales: Pago distribuido entre integrantes', [
            'pago_id' => $pago->id,
            'cuota_grupal' => $cuotaGrupal->numero_cuota,
            'integrantes' => $cuotasIndividuales->count(),
            'tipo_aplicacion' => $tipoAplicacion,
        ]);
    }

    /**
     * Primitivo compartido: aplica el waterfall interés→capital sobre UNA
     * CuotaIndividual puntual — el paso que `distribuirEnCuotasIndividuales()`
     * (split proporcional grupal) y `PagoService::registrarCanonico()`
     * (aplicación secuencial directa, sin CuotasGrupales) tienen en común.
     *
     * Cubre primero el saldo_interes con el monto disponible; el resto va a
     * saldo_capital, topado a su saldo (nunca lo deja negativo). Si el monto
     * disponible excede interés + capital de esta cuota, el excedente NO se
     * aplica aquí — queda a criterio del caller (el split grupal lo descarta
     * porque ya es la porción proporcional exacta; el pago canónico lo
     * recupera vía el remanente devuelto y lo re-imputa a la siguiente cuota).
     *
     * @param  string  $montoDisponibleCuota  Monto ya asignado a ESTA cuota (capital+interés).
     * @param  string  $montoDisponibleMora  Monto de mora ya asignado a ESTA cuota (0.00 si no aplica).
     * @param  bool  $crearAplicacionSiVacia  true: crea el AplicacionPago aunque interés+capital+mora
     *   aplicados sean 0.00 (comportamiento histórico del split grupal — deja rastro de auditoría
     *   incluso en pagos 100% mora). false: solo crea el registro cuando algo fue efectivamente
     *   aplicado (comportamiento histórico de registrarCanonico).
     * @return array{interes: string, capital: string, mora: string} Montos efectivamente aplicados.
     */
    private function aplicarWaterfallCuotaIndividual(
        Pago $pago,
        CuotaIndividual $cuotaInd,
        string $montoDisponibleCuota,
        string $montoDisponibleMora = '0.00',
        string $tipoAplicacion = 'cobranza',
        bool $crearAplicacionSiVacia = true
    ): array {
        $montoAplicadoCapital = '0.00';
        $montoAplicadoInteres = '0.00';
        $montoAplicadoMora = '0.00';

        if (bccomp($montoDisponibleCuota, '0.00', 2) > 0) {
            // Primero cubre el interés, el resto va a capital
            $saldoInteres = (string) $cuotaInd->saldo_interes;
            if (bccomp($montoDisponibleCuota, $saldoInteres, 2) >= 0) {
                $montoAplicadoInteres = $saldoInteres;
                $montoAplicadoCapital = bcsub($montoDisponibleCuota, $saldoInteres, 2);
                // No puede exceder el saldo de capital
                if (bccomp($montoAplicadoCapital, (string) $cuotaInd->saldo_capital, 2) > 0) {
                    $montoAplicadoCapital = (string) $cuotaInd->saldo_capital;
                }
            } else {
                $montoAplicadoInteres = $montoDisponibleCuota;
            }
        }

        if (bccomp($montoDisponibleMora, '0.00', 2) > 0) {
            $montoAplicadoMora = $montoDisponibleMora;
        }

        $huboAplicacion = bccomp($montoAplicadoInteres, '0.00', 2) > 0
            || bccomp($montoAplicadoCapital, '0.00', 2) > 0
            || bccomp($montoAplicadoMora, '0.00', 2) > 0;

        if ($crearAplicacionSiVacia || $huboAplicacion) {
            AplicacionPago::create([
                'pago_id' => $pago->id,
                'cuota_id' => $cuotaInd->id,
                'tipo_aplicacion' => $tipoAplicacion,
                'monto_aplicado_capital' => $montoAplicadoCapital,
                'monto_aplicado_interes' => $montoAplicadoInteres,
                'monto_aplicado_mora' => $montoAplicadoMora,
                'fecha_aplicacion' => $pago->fecha_pago ?? now(),
            ]);
        }

        // Actualizar saldos de la CuotaIndividual
        $nuevoCapital = max(0.0, (float) bcsub((string) $cuotaInd->saldo_capital, $montoAplicadoCapital, 2));
        $nuevoInteres = max(0.0, (float) bcsub((string) $cuotaInd->saldo_interes, $montoAplicadoInteres, 2));

        $cuotaInd->saldo_capital = $nuevoCapital;
        $cuotaInd->saldo_interes = $nuevoInteres;

        if ($nuevoCapital == 0.0 && $nuevoInteres == 0.0) {
            $cuotaInd->estado = 'pagada';
        }

        $cuotaInd->save();

        return [
            'interes' => $montoAplicadoInteres,
            'capital' => $montoAplicadoCapital,
            'mora' => $montoAplicadoMora,
        ];
    }
}
