<?php

namespace App\Filament\Widgets;

use App\Models\Cliente;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ClienteStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $totalClientes = Cliente::count();
        $clientesActivos = Cliente::where('estado_cliente', 'Activo')->count();
        $clientesInactivos = Cliente::where('estado_cliente', 'Inactivo')->count();

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
