<?php

namespace App\Observers;

use App\Models\CuotasGrupales;
use App\Models\Prestamo;
use App\Models\PrestamoIndividual;
use Illuminate\Support\Facades\Log;

class CuotasGrupalesObserver
{
    public function updated(CuotasGrupales $cuota)
    {
        // Log para debug
        Log::info('CuotasGrupalesObserver: Cuota actualizada', [
            'cuota_id' => $cuota->id,
            'estado_pago' => $cuota->estado_pago,
            'prestamo_id' => $cuota->prestamo_id,
        ]);
        
        // Siempre que se actualiza una cuota, verificar si todas están pagadas
        $prestamo = $cuota->prestamo;
        if ($prestamo) {
            $totalCuotas = $prestamo->cuotasGrupales()->count();
            $cuotasPagadas = $prestamo->cuotasGrupales()->where('estado_pago', 'pagado')->count();
            $cuotasNoPagadas = $prestamo->cuotasGrupales()->where('estado_pago', '!=', 'pagado')->count();
            
            Log::info('CuotasGrupalesObserver: Verificando estado del préstamo', [
                'prestamo_id' => $prestamo->id,
                'estado_actual' => $prestamo->estado,
                'total_cuotas' => $totalCuotas,
                'cuotas_pagadas' => $cuotasPagadas,
                'cuotas_no_pagadas' => $cuotasNoPagadas,
            ]);
            
            $todasPagadas = $cuotasNoPagadas === 0;
            
            if ($todasPagadas && $prestamo->estado !== 'Finalizado') {
                Log::info('CuotasGrupalesObserver: Cambiando estado a Finalizado', [
                    'prestamo_id' => $prestamo->id,
                ]);
                
                // Cambiar estado del préstamo y de los préstamos individuales a 'Finalizado'
                $prestamo->estado = 'Finalizado';
                $prestamo->save();
                
                PrestamoIndividual::where('prestamo_id', $prestamo->id)->update(['estado' => 'Finalizado']);
                
                Log::info('CuotasGrupalesObserver: Estado cambiado exitosamente', [
                    'prestamo_id' => $prestamo->id,
                    'nuevo_estado' => $prestamo->estado,
                ]);
            }
        }
    }
}
