<?php

namespace App\Filament\Dashboard\Resources\IngresosResource\Widgets;

use App\Models\Ingreso;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class IngresosStatsWidget extends BaseWidget
{
    use InteractsWithPageFilters;

    protected function getStats(): array
    {
        // Obtener filtros de fecha
        $fechaDesde = $this->filters['fecha_rango']['desde'] ?? null;
        $fechaHasta = $this->filters['fecha_rango']['hasta'] ?? null;

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
        $query = Ingreso::whereBetween('fecha_hora', [$fechaDesde, $fechaHasta]);

        // Totales por tipo de ingreso
        $totales = $query->select('tipo_ingreso', DB::raw('SUM(monto) as total'))
            ->groupBy('tipo_ingreso')
            ->pluck('total', 'tipo_ingreso')
            ->toArray();

        $totalTransferencias = $totales['transferencia'] ?? 0;
        $totalPagosCuota = $totales['pago de cuota de grupo'] ?? 0;
        $totalGeneral = $totalTransferencias + $totalPagosCuota;

            return [
        Stat::make('Transferencias', 'S/ ' . number_format($totalTransferencias, 2))
            ->description('Transferencias ' . $periodoDescripcion)
            ->descriptionIcon('heroicon-m-arrow-trending-up')
            ->color('primary'),

        Stat::make('Pagos de cuota', 'S/ ' . number_format($totalPagosCuota, 2))
            ->description('Pagos de cuota ' . $periodoDescripcion)
            ->descriptionIcon('heroicon-m-users')
            ->color('success'),

        Stat::make('Total Ingresos', 'S/ ' . number_format($totalGeneral, 2))
            ->description('Total ingresos ' . $periodoDescripcion)
            ->descriptionIcon('heroicon-m-banknotes')
            ->color('warning'),
    ];

    }

    protected function getColumns(): int
    {
        return 3;
    }
    protected function getFilterFormSchema(): array
{
    return [
        \Filament\Forms\Components\DatePicker::make('fecha_rango.desde')
            ->label('Desde'),
        \Filament\Forms\Components\DatePicker::make('fecha_rango.hasta')
            ->label('Hasta'),
    ];
}
}