<?php

namespace App\Services\Mora;

use App\Models\CuotaIndividual;
use Carbon\Carbon;

/**
 * Estrategia de mora futura: Porcentaje sobre saldo de capital.
 *
 * Lógica: mora = saldo_capital × (tasa_mora / 30) × días_atraso
 *
 * La tasa de mora mensual se lee de config_json.tasa_mora o del
 * campo tasa_mora del ProductoFinanciero.
 *
 * Preparada para uso futuro cuando se requiera cambiar la lógica de mora.
 */
class MoraPorcentualSaldoCapital implements MoraCalculatorInterface
{
    public function calcular(
        CuotaIndividual $cuota,
        int $integrantes,
        Carbon $fechaCalculo,
        array $config = []
    ): float {
        if ($cuota->estado === 'pagada' || $cuota->fecha_vencimiento >= $fechaCalculo) {
            return 0;
        }

        $diasAtraso = $fechaCalculo->diffInDays($cuota->fecha_vencimiento);
        $tasaMoraMensual = $config['tasa_mora'] ?? 0.05; // 5% mensual default
        $tasaMoraDiaria = $tasaMoraMensual / 30;

        $moraBase = (float) $cuota->saldo_capital * $tasaMoraDiaria * $diasAtraso;

        // Restar condonaciones de mora existentes
        $condonaciones = $cuota->ajustes()
            ->where('tipo', 'condonacion_mora')
            ->sum('monto_ajuste');

        return max(0, round($moraBase - $condonaciones, 2));
    }
}
