<?php

namespace App\Filament\Dashboard\Resources\EgresosResource\Widgets;

use App\Models\Egreso;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class EgresosStatsWidget extends BaseWidget
{
    use InteractsWithPageFilters;

    protected function getStats(): array
    {
        // Obtener filtros de fecha
        $fechaDesde = $this->filters['fecha']['fecha_desde'] ?? null;
        $fechaHasta = $this->filters['fecha']['fecha_hasta'] ?? null;

        // Si no hay filtros, usar el mes actual
        if (!$fechaDesde && !$fechaHasta) {
            $fechaDesde = Carbon::now()->startOfMonth();
            $fechaHasta = Carbon::now()->endOfMonth();
            $periodoDescripcion = 'este mes (' . Carbon::now()->format('M Y') . ')';
        } else {
            // Si hay filtros, usarlos
            $fechaDesde = $fechaDesde ? Carbon::parse($fechaDesde) : Carbon::now()->startOfMonth();
            $fechaHasta = $fechaHasta ? Carbon::parse($fechaHasta)->endOfDay() : Carbon::now()->endOfMonth();
            
            if ($fechaDesde->format('Y-m-d') === $fechaHasta->format('Y-m-d')) {
                $periodoDescripcion = 'el ' . $fechaDesde->format('d/m/Y');
            } else {
                $periodoDescripcion = 'del ' . $fechaDesde->format('d/m/Y') . ' al ' . $fechaHasta->format('d/m/Y');
            }
        }

        // Consulta con filtros aplicados
        $query = Egreso::whereBetween('fecha', [$fechaDesde, $fechaHasta]);

        // Totales por tipo de egreso
        $totales = $query->select('tipo_egreso', DB::raw('SUM(monto) as total'))
            ->groupBy('tipo_egreso')
            ->pluck('total', 'tipo_egreso')
            ->toArray();

        $totalDesembolsos = $totales['desembolso'] ?? 0;
        $totalGastos = $totales['gasto'] ?? 0;
        $totalGeneral = $totalDesembolsos + $totalGastos;

        return [
            Stat::make('Desembolsos', 'S/ ' . number_format($totalDesembolsos, 2))
                ->description('Desembolsos ' . $periodoDescripcion)
                ->descriptionIcon('heroicon-m-arrow-trending-down')
                ->color('warning'),

            Stat::make('Gastos', 'S/ ' . number_format($totalGastos, 2))
                ->description('Gastos ' . $periodoDescripcion)
                ->descriptionIcon('heroicon-m-minus-circle')
                ->color('danger'),

            Stat::make('Total Egresos', 'S/ ' . number_format($totalGeneral, 2))
                ->description('Total egresos ' . $periodoDescripcion)
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('gray'),
        ];
    }

    protected function getColumns(): int
    {
        return 3;
    }
}