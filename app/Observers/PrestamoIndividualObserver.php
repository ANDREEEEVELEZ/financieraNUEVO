<?php

namespace App\Observers;

use App\Models\PrestamoIndividual;
use App\Models\Prestamo;

class PrestamoIndividualObserver
{    public function updated(PrestamoIndividual $prestamoIndividual): void
    {
        \Illuminate\Support\Facades\Log::info('PrestamoIndividualObserver: updated() ejecutado', [
            'prestamo_individual_id' => $prestamoIndividual->id,
            'prestamo_id' => $prestamoIndividual->prestamo_id,
            'monto_prestado_individual' => $prestamoIndividual->monto_prestado_individual
        ]);
        
        $this->recalcularCamposIndividuales($prestamoIndividual, false);
        $this->recalcularTotales($prestamoIndividual->prestamo_id);
    }

    public function created(PrestamoIndividual $prestamoIndividual): void
    {
        $this->recalcularCamposIndividuales($prestamoIndividual, true);
        $this->recalcularTotales($prestamoIndividual->prestamo_id);
    }    public function deleted(PrestamoIndividual $prestamoIndividual): void
    {
        $this->recalcularTotales($prestamoIndividual->prestamo_id);
    }    private function recalcularTotales($prestamoId): void
    {
        if (!$prestamoId) return;

        $prestamo = Prestamo::find($prestamoId);
        if (!$prestamo) return;

        $prestamosIndividuales = PrestamoIndividual::where('prestamo_id', $prestamoId)->get();

        $montoTotalPrestado = $prestamosIndividuales->sum('monto_prestado_individual');
        $montoTotalDevolver = $prestamosIndividuales->sum('monto_devolver_individual');

        \Illuminate\Support\Facades\Log::info('PrestamoIndividualObserver: Recalculando totales', [
            'prestamo_id' => $prestamoId,
            'monto_total_prestado_calculado' => $montoTotalPrestado,
            'monto_total_devolver_calculado' => $montoTotalDevolver,
            'monto_total_prestado_actual' => $prestamo->monto_prestado_total,
            'monto_devolver_actual' => $prestamo->monto_devolver
        ]);

        // Actualizar solo si los valores son diferentes para evitar bucles infinitos
        if ($prestamo->monto_prestado_total != $montoTotalPrestado || $prestamo->monto_devolver != $montoTotalDevolver) {
            $prestamo->updateQuietly([
                'monto_prestado_total' => round($montoTotalPrestado, 2),
                'monto_devolver' => round($montoTotalDevolver, 2),
            ]);
            
            \Illuminate\Support\Facades\Log::info('PrestamoIndividualObserver: Totales actualizados', [
                'prestamo_id' => $prestamoId,
                'nuevo_monto_total_prestado' => round($montoTotalPrestado, 2),
                'nuevo_monto_devolver' => round($montoTotalDevolver, 2)
            ]);
            
            // Actualizar cuotas grupales si existen
            $this->actualizarCuotasGrupales($prestamo, $montoTotalDevolver);
        }
    }
    
    private function actualizarCuotasGrupales(Prestamo $prestamo, $montoTotalDevolver): void
    {
        $cuotasGrupales = $prestamo->cuotasGrupales;
        
        if ($cuotasGrupales->count() > 0 && $prestamo->cantidad_cuotas > 0) {
            $montoPorCuota = $montoTotalDevolver / $prestamo->cantidad_cuotas;
            
            foreach ($cuotasGrupales as $cuota) {
                $montoActual = (float)$cuota->monto_cuota_grupal;
                
                if (abs($montoActual - $montoPorCuota) > 0.01) {
                    $cuota->updateQuietly([
                        'monto_cuota_grupal' => round($montoPorCuota, 2),
                        'saldo_pendiente' => round($montoPorCuota, 2)
                    ]);
                    
                    \Illuminate\Support\Facades\Log::info('PrestamoIndividualObserver: Cuota grupal actualizada', [
                        'cuota_id' => $cuota->id,
                        'numero_cuota' => $cuota->numero_cuota,
                        'monto_anterior' => $montoActual,
                        'monto_nuevo' => round($montoPorCuota, 2)
                    ]);
                }
            }
        }
    }

    private function recalcularCamposIndividuales(PrestamoIndividual $prestamoIndividual, bool $esCreacion = false): void
    {
        // Solo recalcular si cambió el monto prestado individual o es una creación
        if (!$esCreacion && !$prestamoIndividual->isDirty('monto_prestado_individual')) {
            return;
        }

        $monto = floatval($prestamoIndividual->monto_prestado_individual);
        $prestamo = $prestamoIndividual->prestamo;
        
        if (!$prestamo) return;
        
        $tasaInteres = $prestamo->tasa_interes ?? 17;
        $numCuotas = $prestamo->cantidad_cuotas ?? 1;
        
        // Calcular seguro según el monto
        if ($monto <= 400) {
            $seguro = 6;
        } elseif ($monto <= 600) {
            $seguro = 7;
        } elseif ($monto <= 800) {
            $seguro = 8;
        } else {
            $seguro = 9;
        }
        
        // Calcular interés (como monto, no porcentaje)
        $interes = $monto * ($tasaInteres / 100);
        
        // Calcular monto total a devolver individual
        $montoDevolver = $monto + $interes + $seguro;
        
        // Calcular cuota individual
        $cuotaIndividual = $montoDevolver / $numCuotas;
        
        // Actualizar los campos calculados sin disparar eventos
        $prestamoIndividual->updateQuietly([
            'seguro' => round($seguro, 2),
            'interes' => round($interes, 2),
            'monto_devolver_individual' => round($montoDevolver, 2),
            'monto_cuota_prestamo_individual' => round($cuotaIndividual, 2),
        ]);
    }
}
