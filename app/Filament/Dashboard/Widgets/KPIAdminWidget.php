<?php

namespace App\Filament\Dashboard\Widgets;

use App\Services\WidgetStatsService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class KPIAdminWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $stats = WidgetStatsService::getAdminStats();

        return [
            Stat::make('Cartera Total', WidgetStatsService::formatCurrency($stats['cartera_total']))
                ->description('Préstamos activos')
                ->descriptionIcon('heroicon-m-briefcase')
                ->color('success'),

            Stat::make('Pagos Pendientes', (string) $stats['pagos_pendientes'])
                ->description('Por aprobar')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),

            Stat::make('Tasa de Mora Global', $stats['tasa_mora'] . '%')
                ->description('Cartera en mora')
                ->descriptionIcon('heroicon-m-chart-pie')
                ->color(WidgetStatsService::getMoraColor($stats['tasa_mora'])),

            Stat::make('Flujo Neto del Mes', WidgetStatsService::formatCurrency($stats['flujo_neto_mes']))
                ->description('Ingresos - Egresos')
                ->descriptionIcon(WidgetStatsService::getFlowTrendIcon($stats['flujo_neto_mes']))
                ->color(WidgetStatsService::getFlowColor($stats['flujo_neto_mes'])),
        ];
    }
}
