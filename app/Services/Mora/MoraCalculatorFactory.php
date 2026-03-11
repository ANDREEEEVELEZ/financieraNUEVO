<?php

namespace App\Services\Mora;

use InvalidArgumentException;

/**
 * Factory para seleccionar la estrategia de cálculo de mora.
 *
 * Lee la clave 'mora_strategy' del config_json del ProductoFinanciero
 * y retorna la implementación correspondiente.
 *
 * @example
 *   $calculator = MoraCalculatorFactory::make('fija_por_dia_integrante');
 *   $mora = $calculator->calcular($cuota, $integrantes, now(), $config);
 */
class MoraCalculatorFactory
{
    /**
     * Mapeo de strategy names a clases.
     * Para agregar una nueva estrategia, agregar aquí y crear la clase.
     */
    private static array $strategies = [
        'fija_por_dia_integrante' => MoraFijaPorDiaIntegrante::class,
        'porcentual_saldo_capital' => MoraPorcentualSaldoCapital::class,
    ];

    /**
     * Crea una instancia del calculador de mora según el nombre de estrategia.
     *
     * @param string $strategy Nombre de la estrategia (clave de config_json)
     * @throws InvalidArgumentException Si la estrategia no existe
     */
    public static function make(string $strategy = 'fija_por_dia_integrante'): MoraCalculatorInterface
    {
        if (!isset(self::$strategies[$strategy])) {
            throw new InvalidArgumentException(
                "Estrategia de mora '{$strategy}' no reconocida. " .
                "Opciones: " . implode(', ', array_keys(self::$strategies))
            );
        }

        $class = self::$strategies[$strategy];
        return new $class();
    }
}
