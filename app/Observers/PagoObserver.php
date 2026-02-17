<?php

namespace App\Observers;

use App\Models\Pago;
use App\Models\Ingreso;
use App\Models\AplicacionPago;
use App\Models\CuotaIndividual;
use App\Models\MovimientoFinanciero;
use Illuminate\Support\Facades\Log;

class PagoObserver
{
    public function updated(Pago $pago)
    {
        // Verifica si el estado ha cambiado a "aprobado"
        if ($pago->isDirty('estado_pago') && strtolower($pago->estado_pago) === 'aprobado') {
            $this->procesarPagoAprobado($pago);
        }
    }

    /**
     * Procesa un pago aprobado:
     * 1. Crea AplicacionPago para cada cuota individual afectada
     * 2. Actualiza saldos de las cuotas
     * 3. Registra MovimientoFinanciero
     * 4. Crea Ingreso para compatibilidad
     */
    private function procesarPagoAprobado(Pago $pago): void
    {
        Log::info('PagoObserver: Procesando pago aprobado', ['pago_id' => $pago->id]);

        $cuotaGrupal = $pago->cuotaGrupal;
        $grupo = $cuotaGrupal?->prestamo?->grupo;

        // Obtener el cliente que realizó el pago
        $clienteId = $this->determinarClienteDelPago($pago);

        if ($clienteId) {
            // Nuevo sistema: Aplicar a CuotaIndividual
            $this->aplicarPagoACuotasIndividuales($pago, $clienteId);
        }

        // Registrar MovimientoFinanciero
        $this->registrarMovimientoIngreso($pago);

        // Compatibilidad: Crear Ingreso si no existe
        $this->crearIngresoCompatibilidad($pago, $grupo);
    }

    /**
     * Determina qué cliente realizó el pago.
     * Por ahora usa la cuota grupal, pero podría mejorarse en el futuro.
     */
    private function determinarClienteDelPago(Pago $pago): ?int
    {
        // Si el pago tiene detalles, usar el primer cliente
        if ($pago->detalles && $pago->detalles->isNotEmpty()) {
            $primerDetalle = $pago->detalles->first();
            if ($primerDetalle->prestamoIndividual) {
                return $primerDetalle->prestamoIndividual->cliente_id;
            }
        }

        // Intentar obtener desde la cuota grupal (fallback)
        $cuotaGrupal = $pago->cuotaGrupal;
        if ($cuotaGrupal && $cuotaGrupal->prestamo) {
            $prestamo = $cuotaGrupal->prestamo;

            // Si es préstamo individual, devolver el cliente directo
            if ($prestamo->cliente_id) {
                return $prestamo->cliente_id;
            }

            // Si es grupal, buscar en PrestamoIndividual
            $prestamoInd = $prestamo->prestamoIndividual()->first();
            if ($prestamoInd) {
                return $prestamoInd->cliente_id;
            }
        }

        return null;
    }

    /**
     * Aplica el pago a las cuotas individuales del cliente.
     * Usa FIFO: paga primero las cuotas más antiguas.
     */
    private function aplicarPagoACuotasIndividuales(Pago $pago, int $clienteId): void
    {
        // Buscar cuotas pendientes del cliente ordenadas por vencimiento
        $cuotasPendientes = CuotaIndividual::where('cliente_id', $clienteId)
            ->where('estado', '!=', 'pagada')
            ->orderBy('fecha_vencimiento')
            ->get();

        if ($cuotasPendientes->isEmpty()) {
            Log::warning('PagoObserver: No hay cuotas pendientes para el cliente', [
                'pago_id' => $pago->id,
                'cliente_id' => $clienteId,
            ]);
            return;
        }

        $montoRestante = (float) $pago->monto_pagado;

        foreach ($cuotasPendientes as $cuota) {
            if ($montoRestante <= 0)
                break;

            // Calcular mora de esta cuota
            $mora = $cuota->moraCalculada();

            // Prioridad: Mora > Interés > Capital
            $moraAAplicar = min($montoRestante, $mora);
            $montoRestante -= $moraAAplicar;

            $interesAAplicar = min($montoRestante, (float) $cuota->saldo_interes);
            $montoRestante -= $interesAAplicar;

            $capitalAAplicar = min($montoRestante, (float) $cuota->saldo_capital);
            $montoRestante -= $capitalAAplicar;

            // Solo crear aplicación si realmente se aplicó algo
            if ($moraAAplicar > 0 || $interesAAplicar > 0 || $capitalAAplicar > 0) {
                AplicacionPago::create([
                    'pago_id' => $pago->id,
                    'cuota_id' => $cuota->id,
                    'monto_aplicado_capital' => $capitalAAplicar,
                    'monto_aplicado_interes' => $interesAAplicar,
                    'monto_aplicado_mora' => $moraAAplicar,
                    'fecha_aplicacion' => now(),
                ]);

                // Actualizar saldos de la cuota
                $nuevoSaldoCapital = (float) $cuota->saldo_capital - $capitalAAplicar;
                $nuevoSaldoInteres = (float) $cuota->saldo_interes - $interesAAplicar;

                $cuota->update([
                    'saldo_capital' => max(0, $nuevoSaldoCapital),
                    'saldo_interes' => max(0, $nuevoSaldoInteres),
                    'estado' => ($nuevoSaldoCapital <= 0 && $nuevoSaldoInteres <= 0) ? 'pagada' : $cuota->estado,
                ]);

                Log::info('PagoObserver: Aplicación de pago creada', [
                    'pago_id' => $pago->id,
                    'cuota_id' => $cuota->id,
                    'capital' => $capitalAAplicar,
                    'interes' => $interesAAplicar,
                    'mora' => $moraAAplicar,
                ]);
            }
        }

        if ($montoRestante > 0) {
            Log::warning('PagoObserver: Pago mayor que deuda', [
                'pago_id' => $pago->id,
                'sobrante' => $montoRestante,
            ]);
        }
    }

    /**
     * Registra el movimiento financiero de ingreso.
     */
    private function registrarMovimientoIngreso(Pago $pago): void
    {
        $existeMovimiento = MovimientoFinanciero::where('referencia_tipo', Pago::class)
            ->where('referencia_id', $pago->id)
            ->exists();

        if (!$existeMovimiento) {
            MovimientoFinanciero::registrarPagoCuota($pago);
            Log::info('PagoObserver: MovimientoFinanciero creado', ['pago_id' => $pago->id]);
        }
    }

    /**
     * Crea Ingreso para mantener compatibilidad con el sistema anterior.
     */
    private function crearIngresoCompatibilidad(Pago $pago, $grupo): void
    {
        if (!$grupo)
            return;

        // Verifica si ya existe un ingreso para este pago
        if (Ingreso::where('pago_id', $pago->id)->exists())
            return;

        Ingreso::create([
            'tipo_ingreso' => 'pago de cuota de grupo',
            'pago_id' => $pago->id,
            'monto' => $pago->monto_pagado,
            'fecha_hora' => $pago->fecha_pago,
            'grupo_id' => $grupo->id,
            'descripcion' => 'PAGO A CTA DE CUOTA GRUPO ' . $grupo->nombre_grupo .
                ($pago->observaciones ? ' (' . $pago->observaciones . ')' : ''),
        ]);

        Log::info('PagoObserver: Ingreso creado (compatibilidad)', ['pago_id' => $pago->id]);
    }
}

