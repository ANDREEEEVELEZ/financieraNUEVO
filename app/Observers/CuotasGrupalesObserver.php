<?php

namespace App\Observers;

use App\Models\CuotasGrupales;
use App\Models\Prestamo;
use App\Models\PrestamoIndividual;

class CuotasGrupalesObserver
{
    public function updated(CuotasGrupales $cuota)
    {
        // Siempre que se actualiza una cuota, verificar si todas están pagadas
        $prestamo = $cuota->prestamo;
        if ($prestamo) {
            $todasPagadas = $prestamo->cuotasGrupales()->where('estado_pago', '!=', 'Pagado')->count() === 0;
            if ($todasPagadas && $prestamo->estado !== 'Finalizado') {
                // Cambiar estado del préstamo y de los préstamos individuales a 'Finalizado'
                $prestamo->estado = 'Finalizado';
                $prestamo->save();
                PrestamoIndividual::where('prestamo_id', $prestamo->id)->update(['estado' => 'Finalizado']);
            }
        }
    }
}
