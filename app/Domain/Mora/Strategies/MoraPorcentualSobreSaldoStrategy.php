<?php

namespace App\Domain\Mora\Strategies;

/**
 * Fórmula porcentual histórica de nivel individual: saldo_capital x
 * (tasa_mora / 30) x días de atraso, neta de condonaciones, piso en 0.
 * Encapsula exactamente la fórmula que vivía en
 * CuotaIndividual::moraCalculada().
 */
final class MoraPorcentualSobreSaldoStrategy implements MoraCalculationStrategy
{
    public function calcular(MoraCalculoInput $input): float
    {
        $tasaMoraDiaria = $input->tasaMora / 30;
        $moraBase = $input->saldoCapital * $tasaMoraDiaria * $input->diasAtraso;

        return max(0, $moraBase - $input->condonaciones);
    }
}
