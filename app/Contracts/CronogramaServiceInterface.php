<?php

namespace App\Contracts;

use App\Models\ProductoFinanciero;
use Carbon\Carbon;
use Illuminate\Support\Collection;

interface CronogramaServiceInterface
{
    /**
     * Validates that the requested monto is valid for the client's ciclo.
     */
    public function validarMontoPorCiclo(
        string $ciclo,
        float $monto,
        ProductoFinanciero $producto
    ): bool;

    /**
     * Returns the available montos for a given ciclo.
     */
    public function obtenerMontosDisponibles(
        string $ciclo,
        ProductoFinanciero $producto
    ): array;

    /**
     * Calculates the seguro amount based on the monto prestado.
     */
    public function calcularSeguro(float $monto, ProductoFinanciero $producto): float;

    /**
     * Generates the full cronograma de pagos.
     *
     * @param ProductoFinanciero $producto          Selected financial product
     * @param array              $montosPorCliente  [cliente_id => monto_prestado]
     * @param int                $cantidadCuotas    Total number of cuotas
     * @param string             $frecuencia        'semanal', 'quincenal', 'mensual'
     * @param Carbon             $fechaInicio       First due date
     * @return Collection
     */
    public function generar(
        ProductoFinanciero $producto,
        array $montosPorCliente,
        int $cantidadCuotas,
        string $frecuencia,
        Carbon $fechaInicio
    ): Collection;

    /**
     * Generates the cronograma summary for preview views.
     */
    public function generarResumen(
        ProductoFinanciero $producto,
        array $montosPorCliente,
        int $cantidadCuotas
    ): array;
}
