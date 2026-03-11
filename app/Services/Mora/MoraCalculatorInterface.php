<?php

namespace App\Services\Mora;

use App\Models\CuotaIndividual;
use Carbon\Carbon;

/**
 * Interfaz para estrategias de cálculo de mora.
 *
 * Permite intercambiar la lógica de mora según el ProductoFinanciero.
 * La estrategia se selecciona via config_json.mora_strategy del producto.
 *
 * Estrategias disponibles:
 *   - 'fija_por_dia_integrante' → MoraFijaPorDiaIntegrante (actual/default)
 *   - 'porcentual_saldo_capital' → MoraPorcentualSaldoCapital (futura)
 */
interface MoraCalculatorInterface
{
    /**
     * Calcula el monto de mora para una cuota individual.
     *
     * @param CuotaIndividual $cuota       Cuota a evaluar
     * @param int             $integrantes Número de integrantes del grupo
     * @param Carbon          $fechaCalculo Fecha para el cálculo (normalmente now())
     * @param array           $config      Config del ProductoFinanciero (mora_monto_diario, tasa_mora, etc.)
     */
    public function calcular(
        CuotaIndividual $cuota,
        int $integrantes,
        Carbon $fechaCalculo,
        array $config = []
    ): float;
}
