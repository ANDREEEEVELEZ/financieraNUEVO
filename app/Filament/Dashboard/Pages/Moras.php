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

            // Verificar si ya existe una mora para esta cuota
            $moraExistente = Mora::where('cuota_grupal_id', $cuota->id)->first();

            // Solo calcular y actualizar si la mora NO está pagada
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
            // Si está pagada, no actualizar nada - mantener los valores congelados
        }

        $user = request()->user();
$query = CuotasGrupales::with(['mora', 'prestamo.grupo'])
    ->whereHas('mora', function ($moraQuery) use ($user) {
        $moraQuery->where(function ($subQuery) use ($user) {
            $subQuery->visiblePorUsuario($user);
        });
    });

// Filtro por grupo (si hay nombre ingresado)
if (request('grupo')) {
    $query->whereHas('prestamo.grupo', function($q) {
        $q->where('nombre_grupo', 'like', '%' . request('grupo') . '%');
    });
}

// Filtro por fecha (si hay desde/hasta)
if (request('desde')) {
    $query->whereDate('fecha_vencimiento', '>=', request('desde'));
}
if (request('hasta')) {
    $query->whereDate('fecha_vencimiento', '<=', request('hasta'));
}

// Filtro por monto mínimo
if (request('monto')) {
    $query->whereHas('mora', function($q) {
        $q->whereRaw('ABS(monto_mora_calculado) >= ?', [floatval(request('monto'))]);
    });
}

// Filtro por estado de mora
$estado = request('estado_mora');
$estadosValidos = ['pendiente', 'pagada', 'parcialmente_pagada'];
if ($estado && in_array($estado, $estadosValidos)) {
    $query->whereHas('mora', function($q) use ($estado) {
        $q->where('estado_mora', $estado);
    });
}


        return [
            'cuotas_mora' => $query->get()
        ];
    }
}
