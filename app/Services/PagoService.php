<?php

namespace App\Services;

use App\Models\Pago;
use App\Models\CuotasGrupales;
use App\Models\CuotaIndividual;
use App\Models\AplicacionPago;
use App\Models\Prestamo;
use App\Models\Ingreso;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class PagoService
{
    /**
     * Aprueba un pago, actualiza saldos con precisión bcmath,
     * distribuye el pago en AplicacionPago por CuotaIndividual (capital + interés),
     * y registra el Ingreso (partida doble).
     *
     * @throws Exception
     */
    public function aprobarPago(Pago $pago): Pago
    {
        return DB::transaction(function () use ($pago) {
            // Bloqueo pesimista
            $pago = Pago::where('id', $pago->id)->lockForUpdate()->firstOrFail();

            if ($pago->estado_pago !== 'pendiente') {
                return $pago;
            }

            $cuota = CuotasGrupales::with(['prestamo', 'mora'])
                ->where('id', $pago->cuota_grupal_id)
                ->lockForUpdate()
                ->firstOrFail();

            $prestamo = $cuota->prestamo;

            if (!$prestamo || !in_array($prestamo->estado, Prestamo::ESTADOS_ACTIVOS)) {
                throw new Exception('Solo se pueden aprobar pagos de préstamos en estado Activo, Al Día o En Mora.');
            }

            $montoCuota   = (string) $cuota->monto_cuota_grupal;
            $montoPagado  = (string) $pago->monto_pagado;

            // — Mora —
            $montoMoraTotal = $cuota->mora
                ? (string) abs($cuota->mora->monto_mora_calculado)
                : '0.00';

            $pagosMoraPrevios = number_format(
                $cuota->pagos()
                    ->where('estado_pago', 'aprobado')
                    ->where('id', '!=', $pago->id)
                    ->sum('monto_mora_pagada'),
                2, '.', ''
            );

            $saldoMoraPorPagar = bcsub($montoMoraTotal, $pagosMoraPrevios, 2);
            if (bccomp($saldoMoraPorPagar, '0.00', 2) < 0) {
                $saldoMoraPorPagar = '0.00';
            }

            $moraPagadaEnEstePago = bccomp($montoPagado, $saldoMoraPorPagar, 2) <= 0
                ? $montoPagado
                : $saldoMoraPorPagar;

            $pago->monto_mora_pagada = $moraPagadaEnEstePago;
            $pago->estado_pago       = 'aprobado';
            $pago->save();

            // Resto va a la cuota
            $montoRestanteParaCuota = bcsub($montoPagado, $moraPagadaEnEstePago, 2);
            if (bccomp($montoRestanteParaCuota, '0.00', 2) < 0) {
                $montoRestanteParaCuota = '0.00';
            }

            // — Saldos cuota grupal —
            $pagosAprobados   = $cuota->pagos()->where('estado_pago', 'aprobado')->get();
            $totalPagadoCuota = '0.00';
            foreach ($pagosAprobados as $pAprobado) {
                $diff = bcsub((string) $pAprobado->monto_pagado, (string) $pAprobado->monto_mora_pagada, 2);
                if (bccomp($diff, '0.00', 2) > 0) {
                    $totalPagadoCuota = bcadd($totalPagadoCuota, $diff, 2);
                }
            }

            $saldoCuotaPendiente = bcsub($montoCuota, $totalPagadoCuota, 2);
            if (bccomp($saldoCuotaPendiente, '0.00', 2) < 0) {
                $saldoCuotaPendiente = '0.00';
            }

            $moraTotalPagada    = bcadd($pagosMoraPrevios, $moraPagadaEnEstePago, 2);
            $saldoMoraPendiente = bcsub($montoMoraTotal, $moraTotalPagada, 2);
            if (bccomp($saldoMoraPendiente, '0.00', 2) < 0) {
                $saldoMoraPendiente = '0.00';
            }

            $saldoTotalPendiente = bcadd($saldoCuotaPendiente, $saldoMoraPendiente, 2);

            // Actualizar mora
            if ($cuota->mora) {
                $cuota->mora->estado_mora = match (true) {
                    bccomp($saldoMoraPendiente, '0.00', 2) === 0 && bccomp($saldoCuotaPendiente, '0.00', 2) === 0 => 'pagada',
                    bccomp($saldoMoraPendiente, '0.00', 2) > 0  && bccomp($saldoCuotaPendiente, '0.00', 2) === 0 => 'pendiente',
                    default => 'parcialmente_pagada',
                };
                $cuota->mora->save();
            }

            // Actualizar cuota grupal
            $cuota->saldo_pendiente   = $saldoTotalPendiente;
            $cuota->estado_pago       = bccomp($saldoTotalPendiente, '0.00', 2) === 0 ? 'pagado' : 'parcial';
            $cuota->estado_cuota_grupal = bccomp($saldoTotalPendiente, '0.00', 2) === 0
                ? 'cancelada'
                : (bccomp($saldoMoraPendiente, '0.00', 2) > 0 ? 'mora' : 'vigente');
            $cuota->save();

            // ──────────────────────────────────────────────────────────────
            // V2: Distribuir en CuotaIndividual + AplicacionPago
            // ──────────────────────────────────────────────────────────────
            $this->distribuirEnCuotasIndividuales(
                $pago,
                $cuota,
                $montoRestanteParaCuota,
                $moraPagadaEnEstePago
            );

            // Ingreso (partida doble)
            if (!$pago->ingreso) {
                Ingreso::create([
                    'tipo_ingreso' => 'pago de cuota de grupo',
                    'pago_id'      => $pago->id,
                    'grupo_id'     => $prestamo->grupo_id,
                    'fecha_hora'   => now(),
                    'descripcion'  => 'PAGO APROBADO CUOTA #' . $cuota->numero_cuota . ' (PRESTAMO ID ' . $prestamo->id . ')',
                    'monto'        => $pago->monto_pagado,
                ]);
            }

            $prestamo->verificarYActualizarEstado();

            return $pago;
        });
    }

    /**
     * Distribuye el monto de la cuota pagada entre las CuotaIndividual del mismo número.
     * Crea un AplicacionPago por cada integrante y actualiza saldos (capital + interés).
     */
    private function distribuirEnCuotasIndividuales(
        Pago $pago,
        CuotasGrupales $cuotaGrupal,
        string $montoParaCuota,
        string $montoParaMora
    ): void {
        $cuotasIndividuales = CuotaIndividual::where('prestamo_id', $cuotaGrupal->prestamo_id)
            ->where('numero_cuota', $cuotaGrupal->numero_cuota)
            ->where('estado', '!=', 'pagada')
            ->lockForUpdate()
            ->get();

        if ($cuotasIndividuales->isEmpty()) {
            Log::warning('PagoService: Sin CuotaIndividual para distribuir', [
                'prestamo_id'  => $cuotaGrupal->prestamo_id,
                'numero_cuota' => $cuotaGrupal->numero_cuota,
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
            $montoAplicadoMora    = '0.00';

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
                'pago_id'                => $pago->id,
                'cuota_id'               => $cuotaInd->id,
                'monto_aplicado_capital' => $montoAplicadoCapital,
                'monto_aplicado_interes' => $montoAplicadoInteres,
                'monto_aplicado_mora'    => $montoAplicadoMora,
                'fecha_aplicacion'       => $pago->fecha_pago ?? now(),
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

        Log::info('PagoService: Pago distribuido entre integrantes', [
            'pago_id'      => $pago->id,
            'cuota_grupal' => $cuotaGrupal->numero_cuota,
            'integrantes'  => $cuotasIndividuales->count(),
        ]);
    }

    /**
     * Rechaza un pago y restablece la cuota grupal usando bcmath.
     *
     * @throws Exception
     */
    public function rechazarPago(Pago $pago): Pago
    {
        return DB::transaction(function () use ($pago) {
            $pago = Pago::where('id', $pago->id)->lockForUpdate()->firstOrFail();

            if ($pago->estado_pago !== 'pendiente') {
                return $pago;
            }

            $cuota = CuotasGrupales::with(['prestamo', 'mora'])
                ->where('id', $pago->cuota_grupal_id)
                ->lockForUpdate()
                ->firstOrFail();

            $prestamo = $cuota->prestamo;
            if (!$prestamo || !in_array($prestamo->estado, Prestamo::ESTADOS_ACTIVOS)) {
                throw new Exception('Solo se pueden rechazar pagos de préstamos en estado Activo, Al Día o En Mora.');
            }

            $pago->estado_pago = 'rechazado';
            $pago->save();

            $totalAPagar = (string) $cuota->monto_cuota_grupal;
            $montoMora   = $cuota->mora ? (string) abs($cuota->mora->monto_mora_calculado) : '0.00';
            $deudaTotal  = bcadd($totalAPagar, $montoMora, 2);

            $totalPagado = number_format(
                $cuota->pagos()->where('estado_pago', 'aprobado')->where('id', '!=', $pago->id)->sum('monto_pagado'),
                2, '.', ''
            );

            if (bccomp($totalPagado, '0.00', 2) === 0) {
                $cuota->estado_pago        = 'pendiente';
                $cuota->saldo_pendiente    = $totalAPagar;
                $cuota->estado_cuota_grupal = $cuota->mora ? 'mora' : 'vigente';
                if ($cuota->mora) {
                    $cuota->mora->estado_mora = 'pendiente';
                    $cuota->mora->save();
                }
            } elseif (bccomp($totalPagado, $deudaTotal, 2) >= 0) {
                $cuota->saldo_pendiente    = '0.00';
                $cuota->estado_pago        = 'pagado';
                $cuota->estado_cuota_grupal = 'cancelada';
                if ($cuota->mora) {
                    $cuota->mora->estado_mora = 'pagada';
                    $cuota->mora->save();
                }
            } else {
                $cuota->saldo_pendiente    = bcsub($deudaTotal, $totalPagado, 2);
                $cuota->estado_pago        = 'parcial';
                $cuota->estado_cuota_grupal = $cuota->mora ? 'mora' : 'vigente';
                if ($cuota->mora) {
                    $cuota->mora->estado_mora = 'parcial';
                    $cuota->mora->save();
                }
            }

            $cuota->save();
            $prestamo->verificarYActualizarEstado();

            return $pago;
        });
    }

    /**
     * Revierte un pago aprobado:
     * - Elimina las AplicacionPago y restaura saldos en CuotaIndividual
     * - Recalcula saldos en CuotaGrupal
     * - Crea Ingreso negativo (partida doble inversa)
     */
    public function revertirPago(Pago $pago): bool
    {
        return DB::transaction(function () use ($pago) {
            $pago = Pago::where('id', $pago->id)->lockForUpdate()->firstOrFail();

            if ($pago->estado_pago !== 'aprobado') {
                return false;
            }

            $pagoOriginalMonto = $pago->monto_pagado;

            // Restaurar CuotaIndividual desde AplicacionPago
            AplicacionPago::where('pago_id', $pago->id)
                ->with('cuota')
                ->get()
                ->each(function (AplicacionPago $ap) {
                    $cuotaInd = $ap->cuota;
                    if ($cuotaInd) {
                        $cuotaInd->saldo_capital = bcadd((string) $cuotaInd->saldo_capital, (string) $ap->monto_aplicado_capital, 2);
                        $cuotaInd->saldo_interes = bcadd((string) $cuotaInd->saldo_interes, (string) $ap->monto_aplicado_interes, 2);
                        $cuotaInd->estado        = 'pendiente';
                        $cuotaInd->save();
                    }
                    $ap->delete();
                });

            $pago->estado_pago      = 'pendiente';
            $pago->monto_mora_pagada = 0;
            $pago->save();

            $cuota = CuotasGrupales::with(['prestamo', 'mora'])
                ->where('id', $pago->cuota_grupal_id)
                ->lockForUpdate()
                ->firstOrFail();

            $totalAPagar = (string) $cuota->monto_cuota_grupal;
            $montoMora   = $cuota->mora ? (string) abs($cuota->mora->monto_mora_calculado) : '0.00';
            $deudaTotal  = bcadd($totalAPagar, $montoMora, 2);

            $totalPagado = number_format(
                $cuota->pagos()->where('estado_pago', 'aprobado')->where('id', '!=', $pago->id)->sum('monto_pagado'),
                2, '.', ''
            );

            $saldoPendiente = bcsub($deudaTotal, $totalPagado, 2);
            if (bccomp($saldoPendiente, '0.00', 2) < 0) {
                $saldoPendiente = '0.00';
            }

            $cuota->saldo_pendiente    = $saldoPendiente;
            $cuota->estado_pago        = bccomp($totalPagado, '0.00', 2) <= 0 ? 'pendiente' : 'parcial';
            $cuota->estado_cuota_grupal = $cuota->mora ? 'mora' : 'vigente';
            $cuota->save();

            if ($cuota->mora) {
                $cuota->mora->estado_mora = 'pendiente';
                $cuota->mora->save();
            }

            // Partida doble inversa
            $prestamo = $cuota->prestamo;
            Ingreso::create([
                'tipo_ingreso' => 'pago de cuota de grupo',
                'pago_id'      => $pago->id,
                'grupo_id'     => $prestamo->grupo_id ?? null,
                'fecha_hora'   => now(),
                'descripcion'  => 'ANULACIÓN DE PAGO REVERTIDO CUOTA #' . $cuota->numero_cuota . ' (PRESTAMO ID ' . ($prestamo->id ?? 'N/A') . ')',
                'monto'        => bcmul((string) $pagoOriginalMonto, '-1', 2),
            ]);

            if ($prestamo) {
                $prestamo->verificarYActualizarEstado();
            }

            return true;
        });
    }

    /**
     * Registra un pago canónico directamente sobre el Préstamo, sin requerir CuotasGrupales.
     * 
     * @param Prestamo $prestamo
     * @param string $montoPagado
     * @param string|null $codigoOperacion
     * @param string|null $tipoPago
     * @param string|null $observaciones
     * @return Pago
     * @throws Exception
     */
    public function registrarCanonico(
        Prestamo $prestamo,
        string $montoPagado,
        ?string $codigoOperacion = null,
        ?string $tipoPago = 'efectivo',
        ?string $observaciones = null
    ): Pago {
        return DB::transaction(function () use ($prestamo, $montoPagado, $codigoOperacion, $tipoPago, $observaciones) {
            $prestamo = Prestamo::where('id', $prestamo->id)->lockForUpdate()->firstOrFail();

            if (!in_array($prestamo->estado, Prestamo::ESTADOS_ACTIVOS)) {
                throw new Exception('Solo se pueden registrar pagos para préstamos en estado Activo, Al Día o En Mora.');
            }

            // Crear el Pago sin cuota_grupal_id
            $pago = Pago::create([
                'cuota_grupal_id' => null,
                'monto_pagado'    => $montoPagado,
                'codigo_operacion'=> $codigoOperacion,
                'tipo_pago'       => $tipoPago,
                'observaciones'   => $observaciones,
                'estado_pago'     => 'aprobado',
                'fecha_pago'      => now(),
                'monto_mora_pagada' => 0,
            ]);

            $montoRestante = $montoPagado;

            $cuotasPendientes = CuotaIndividual::where('prestamo_id', $prestamo->id)
                ->where('estado', '!=', 'pagada')
                ->orderBy('numero_cuota', 'asc')
                ->lockForUpdate()
                ->get();

            foreach ($cuotasPendientes as $cuotaInd) {
                if (bccomp($montoRestante, '0.00', 2) <= 0) {
                    break;
                }

                $saldoInteres = (string) $cuotaInd->saldo_interes;
                $saldoCapital = (string) $cuotaInd->saldo_capital;
                
                $aplicadoInteres = '0.00';
                $aplicadoCapital = '0.00';

                // Cubrir interés primero
                if (bccomp($saldoInteres, '0.00', 2) > 0) {
                    if (bccomp($montoRestante, $saldoInteres, 2) >= 0) {
                        $aplicadoInteres = $saldoInteres;
                        $montoRestante = bcsub($montoRestante, $saldoInteres, 2);
                    } else {
                        $aplicadoInteres = $montoRestante;
                        $montoRestante = '0.00';
                    }
                }

                // Luego capital
                if (bccomp($montoRestante, '0.00', 2) > 0 && bccomp($saldoCapital, '0.00', 2) > 0) {
                    if (bccomp($montoRestante, $saldoCapital, 2) >= 0) {
                        $aplicadoCapital = $saldoCapital;
                        $montoRestante = bcsub($montoRestante, $saldoCapital, 2);
                    } else {
                        $aplicadoCapital = $montoRestante;
                        $montoRestante = '0.00';
                    }
                }

                if (bccomp($aplicadoInteres, '0.00', 2) > 0 || bccomp($aplicadoCapital, '0.00', 2) > 0) {
                    AplicacionPago::create([
                        'pago_id'                => $pago->id,
                        'cuota_id'               => $cuotaInd->id,
                        'monto_aplicado_capital' => $aplicadoCapital,
                        'monto_aplicado_interes' => $aplicadoInteres,
                        'monto_aplicado_mora'    => '0.00',
                        'fecha_aplicacion'       => $pago->fecha_pago,
                    ]);

                    $nuevoCapital = max(0.0, (float) bcsub($saldoCapital, $aplicadoCapital, 2));
                    $nuevoInteres = max(0.0, (float) bcsub($saldoInteres, $aplicadoInteres, 2));

                    $cuotaInd->saldo_capital = $nuevoCapital;
                    $cuotaInd->saldo_interes = $nuevoInteres;

                    if ($nuevoCapital == 0.0 && $nuevoInteres == 0.0) {
                        $cuotaInd->estado = 'pagada';
                    }

                    $cuotaInd->save();
                }
            }

            // Ingreso (partida doble)
            Ingreso::create([
                'tipo_ingreso' => 'pago canónico',
                'pago_id'      => $pago->id,
                'grupo_id'     => $prestamo->grupo_id ?? null,
                'fecha_hora'   => now(),
                'descripcion'  => 'PAGO CANÓNICO (PRESTAMO ID ' . $prestamo->id . ')',
                'monto'        => $pago->monto_pagado,
            ]);

            $prestamo->verificarYActualizarEstado();

            return $pago;
        });
    }
}
