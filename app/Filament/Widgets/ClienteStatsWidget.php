<?php

namespace App\Filament\Widgets;

use App\Models\Cliente;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\Asesor;

class ClienteStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $user = request()->user();
        $query = Cliente::query();

        if ($user->hasRole('Asesor')) {
            $asesor = Asesor::where('user_id', $user->id)->first();
            if ($asesor) {
                $query->where('asesor_id', $asesor->id);
            }
        }

        $totalClientes = $query->count();
        $clientesActivos = (clone $query)->where('estado_cliente', 'Activo')->count();
        $clientesInactivos = (clone $query)->where('estado_cliente', 'Inactivo')->count();

        return [
            Stat::make('Total Clientes', $totalClientes)
                ->description('Número total de clientes')
                ->color('gray'),
            Stat::make('Clientes Activos', $clientesActivos)
                ->description('Clientes en estado activo')
                ->color('success'),
            Stat::make('Clientes Inactivos', $clientesInactivos)
                ->description('Clientes en estado inactivo')
                ->color('danger'),
        ];
    }
}
