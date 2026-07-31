<?php

namespace App\Domain\Mora\Strategies;

/**
 * Value Object: entrada unificada para MoraCalculationStrategy.
 *
 * Las dos fórmulas de mora existentes operan a granularidades distintas
 * (grupal: integrantes + días; individual: saldo + tasa + días - condonaciones),
 * por lo que la entrada expone ambos conjuntos de campos como opcionales.
 * Cada estrategia concreta sólo lee los campos que su fórmula necesita.
 *
 * El cálculo de `diasAtraso` (incluyendo la lógica de fecha congelada para
 * mora ya pagada) y la consulta de condonaciones permanecen en los modelos
 * llamantes (`Mora`, `CuotaIndividual`) — son detalles de cómo se obtienen
 * los datos, no de la fórmula en sí.
 */
final class MoraCalculoInput
{
    public function __construct(
        public readonly int $diasAtraso,
        public readonly ?int $numeroIntegrantes = null,
        public readonly float $saldoCapital = 0.0,
        public readonly float $tasaMora = 0.0,
        public readonly float $condonaciones = 0.0,
    ) {
    }
}
