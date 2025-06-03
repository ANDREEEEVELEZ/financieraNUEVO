<?php

namespace App\Filament\Dashboard\Resources\PagoResource\Pages;

use App\Filament\Dashboard\Resources\PagoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;

class ListPagos extends ListRecords
{
    protected static string $resource = PagoResource::class;

    protected function getHeaderActions(): array
    {
        $user = Auth::user();

        return [
            Actions\CreateAction::make(),

            Actions\Action::make('exportar_pdf')
                ->label('Exportar PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('primary')
                ->form([
                    \Filament\Forms\Components\Select::make('grupo')
                        ->label('Nombre del grupo')
                        ->options(function () use ($user) {
                            if ($user && $user->hasRole('Asesor')) {
                                // Mostrar solo grupos del asesor (id => nombre)
                                return \App\Models\Grupo::where('asesor_id', $user->id)
                                    ->orderBy('nombre_grupo')
                                    ->pluck('nombre_grupo', 'id')
                                    ->toArray();
                            }
                            // Para otros roles, mostrar todos los grupos
                            return \App\Models\Grupo::orderBy('nombre_grupo')
                                ->pluck('nombre_grupo', 'id')
                                ->toArray();
                        })
                        ->searchable()
                        ->placeholder('Todos'),
                    \Filament\Forms\Components\DatePicker::make('from')->label('Desde'),
                    \Filament\Forms\Components\DatePicker::make('until')->label('Hasta'),
                    \Filament\Forms\Components\Select::make('estado_pago')
                        ->label('Estado')
                        ->options([
                            '' => 'Todos',
                            'Pendiente' => 'Pendiente',
                            'Aprobado' => 'Aprobado',
                            'Rechazado' => 'Rechazado',
                        ]),
                ])
                ->action(function (array $data) {
                    $params = array_filter([
                        'grupo' => $data['grupo'] ?? null,
                        'from' => $data['from'] ?? null,
                        'until' => $data['until'] ?? null,
                        'estado_pago' => $data['estado_pago'] ?? null,
                    ]);
                    $url = route('pagos.exportar.pdf', $params);
                    return redirect($url);
                }),
        ];
    }
}
