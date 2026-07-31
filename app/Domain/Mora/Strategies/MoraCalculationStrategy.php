<?php

namespace App\Domain\Mora\Strategies;

/**
 * Strategy pattern para el cálculo del monto de mora, configurable por
 * producto financiero (ver ProductoFinanciero::moraStrategy()).
 *
 * Mismo espíritu que App\Domain\Prestamos\Strategies\ElegibilidadRetanqueoStrategy:
 * no hay una fórmula "ganadora" — ambas implementaciones son reglas de negocio
 * legítimas y seleccionables por producto.
 */
interface MoraCalculationStrategy
{
    /**
     * Calcula el monto de mora a partir de los datos ya resueltos por el
     * llamante (días de atraso, y — según la estrategia — integrantes o
     * saldo/tasa/condonaciones).
     */
    public function calcular(MoraCalculoInput $input): float;
}
