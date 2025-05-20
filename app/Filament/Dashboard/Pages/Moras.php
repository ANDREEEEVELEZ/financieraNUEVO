<?php

namespace App\Filament\Dashboard\Pages;

use Filament\Pages\Page;
use App\Models\Mora;
use App\Models\Cuotas_Grupales;

class Moras extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-exclamation-circle';
    protected static string $view = 'filament.dashboard.pages.mora';
    protected static ?string $title = 'Gestión de Moras';
    protected static ?int $navigationSort = 5;

    public function getViewData(): array
    {
        // Actualizar estado de cuotas vencidas no pagadas
        Cuotas_Grupales::where('estado_cuota_grupal', 'vigente')
            ->where('estado_pago', '!=', 'pagado')
            ->whereDate('fecha_vencimiento', '<', now())
            ->update(['estado_cuota_grupal' => 'mora']);

        // Recalcular mora si está en estado "mora"
        $cuotasEnMora = Cuotas_Grupales::with('prestamo.grupo')
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
                'fecha_atraso' => $diasAtraso,
                'monto_mora' => $montoMora,
                'estado_mora' => 'pendiente',
            ]
        );
    }


        return [
            'cuotas_mora' => Cuotas_Grupales::with(['mora', 'prestamo.grupo'])
                ->where('estado_cuota_grupal', 'mora')
                ->get()
        ];
    }
}
