<?php

namespace App\Filament\Dashboard\Widgets;

use App\Services\WidgetStatsService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class KPIJefeOperacionesWidget extends BaseWidget
{
    protected static bool $isLazy = true;

    protected function getStats(): array
    {
        $stats = WidgetStatsService::getJefeOperacionesStats();

        return [
            Stat::make('Pagos por Aprobar', (string) $stats['pagos_pendientes'])
                ->description('En espera de aprobación')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),

            Stat::make('Total Cobrado', WidgetStatsService::formatCurrency($stats['monto_cobrado_mes']))
                ->description('Este mes')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),

            Stat::make('Total Desembolsado', WidgetStatsService::formatCurrency($stats['monto_desembolsado_mes']))
                ->description('Este mes')
                ->descriptionIcon('heroicon-m-banknote')
                ->color('primary'),

            Stat::make('Grupos en Mora', (string) $stats['grupos_en_mora'])
                ->description('Requieren seguimiento')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('danger'),
        ];
    }
}
