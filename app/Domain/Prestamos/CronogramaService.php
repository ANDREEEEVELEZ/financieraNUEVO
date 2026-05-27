<?php

namespace App\Domain\Prestamos;

use App\Contracts\CronogramaServiceInterface;
use App\Models\ProductoFinanciero;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Servicio para generar y validar cronogramas de pagos.
 *
 * Usado en dos contextos:
 *   1. Preview en el Wizard de creación de solicitud (Asesor/JC)
 *   2. Generación real de cuotas al desembolsar (JO)
 *
 * Valida montos según ciclo del cliente y calcula seguros automáticamente.
 *
 * @example
 *   $servicio = new CronogramaService();
 *   $cronograma = $servicio->generar($producto, $montos, 16, 'semanal', now());
 */
class CronogramaService implements CronogramaServiceInterface
{
    /**
     * Rangos de monto por ciclo (números romanos).
     * Valores default — se sobreescriben con config_json del producto.
     */
    private const CICLOS_DEFAULT = [
        'I' => [400],
        'II' => [400, 500, 600],
        'III' => [400, 500, 600, 700, 800],
        'IV' => [400, 500, 600, 700, 800, 900, 1000],
        'V' => [400, 500, 600, 700, 800, 900, 1000, 1100, 1200],
        'VI' => [400, 500, 600, 700, 800, 900, 1000, 1100, 1200, 1300, 1400],
    ];

    /**
     * Valida que el monto solicitado es válido para el ciclo del cliente.
     */
    public function validarMontoPorCiclo(
        string $ciclo,
        float $monto,
        ProductoFinanciero $producto
    ): bool {
        $ciclos = $producto->getConfig('ciclos', []);

        // Si el producto tiene config de ciclos, usarla
        if (!empty($ciclos) && isset($ciclos[$ciclo])) {
            $montosPermitidos = $ciclos[$ciclo]['montos'] ?? [];
            return in_array($monto, $montosPermitidos);
        }

        // Fallback a valores default
        if (isset(self::CICLOS_DEFAULT[$ciclo])) {
            return in_array($monto, self::CICLOS_DEFAULT[$ciclo]);
        }

        return false;
    }

    /**
     * Obtiene los montos disponibles para un ciclo dado.
     */
    public function obtenerMontosDisponibles(
        string $ciclo,
        ProductoFinanciero $producto
    ): array {
        $ciclos = $producto->getConfig('ciclos', []);

        if (!empty($ciclos) && isset($ciclos[$ciclo])) {
            return $ciclos[$ciclo]['montos'] ?? [];
        }

        return self::CICLOS_DEFAULT[$ciclo] ?? [];
    }

    /**
     * Calcula el seguro basado en el monto prestado.
     */
    public function calcularSeguro(float $monto, ProductoFinanciero $producto): float
    {
        $seguroPorMonto = $producto->getConfig('seguro_por_monto', []);

        // Buscar monto exacto en config
        $montoKey = (string) intval($monto);
        if (isset($seguroPorMonto[$montoKey])) {
            return (float) $seguroPorMonto[$montoKey];
        }

        // Fallback: 1% del monto
        return round($monto * 0.01, 2);
    }

    /**
     * Genera el cronograma completo de pagos.
     *
     * @param ProductoFinanciero $producto       Producto financiero seleccionado
     * @param array              $montosPorCliente [cliente_id => monto_prestado]
     * @param int                $cantidadCuotas  Número total de cuotas
     * @param string             $frecuencia      'semanal', 'quincenal', 'mensual'
     * @param Carbon             $fechaInicio     Fecha del primer vencimiento
     * @return Collection Colección de cuotas con detalle por cliente
     */
    public function generar(
        ProductoFinanciero $producto,
        array $montosPorCliente,
        int $cantidadCuotas,
        string $frecuencia,
        Carbon $fechaInicio
    ): Collection {
        $tasaInteres = (float) $producto->tasa_interes;
        $cronograma = collect();

        foreach (range(1, $cantidadCuotas) as $numeroCuota) {
            $fechaVencimiento = $this->calcularFechaVencimiento(
                $fechaInicio,
                $numeroCuota,
                $frecuencia
            );

            $detallePorCliente = [];
            $totalGrupalCuota = 0;

            foreach ($montosPorCliente as $clienteId => $montoPrestado) {
                $seguro = $this->calcularSeguro($montoPrestado, $producto);
                $interesMonto = $montoPrestado * ($tasaInteres / 100);

                $capitalCuota = round($montoPrestado / $cantidadCuotas, 2);
                $interesCuota = round($interesMonto / $cantidadCuotas, 2);
                $seguroCuota = round($seguro / $cantidadCuotas, 2);
                $totalCuota = $capitalCuota + $interesCuota + $seguroCuota;

                $detallePorCliente[$clienteId] = [
                    'capital' => $capitalCuota,
                    'interes' => $interesCuota,
                    'seguro' => $seguroCuota,
                    'total' => $totalCuota,
                ];

                $totalGrupalCuota += $totalCuota;
            }

            $cronograma->push([
                'numero_cuota' => $numeroCuota,
                'fecha_vencimiento' => $fechaVencimiento->toDateString(),
                'detalle_clientes' => $detallePorCliente,
                'total_grupal' => round($totalGrupalCuota, 2),
            ]);
        }

        return $cronograma;
    }

    /**
     * Genera el resumen total del cronograma para la vista previa.
     */
    public function generarResumen(
        ProductoFinanciero $producto,
        array $montosPorCliente,
        int $cantidadCuotas
    ): array {
        $tasaInteres = (float) $producto->tasa_interes;
        $resumen = [
            'total_prestado' => 0,
            'total_intereses' => 0,
            'total_seguros' => 0,
            'total_devolver' => 0,
            'por_cliente' => [],
        ];

        foreach ($montosPorCliente as $clienteId => $montoPrestado) {
            $seguro = $this->calcularSeguro($montoPrestado, $producto);
            $interes = $montoPrestado * ($tasaInteres / 100);
            $totalDevolver = $montoPrestado + $interes + $seguro;

            $resumen['por_cliente'][$clienteId] = [
                'monto_prestado' => $montoPrestado,
                'interes' => round($interes, 2),
                'seguro' => $seguro,
                'total_devolver' => round($totalDevolver, 2),
                'cuota_mensual' => round($totalDevolver / $cantidadCuotas, 2),
            ];

            $resumen['total_prestado'] += $montoPrestado;
            $resumen['total_intereses'] += $interes;
            $resumen['total_seguros'] += $seguro;
            $resumen['total_devolver'] += $totalDevolver;
        }

        $resumen['total_prestado'] = round($resumen['total_prestado'], 2);
        $resumen['total_intereses'] = round($resumen['total_intereses'], 2);
        $resumen['total_seguros'] = round($resumen['total_seguros'], 2);
        $resumen['total_devolver'] = round($resumen['total_devolver'], 2);

        return $resumen;
    }

    /**
     * Calcula la fecha de vencimiento según frecuencia.
     */
    private function calcularFechaVencimiento(
        Carbon $fechaInicio,
        int $numeroCuota,
        string $frecuencia
    ): Carbon {
        return match ($frecuencia) {
            'semanal' => $fechaInicio->copy()->addWeeks($numeroCuota),
            'quincenal' => $fechaInicio->copy()->addWeeks($numeroCuota * 2),
            'mensual' => $fechaInicio->copy()->addMonths($numeroCuota),
            default => $fechaInicio->copy()->addWeeks($numeroCuota),
        };
    }
}
