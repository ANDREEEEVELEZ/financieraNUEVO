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

            $montoMora = Mora::calcularMontoMora($cuota);

            Mora::updateOrCreate(
                ['cuota_grupal_id' => $cuota->id],
                [
                    'fecha_atraso' => now(),
                    'monto_mora' => $montoMora,
                    'estado_mora' => 'pendiente',
                ]
            );
        }

        $query = CuotasGrupales::with(['mora', 'prestamo.grupo'])
            ->whereHas('mora');

        $filtro = request('filtro');
        if ($filtro === 'grupo' && request('grupo')) {
            $query->whereHas('prestamo.grupo', function($q) {
                $q->where('nombre_grupo', 'like', '%' . request('grupo') . '%');
            });
        } elseif ($filtro === 'fecha' && (request('desde') || request('hasta'))) {
            if (request('desde')) {
                $query->whereDate('fecha_vencimiento', '>=', request('desde'));
            }
            if (request('hasta')) {
                $query->whereDate('fecha_vencimiento', '<=', request('hasta'));
            }
        } elseif ($filtro === 'monto' && request('monto')) {
            $query->whereHas('mora', function($q) {
                $q->whereRaw('ABS(monto_mora_calculado) >= ?', [floatval(request('monto'))]);
            });
        } elseif ($filtro === 'estado' && request('estado_mora')) {
            $estado = request('estado_mora');
            $estadosValidos = ['pendiente', 'pagada', 'parcial'];
            if (in_array($estado, $estadosValidos)) {
                $query->whereHas('mora', function($q) use ($estado) {
                    $q->where('estado_mora', $estado);
                });
            }
        }

        return [
            'cuotas_mora' => $query->get()
        ];
    }
}
