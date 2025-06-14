<?php

namespace App\Filament\Dashboard\Pages;

use Filament\Pages\Page;
use App\Models\Mora;
use App\Models\CuotasGrupales;

class Moras extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-exclamation-circle';
    protected static string $view = 'filament.dashboard.pages.mora';
    protected static ?string $title = 'Gestión de Moras';
    protected static ?int $navigationSort = 5;

    public function getViewData(): array
    {

        CuotasGrupales::where('estado_cuota_grupal', 'vigente')
            ->where('estado_pago', '!=', 'pagado')
            ->whereDate('fecha_vencimiento', '<', now())
            ->update(['estado_cuota_grupal' => 'mora']);

        $cuotasEnMora = CuotasGrupales::with('prestamo.grupo')
            ->where('estado_cuota_grupal', 'mora')
            ->get();

        foreach ($cuotasEnMora as $cuota) {
            $diasAtraso = now()->isAfter($cuota->fecha_vencimiento)
                ? now()->diffInDays($cuota->fecha_vencimiento)
                : 0;


            $moraExistente = Mora::where('cuota_grupal_id', $cuota->id)->first();


            if (!$moraExistente || $moraExistente->estado_mora !== 'pagada') {
                $montoMora = Mora::calcularMontoMora($cuota, now(), $moraExistente->estado_mora ?? 'pendiente');

                Mora::updateOrCreate(
                    ['cuota_grupal_id' => $cuota->id],
                    [
                        'fecha_atraso' => now(),
                        'monto_mora' => $montoMora,
                        'estado_mora' => $moraExistente->estado_mora ?? 'pendiente',
                    ]
                );
            }
        }

        $user = request()->user();
        $query = CuotasGrupales::with(['mora', 'prestamo.grupo'])
            ->whereHas('mora', function ($moraQuery) use ($user) {
                $moraQuery->where(function ($subQuery) use ($user) {
                    $subQuery->visiblePorUsuario($user);
                });
            });


        $query = $this->aplicarFiltros($query);

        return [
            'cuotas_mora' => $query->get(),
            'filtros_activos' => $this->obtenerFiltrosActivos()
        ];
    }

    private function aplicarFiltros($query)
    {

        if (request('grupo')) {
            $query->whereHas('prestamo.grupo', function($q) {
                $q->where('nombre_grupo', 'like', '%' . request('grupo') . '%');
            });
        }


        if (request('desde')) {
            $query->whereDate('fecha_vencimiento', '>=', request('desde'));
        }
        if (request('hasta')) {
            $query->whereDate('fecha_vencimiento', '<=', request('hasta'));
        }


        if (request('monto') && is_numeric(request('monto'))) {
            $query->whereHas('mora', function($q) {
                $q->whereRaw('ABS(monto_mora_calculado) >= ?', [floatval(request('monto'))]);
            });
        }

        if (request('estado_mora') && request('estado_mora') !== '') {
            $estadosValidos = ['pendiente', 'pagada', 'parcialmente_pagada'];
            if (in_array(request('estado_mora'), $estadosValidos)) {
                $query->whereHas('mora', function($q) {
                    $estado = request('estado_mora');
                   
                    if ($estado === 'parcial') {
                        $estado = 'parcialmente_pagada';
                    }
                    $q->where('estado_mora', $estado);
                });
            }
        }

        return $query;
    }

    private function obtenerFiltrosActivos()
    {
        $filtros = [];

        if (request('grupo')) {
            $filtros[] = 'Grupo: ' . request('grupo');
        }

        if (request('desde') || request('hasta')) {
            $fechas = [];
            if (request('desde')) $fechas[] = 'desde ' . request('desde');
            if (request('hasta')) $fechas[] = 'hasta ' . request('hasta');
            $filtros[] = 'Fechas: ' . implode(' ', $fechas);
        }

        if (request('monto')) {
            $filtros[] = 'Monto mínimo: S/ ' . number_format(request('monto'), 2);
        }

        if (request('estado_mora')) {
            $estados = [
                'pendiente' => 'Pendiente',
                'pagada' => 'Pagada',
                'parcial' => 'Parcial',
                'parcialmente_pagada' => 'Parcialmente Pagada'
            ];
            $filtros[] = 'Estado: ' . ($estados[request('estado_mora')] ?? request('estado_mora'));
        }

        return $filtros;
    }

    public function limpiarFiltros()
    {
        return redirect()->route('filament.dashboard.pages.moras');
    }
}
