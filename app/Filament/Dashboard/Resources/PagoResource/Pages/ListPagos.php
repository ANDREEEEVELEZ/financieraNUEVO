<?php

namespace App\Filament\Dashboard\Resources\PagoResource\Pages;

use App\Filament\Dashboard\Resources\PagoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPagos extends ListRecords
{
    protected static string $resource = PagoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('exportar_pdf')
                ->label('Exportar PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('primary') // Mejor contraste en modo claro
                ->form([
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
