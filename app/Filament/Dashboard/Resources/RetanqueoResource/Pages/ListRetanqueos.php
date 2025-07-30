<?php

namespace App\Filament\Dashboard\Resources\RetanqueoResource\Pages;

use App\Filament\Dashboard\Resources\RetanqueoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Widgets\StatsOverviewWidget;
use App\Models\Retanqueo;

class ListRetanqueos extends ListRecords
{
    protected static string $resource = RetanqueoResource::class;

    protected function getHeaderActions(): array
    {
        $user = request()->user();
        
        $actions = [];
        
        if ($user && $user->hasAnyRole(['super_admin', 'Jefe de operaciones', 'Jefe de creditos', 'Asesor'])) {
            $actions[] = Actions\CreateAction::make()
                ->label('Nueva Solicitud de Retanqueo')
                ->icon('heroicon-o-plus-circle')
                ->color('primary');
        }

        return $actions;
    }

    protected function getHeaderWidgets(): array
    {
        return [
            RetanqueoStatsWidget::class,
        ];
    }
}

class RetanqueoStatsWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $user = request()->user();
        
        // Consulta base filtrada por usuario
        $query = Retanqueo::query();
        
        if ($user->hasRole('Asesor')) {
            $asesor = \App\Models\Asesor::where('user_id', $user->id)->first();
            if ($asesor) {
                $query->whereHas('prestamoAntiguo.grupo', function ($subQuery) use ($asesor) {
                    $subQuery->where('asesor_id', $asesor->id);
                });
            }
        }

        $totalSolicitudes = (clone $query)->count();
        $solicitudesPendientes = (clone $query)->where('estado_retanqueo', 'solicitud_pendiente')->count();
        $retanqueosAprobados = (clone $query)->where('estado_retanqueo', 'aprobado')->count();
        $retanqueosEjecutados = (clone $query)->where('estado_retanqueo', 'ejecutado')->count();
        $retanqueosRechazados = (clone $query)->where('estado_retanqueo', 'rechazado')->count();

        $montoTotalRetanqueado = (clone $query)
            ->where('estado_retanqueo', 'ejecutado')
            ->sum('monto_retanqueo');

        return [
            StatsOverviewWidget\Stat::make('Total Solicitudes', $totalSolicitudes)
                ->description('Todas las solicitudes de retanqueo')
                ->descriptionIcon('heroicon-m-document-text')
                ->color('primary'),

            StatsOverviewWidget\Stat::make('Pendientes de Aprobación', $solicitudesPendientes)
                ->description('Esperando revisión')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),

            StatsOverviewWidget\Stat::make('Aprobados', $retanqueosAprobados)
                ->description('Listos para ejecutar')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),

            StatsOverviewWidget\Stat::make('Ejecutados', $retanqueosEjecutados)
                ->description('Retanqueos completados')
                ->descriptionIcon('heroicon-m-play')
                ->color('info'),

            StatsOverviewWidget\Stat::make('Rechazados', $retanqueosRechazados)
                ->description('Solicitudes rechazadas')
                ->descriptionIcon('heroicon-m-x-circle')
                ->color('danger'),

            StatsOverviewWidget\Stat::make('Monto Total Retanqueado', 'S/ ' . number_format($montoTotalRetanqueado, 2))
                ->description('Total de retanqueos ejecutados')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),
        ];
    }
}
