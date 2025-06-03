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
                ->color('primary') // Mejor contraste en modo claro
                ->form([
                    \Filament\Forms\Components\Select::make('grupo')
                        ->label('Nombre del grupo')
                        ->options(function () use ($user) {
                            $query = \App\Models\Grupo::query();

                            if ($user->hasRole('Asesor')) {
                                $asesor = \App\Models\Asesor::where('user_id', $user->id)->first();
                                if ($asesor) {
                                    $query->where('asesor_id', $asesor->id);
                                } else {
                                    return []; // Si el asesor no existe, retornar vacío
                                }
                            } elseif (!$user->hasAnyRole(['super_admin', 'Jefe de Operaciones', 'Jefe de Creditos'])) {
                                return []; // Si no tiene roles permitidos, retornar vacío
                            }

                            return $query->orderBy('nombre_grupo')->pluck('nombre_grupo', 'id')->toArray();
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
