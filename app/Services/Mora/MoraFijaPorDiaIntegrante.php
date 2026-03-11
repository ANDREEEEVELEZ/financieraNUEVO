<?php

namespace App\Services\Mora;

use App\Models\CuotaIndividual;
use Carbon\Carbon;

/**
 * Estrategia de mora actual: Monto fijo por día × integrante.
 *
 * Lógica: mora = integrantes × días_atraso × monto_diario (default $1.00)
 *
 * El monto diario se lee de config_json.mora_monto_diario del producto.
 * Si no está definido, usa $1.00 como fallback.
 */
class MoraFijaPorDiaIntegrante implements MoraCalculatorInterface
{
    public function calcular(
        CuotaIndividual $cuota,
        int $integrantes,
        Carbon $fechaCalculo,
        array $config = []
    ): float {
        // Si la cuota está pagada o no está vencida, no hay mora
        if ($cuota->estado === 'pagada' || $cuota->fecha_vencimiento >= $fechaCalculo) {
            return 0;
        }

        $diasAtraso = $fechaCalculo->diffInDays($cuota->fecha_vencimiento);
        $montoDiario = $config['mora_monto_diario'] ?? 1.00;

        $moraBase = $integrantes * $diasAtraso * $montoDiario;

        // Restar condonaciones de mora existentes
        $condonaciones = $cuota->ajustes()
            ->where('tipo', 'condonacion_mora')
            ->sum('monto_ajuste');

        return max(0, $moraBase - $condonaciones);
    }
}
