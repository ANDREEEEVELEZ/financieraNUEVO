<?php

namespace App\Observers;

use App\Models\Prestamo;
use App\Models\Cuotas_Grupales;
use Carbon\Carbon;

class PrestamoObserver
{
    /**
     * Handle the Prestamo "created" event.
     */
    public function created(Prestamo $prestamo): void
    {
        $montoTotal = $prestamo->monto_devolver;
        $cantidadCuotas = $prestamo->cantidad_cuotas;
        $montoPorCuota = $montoTotal / $cantidadCuotas;
        $fechaInicio = Carbon::parse($prestamo->fecha_prestamo);

        // Frecuencia de pago
        $dias = match($prestamo->frecuencia) {
            'mensual' => 30,
            'quincenal' => 15,
            'semanal' => 7,
            default => 30,
        };

        for ($i = 1; $i <= $cantidadCuotas; $i++) {
            Cuotas_Grupales::create([
                'prestamo_id' => $prestamo->id,
                'numero_cuota' => $i,
                'monto_cuota_grupal' => round($montoPorCuota, 2),
                'saldo_pendiente' => round($montoPorCuota, 2),
                'fecha_vencimiento' => $fechaInicio->copy()->addDays($dias * $i),
                'estado_cuota_grupal' => 'pendiente',
                'estado_pago' => 'pendiente',
            ]);
        }
    }
}
