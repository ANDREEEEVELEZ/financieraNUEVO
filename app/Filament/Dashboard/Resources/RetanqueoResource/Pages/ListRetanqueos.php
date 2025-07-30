<?php

namespace App\Filament\Dashboard\Resources\RetanqueoResource\Pages;

use App\Filament\Dashboard\Resources\RetanqueoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
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
            // Removemos el widget personalizado para evitar el error
        ];
    }

    public function getTitle(): string
    {
        return 'Retanqueos';
    }

    protected function getHeaderData(): array
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
            'total' => $totalSolicitudes,
            'pendientes' => $solicitudesPendientes,
            'aprobados' => $retanqueosAprobados,
            'ejecutados' => $retanqueosEjecutados,
            'rechazados' => $retanqueosRechazados,
            'monto_total' => $montoTotalRetanqueado,
        ];
    }
}
