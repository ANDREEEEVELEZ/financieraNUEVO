<?php

namespace App\Filament\Dashboard\Resources\PagoResource\Widgets;

use App\Models\Pago;
use App\Models\Asesor;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class PagosStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $user = Auth::user();
        
        // Verificar si el usuario tiene permisos para ver el widget
        if ($user->hasRole('Asesor')) {
            $asesor = Asesor::where('user_id', $user->id)->first();
            if (!$asesor) {
                return []; // Si el asesor no existe, retornar vacío
            }
        } elseif (!$user->hasAnyRole(['super_admin', 'Jefe de operaciones', 'Jefe de creditos'])) {
            return []; // Si no tiene roles permitidos, retornar vacío
        }

        return [
            Stat::make('Total Pagos Registrados', $this->getFilteredQuery()->count())
                ->description('Número total de pagos')
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->color('success'),
            Stat::make('Monto Total Registrado', 'S/' . number_format($this->getFilteredQuery()->where('estado_pago', 'aprobado')->sum('monto_pagado'), 2))
                ->description('Monto total de pagos aprobados')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('warning'),
            
            Stat::make('Pagos del Mes Registrados', $this->getCurrentMonthQuery()->count())
                ->description('Pagos realizados este mes')
                ->descriptionIcon('heroicon-m-calendar')
                ->color('info'),
            

            Stat::make('Monto del Mes Registrado', 'S/' . number_format($this->getCurrentMonthQuery()->where('estado_pago', 'aprobado')->sum('monto_pagado'), 2))
                ->description('Monto aprobado del mes actual')
                ->descriptionIcon('heroicon-m-credit-card')
                ->color('primary'),
        ];
    }

    protected function getFilteredQuery(): Builder
    {
        $user = Auth::user();
        $query = Pago::query();

        // Aplicar filtros según el rol del usuario
        if ($user->hasRole('Asesor')) {
            $asesor = Asesor::where('user_id', $user->id)->first();
            if ($asesor) {
                // Filtrar pagos que pertenecen a cuotas grupales de grupos manejados por este asesor
                $query->whereHas('cuotaGrupal.prestamo.grupo', function (Builder $subQuery) use ($asesor) {
                    $subQuery->where('asesor_id', $asesor->id);
                });
            } else {
                // Si el asesor no existe, no mostrar registros
                $query->whereRaw('1 = 0');
            }
        } elseif (!$user->hasAnyRole(['super_admin', 'Jefe de operaciones', 'Jefe de creditos'])) {
            // Si no tiene roles permitidos, no mostrar registros
            $query->whereRaw('1 = 0');
        }
        // Para super_admin, Jefe de operaciones y Jefe de creditos, mostrar todos los registros

        return $query;
    }

    protected function getCurrentMonthQuery(): Builder
    {
        return $this->getFilteredQuery()
            ->whereMonth('fecha_pago', now()->month)
            ->whereYear('fecha_pago', now()->year);
    }
}