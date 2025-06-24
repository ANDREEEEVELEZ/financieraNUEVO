<?php

namespace App\Observers;

use App\Models\PrestamoIndividual;
use App\Models\Prestamo;

class PrestamoIndividualObserver
{
    public function updated(PrestamoIndividual $prestamoIndividual): void
    {
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
    }

    private function recalcularTotales($prestamoId): void
    {
        if (!$prestamoId) return;

        $prestamo = Prestamo::find($prestamoId);
        if (!$prestamo) return;

        $prestamosIndividuales = PrestamoIndividual::where('prestamo_id', $prestamoId)->get();

        $montoTotalPrestado = $prestamosIndividuales->sum('monto_prestado_individual');
        $montoTotalDevolver = $prestamosIndividuales->sum('monto_devolver_individual');

        // Actualizar solo si los valores son diferentes para evitar bucles infinitos
        if ($prestamo->monto_prestado_total != $montoTotalPrestado || $prestamo->monto_devolver != $montoTotalDevolver) {
            $prestamo->updateQuietly([
                'monto_prestado_total' => round($montoTotalPrestado, 2),
                'monto_devolver' => round($montoTotalDevolver, 2),
            ]);
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
