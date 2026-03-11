<?php

namespace App\Services;

use App\Models\Pago;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Servicio de gestión de pagos.
 *
 * Encapsula operaciones críticas sobre pagos que requieren
 * transacciones atómicas y registro de auditoría.
 *
 * Operaciones:
 *   - Revertir pago aprobado (solo JO, con motivo obligatorio)
 */
class PagoService
{
    /**
     * Revierte un pago previamente aprobado.
     *
     * Solo puede ser ejecutado por JO (Jefe de Operaciones).
     * Requiere motivo obligatorio. Todo opera en DB::transaction().
     *
     * Pasos:
     *   1. Cambia estado_pago de 'aprobado' a 'revertido'
     *   2. Recalcula saldo de la cuota grupal
     *   3. Revierte estado de mora si aplica
     *   4. Registra en audit_logs
     *
     * @param Pago   $pago   Pago a revertir (debe estar en estado 'aprobado')
     * @param string $motivo Justificación obligatoria
     * @throws \Exception Si el pago no está en estado 'aprobado'
     */
    public function revertir(Pago $pago, string $motivo): void
    {
        if ($pago->estado_pago !== 'aprobado') {
            throw new \Exception(
                'Solo se pueden revertir pagos en estado aprobado. ' .
                'Estado actual: ' . $pago->estado_pago
            );
        }

        if (empty(trim($motivo))) {
            throw new \Exception('El motivo es obligatorio para revertir un pago.');
        }

        DB::transaction(function () use ($pago, $motivo) {
            $estadoAnterior = $pago->estado_pago;

            // 1. Cambiar estado del pago
            $pago->update(['estado_pago' => 'revertido']);

            // 2. Recalcular saldo de la cuota grupal
            $cuota = $pago->cuotaGrupal;
            if ($cuota) {
                $this->recalcularSaldoCuota($cuota, $pago);
            }

            // 3. Registrar auditoría
            AuditService::registrar(
                'revertir_pago',
                $pago,
                [
                    'estado_pago' => $estadoAnterior,
                    'monto_pagado' => $pago->monto_pagado,
                    'cuota_grupal_id' => $pago->cuota_grupal_id,
                ],
                [
                    'estado_pago' => 'revertido',
                ],
                $motivo
            );

            Log::info('Pago revertido exitosamente', [
                'pago_id' => $pago->id,
                'motivo' => $motivo,
                'usuario_id' => auth()->id(),
            ]);
        });
    }

    /**
     * Recalcula el saldo de una cuota después de revertir un pago.
     */
    private function recalcularSaldoCuota($cuota, Pago $pagoRevertido): void
    {
        // Recalcular basándose solo en pagos que siguen aprobados
        $pagosAprobados = $cuota->pagos()
            ->where('estado_pago', 'aprobado')
            ->where('id', '!=', $pagoRevertido->id)
            ->get();

        $totalPagadoCuota = 0;
        $totalMoraPagada = 0;

        foreach ($pagosAprobados as $pago) {
            $totalPagadoCuota += max(0, $pago->monto_pagado - $pago->monto_mora_pagada);
            $totalMoraPagada += $pago->monto_mora_pagada;
        }

        $montoCuota = (float) $cuota->monto_cuota_grupal;
        $montoMora = $cuota->mora ? abs($cuota->mora->monto_mora_calculado) : 0;

        $saldoCuotaPendiente = max(0, $montoCuota - $totalPagadoCuota);
        $saldoMoraPendiente = max(0, $montoMora - $totalMoraPagada);
        $saldoTotal = $saldoCuotaPendiente + $saldoMoraPendiente;

        // Actualizar estado de cuota
        $cuota->saldo_pendiente = $saldoTotal;
        if ($saldoTotal == 0) {
            $cuota->estado_pago = 'pagado';
            $cuota->estado_cuota_grupal = 'cancelada';
        } elseif ($totalPagadoCuota > 0) {
            $cuota->estado_pago = 'parcial';
            $cuota->estado_cuota_grupal = $saldoMoraPendiente > 0 ? 'mora' : 'vigente';
        } else {
            $cuota->estado_pago = 'pendiente';
            $cuota->estado_cuota_grupal = $cuota->mora ? 'mora' : 'vigente';
        }
        $cuota->save();

        // Actualizar estado de mora si aplica
        if ($cuota->mora) {
            if ($saldoMoraPendiente == 0 && $saldoCuotaPendiente == 0) {
                $cuota->mora->estado_mora = 'pagada';
            } elseif ($totalMoraPagada > 0) {
                $cuota->mora->estado_mora = 'parcialmente_pagada';
            } else {
                $cuota->mora->estado_mora = 'pendiente';
            }
            $cuota->mora->save();
        }

        // Recalcular estado del préstamo
        $prestamo = $cuota->prestamo;
        if ($prestamo) {
            $prestamo->verificarYActualizarEstado();
        }
    }
}
