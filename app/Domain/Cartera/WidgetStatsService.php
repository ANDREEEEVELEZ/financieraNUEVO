<?php

namespace App\Domain\Cartera;

use App\Infrastructure\Cache\CacheService;

class WidgetStatsService
{
    public static function getAdminStats(): array
    {
        return CacheService::getDashboardStatsAdmin();
    }

    public static function getAsesorStats(int $asesorId): array
    {
        return CacheService::getDashboardStatsAsesor($asesorId);
    }

    public static function getJefeOperacionesStats(): array
    {
        return CacheService::getDashboardStatsJO();
    }

    public static function getJefeCreditosStats(): array
    {
        return CacheService::getDashboardStatsJC();
    }

    public static function formatCurrency(float $amount): string
    {
        return 'S/ ' . number_format($amount, 2);
    }

    public static function getMoraColor(float $tasaMora): string
    {
        return $tasaMora > 10 ? 'danger' : 'warning';
    }

    public static function getFlowTrendIcon(float $flujo): string
    {
        return 'heroicon-m-arrow-trending-' . ($flujo >= 0 ? 'up' : 'down');
    }

    public static function getFlowColor(float $flujo): string
    {
        return $flujo >= 0 ? 'success' : 'danger';
    }
}
