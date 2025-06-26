<?php

namespace App\Filament\Dashboard\Resources\PrestamoResource\Pages;

use App\Filament\Dashboard\Resources\PrestamoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use App\Models\Prestamo;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Carbon\Carbon;

class ListPrestamo extends ListRecords
{
    protected static string $resource = PrestamoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->icon('heroicon-o-plus-circle'),
            
            Actions\Action::make('imprimir_contratos')
                ->label('Imprimir Contrato')
                ->icon('heroicon-o-printer')
                ->color('success')
                ->form([
                    DatePicker::make('fecha_desde')
                        ->label('Fecha desde')
                        ->required()
                        ->default(now()->subMonth()),
                        
                    DatePicker::make('fecha_hasta')
                        ->label('Fecha hasta')
                        ->required()
                        ->default(now()),
                        
                    Select::make('prestamo_id')
                        ->label('Seleccionar Préstamo Aprobado')
                        ->options(function (Forms\Get $get) {
                            $fechaDesde = $get('fecha_desde');
                            $fechaHasta = $get('fecha_hasta');
                            
                            $query = Prestamo::with(['grupo'])
                                ->where('estado', 'Aprobado');
                                
                            if ($fechaDesde) {
                                $query->whereDate('fecha_prestamo', '>=', $fechaDesde);
                            }
                            
                            if ($fechaHasta) {
                                $query->whereDate('fecha_prestamo', '<=', $fechaHasta);
                            }
                            
                            // Aplicar filtros de usuario
                            $user = request()->user();
                            if ($user->hasRole('Asesor')) {
                                $asesor = \App\Models\Asesor::where('user_id', $user->id)->first();
                                if ($asesor) {
                                    $query->whereHas('grupo', fn($q) => $q->where('asesor_id', $asesor->id));
                                }
                            }
                            
                            return $query->get()
                                ->mapWithKeys(function ($prestamo) {
                                    $grupoNombre = $prestamo->grupo->nombre_grupo ?? 'Sin grupo';
                                    $fecha = $prestamo->fecha_prestamo ? $prestamo->fecha_prestamo : 'Sin fecha';
                                    $monto = 'S/ ' . number_format((float) $prestamo->monto_prestado_total, 2);
                                    return [
                                        $prestamo->id => "{$grupoNombre} - {$fecha} - {$monto}"
                                    ];
                                });
                        })
                        ->searchable()
                        ->required()
                        ->reactive()
                        ->placeholder('Seleccione un préstamo aprobado')
                        ->helperText('Solo se muestran préstamos con estado "Aprobado" en el rango de fechas seleccionado'),
                ])
                ->action(function (array $data) {
                    $prestamo = Prestamo::with('grupo')->find($data['prestamo_id']);
                    
                    if (!$prestamo) {
                        Notification::make()
                            ->title('Error')
                            ->body('Préstamo no encontrado')
                            ->danger()
                            ->send();
                        return;
                    }
                    
                    if ($prestamo->estado !== 'Aprobado') {
                        Notification::make()
                            ->title('Error')
                            ->body('Solo se pueden imprimir contratos de préstamos aprobados')
                            ->danger()
                            ->send();
                        return;
                    }
                    
                    if (!$prestamo->grupo_id) {
                        Notification::make()
                            ->title('Error')
                            ->body('El préstamo debe estar asociado a un grupo')
                            ->danger()
                            ->send();
                        return;
                    }
                    
                    // Redirigir a la URL de impresión de contratos
                    return redirect()->route('contratos.grupo.imprimir', $prestamo->grupo_id);
                })
                ->modalHeading('Imprimir Contrato de Préstamo')
                ->modalDescription('Seleccione el rango de fechas y el préstamo aprobado para imprimir su contrato.')
                ->modalSubmitActionLabel('Imprimir Contrato')
                ->modalWidth('lg'),
        ];
    }
}
