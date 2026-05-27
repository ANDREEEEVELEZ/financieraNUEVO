<?php

namespace App\Filament\Dashboard\Widgets;

use App\Domain\Cartera\WidgetStatsService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class KPIJefeCreditosWidget extends BaseWidget
{
    protected static bool $isLazy = true;

    protected function getStats(): array
    {
        $stats = WidgetStatsService::getJefeCreditosStats();

        return [
            Stat::make('Solicitudes Pendientes', (string) $stats['solicitudes_pendientes'])
                ->description('Por evaluar')
                ->descriptionIcon('heroicon-m-document-text')
                ->color('warning'),

            Stat::make('Aprobados Sin Firmar', (string) $stats['aprobados_sin_firmar'])
                ->description('Pendientes de firma')
                ->descriptionIcon('heroicon-m-pencil')
                ->color('info'),

            Stat::make('Cartera Activa', WidgetStatsService::formatCurrency($stats['cartera_activa']))
                ->description('En vigencia')
                ->descriptionIcon('heroicon-m-briefcase')
                ->color('success'),

            Stat::make('Tasa de Mora', $stats['tasa_mora'] . '%')
                ->description('Sobre cartera activa')
                ->descriptionIcon('heroicon-m-chart-pie')
                ->color(WidgetStatsService::getMoraColor($stats['tasa_mora'])),
        ];
    }
}
