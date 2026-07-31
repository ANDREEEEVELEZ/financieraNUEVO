<?php

namespace App\Domain\Mora\Strategies;

/**
 * Fórmula plana histórica de nivel grupal: S/ 1 por integrante por día de
 * atraso. Encapsula exactamente la fórmula que vivía en
 * Mora::calcularMontoMora() (integrantes * diasAtraso * 1).
 */
final class MoraFlatPorIntegranteStrategy implements MoraCalculationStrategy
{
    public function calcular(MoraCalculoInput $input): float
    {
        $integrantes = $input->numeroIntegrantes ?? 0;

        return $integrantes * $input->diasAtraso * 1;
    }
}
