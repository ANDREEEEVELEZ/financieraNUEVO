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
                        
                    Select::make('estado_prestamos')
                        ->label('Seleccionar Estado de Préstamos')
                        ->options([
                            'Aprobado' => 'Aprobado',
                            'Activo' => 'Activo',
                            'Parcialmente Retanqueado' => 'Parcialmente Retanqueado',
                            'Finalizado' => 'Finalizado',
                        ])
                        ->required()
                        ->placeholder('Seleccione el estado de los préstamos')
                        ->helperText('Se descargarán TODOS los contratos con el estado seleccionado en el rango de fechas'),
                ])
                ->action(function (array $data) {
                    $fechaDesde = $data['fecha_desde'];
                    $fechaHasta = $data['fecha_hasta'];
                    $estadoSeleccionado = $data['estado_prestamos'];
                    
                    // Buscar préstamos con el estado seleccionado en el rango de fechas
                    $query = Prestamo::with(['grupo'])
                        ->where('estado', $estadoSeleccionado)
                        ->whereNotNull('grupo_id'); // Solo préstamos con grupo
                        
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
                    
                    $prestamos = $query->get();
                    
                    if ($prestamos->isEmpty()) {
                        Notification::make()
                            ->title('Sin resultados')
                            ->body("No se encontraron préstamos con estado '{$estadoSeleccionado}' en el rango de fechas seleccionado")
                            ->warning()
                            ->send();
                        return;
                    }
                    
                    // Redirigir a la nueva ruta que maneja la descarga masiva
                    $gruposIds = $prestamos->pluck('grupo_id')->unique()->values()->toArray();
                    
                    return redirect()->route('contratos.masivos.imprimir', [
                        'grupos' => implode(',', $gruposIds),
                        'estado' => $estadoSeleccionado,
                        'fecha_desde' => $fechaDesde,
                        'fecha_hasta' => $fechaHasta
                    ]);
                })
                ->modalHeading('Imprimir Contratos Masivos')
                ->modalDescription('Seleccione el rango de fechas y el estado de préstamos para descargar TODOS los contratos correspondientes.')
                ->modalSubmitActionLabel('Descargar Contratos')
                ->modalWidth('lg'),
        ];
    }
}
