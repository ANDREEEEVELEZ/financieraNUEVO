<?php

namespace App\Observers;

use App\Models\Pago;
use App\Models\Ingreso;
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
     * 1. Registra MovimientoFinanciero
     * 2. Crea Ingreso para compatibilidad
     *
     * Note: AplicacionPago distribution is handled exclusively by
     * PagoService::distribuirEnCuotasIndividuales() (BCMath proportional).
     * The former FIFO path (aplicarPagoACuotasIndividuales) was removed to
     * eliminate the double-write bug (SR-1).
     */
    private function procesarPagoAprobado(Pago $pago): void
    {
        Log::info('PagoObserver: Procesando pago aprobado', ['pago_id' => $pago->id]);

        $cuotaGrupal = $pago->cuotaGrupal;
        $grupo = $cuotaGrupal?->prestamo?->grupo;

        // Registrar MovimientoFinanciero
        $this->registrarMovimientoIngreso($pago);

        // Compatibilidad: Crear Ingreso si no existe
        $this->crearIngresoCompatibilidad($pago, $grupo);
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
