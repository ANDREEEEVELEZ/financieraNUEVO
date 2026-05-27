<?php

namespace App\Filament\Dashboard\Widgets;

use App\Infrastructure\Cache\CacheService;
use App\Domain\Cartera\WidgetStatsService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class KPIAsesorWidget extends BaseWidget
{
    protected static bool $isLazy = true;

    protected function getStats(): array
    {
        $user = auth()->user();
        $asesor = $user ? CacheService::getAsesorByUserId($user->id) : null;

        if (!$asesor) {
            return [
                Stat::make('Error', 'No asociado a asesor'),
            ];
        }

        $stats = WidgetStatsService::getAsesorStats($asesor->id);

        return [
            Stat::make('Grupos Activos', (string) $stats['grupos_activos'])
                ->description('A su cargo')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('primary'),

            Stat::make('Cuotas Esta Semana', (string) $stats['cuotas_semana'])
                ->description('Por vencer')
                ->descriptionIcon('heroicon-m-calendar')
                ->color('warning'),

            Stat::make('Monto Cobrado', WidgetStatsService::formatCurrency($stats['monto_cobrado_mes']))
                ->description('Este mes')
                ->descriptionIcon('heroicon-m-banknote')
                ->color('success'),

            Stat::make('Grupos en Mora', (string) $stats['grupos_en_mora'])
                ->description('Requieren atención')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('danger'),
        ];
    }
}
