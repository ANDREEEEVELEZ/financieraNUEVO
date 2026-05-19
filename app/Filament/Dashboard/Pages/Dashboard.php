<?php

namespace App\Filament\Dashboard\Pages;

use App\Filament\Dashboard\Widgets\CuotasVigentesWidget;
use App\Filament\Dashboard\Resources\EgresosResource\Widgets\EgresosStatsWidget;
use App\Filament\Dashboard\Resources\IngresosResource\Widgets\IngresosStatsWidget;
use App\Filament\Dashboard\Widgets\KPIAsesorWidget;
use App\Filament\Dashboard\Widgets\KPIAdminWidget;
use App\Filament\Dashboard\Widgets\KPIJefeCreditosWidget;
use App\Filament\Dashboard\Widgets\KPIJefeOperacionesWidget;
use App\Filament\Dashboard\Resources\PagoResource\Widgets\PagosStatsWidget;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    public function getWidgets(): array
    {
        $user = auth()->user();

        if ($user->hasRole('Asesor')) {
            return [
                KPIAsesorWidget::class,
                CuotasVigentesWidget::class,
                PagosStatsWidget::class,
            ];
        }

        if ($user->hasRole('Jefe de operaciones')) {
            return [
                KPIJefeOperacionesWidget::class,
                CuotasVigentesWidget::class,
                PagosStatsWidget::class,
                IngresosStatsWidget::class,
                EgresosStatsWidget::class,
            ];
        }

        if ($user->hasRole('Jefe de creditos')) {
            return [
                KPIJefeCreditosWidget::class,
                CuotasVigentesWidget::class,
                PagosStatsWidget::class,
            ];
        }

        return [
            KPIAdminWidget::class,
            CuotasVigentesWidget::class,
            PagosStatsWidget::class,
            IngresosStatsWidget::class,
            EgresosStatsWidget::class,
        ];
    }
}
