<?php

namespace App\Filament\Dashboard\Resources\ClienteResource\Pages;

use App\Filament\Dashboard\Resources\ClienteResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\Action;
use Filament\Forms;

class ListClientes extends ListRecords
{
    protected static string $resource = ClienteResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            \App\Filament\Widgets\ClienteStatsWidget::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->icon('heroicon-o-plus-circle'),
            
            // Acción de traslado masivo visible en el header
            Actions\Action::make('trasladar_clientes_masivo')
                ->label('Trasladar Clientes')
                ->icon('heroicon-o-arrow-right-circle')
                ->color('warning')
                ->visible(fn () => request()->user() && request()->user()->hasAnyRole(['super_admin', 'Jefe de operaciones']))
                ->form([
                    Forms\Components\Section::make('Selección de Clientes')
                        ->schema([
                            Forms\Components\CheckboxList::make('clientes_seleccionados')
                                ->label('Seleccionar Clientes para Trasladar')
                                ->options(function () {
                                    $user = request()->user();
                                    $query = \App\Models\Cliente::with(['persona', 'grupos'])
                                        ->where('estado_cliente', 'Activo');
                                    
                                    // Si es asesor, solo sus clientes
                                    if ($user->hasRole('Asesor')) {
                                        $asesor = \App\Models\Asesor::where('user_id', $user->id)->first();
                                        if ($asesor) {
                                            $query->where('asesor_id', $asesor->id);
                                        }
                                    }
                                    
                                    return $query->get()
                                        ->mapWithKeys(function ($cliente) {
                                            $grupoInfo = $cliente->tieneGrupoActivo() 
                                                ? " (Grupo: {$cliente->grupos()->where('estado_grupo', 'Activo')->first()->nombre_grupo})"
                                                : " (Sin grupo)";
                                            return [
                                                $cliente->id => "{$cliente->persona->nombre} {$cliente->persona->apellidos} - DNI: {$cliente->persona->DNI}{$grupoInfo}"
                                            ];
                                        });
                                })
                                ->required()
                                ->searchable()
                                ->columns(2)
                                ->helperText('Seleccione los clientes que desea trasladar a otro asesor'),
                        ]),
                    
                    Forms\Components\Section::make('Nuevo Asesor')
                        ->schema([
                            Forms\Components\Select::make('nuevo_asesor_id')
                                ->label('Nuevo Asesor')
                                ->required()
                                ->options(function () {
                                    return \App\Models\Asesor::where('estado_asesor', 'Activo')
                                        ->with('persona')
                                        ->get()
                                        ->mapWithKeys(function ($asesor) {
                                            return [$asesor->id => $asesor->persona->nombre . ' ' . $asesor->persona->apellidos];
                                        });
                                })
                                ->searchable()
                                ->helperText('Seleccione el asesor al que desea trasladar los clientes'),
                        ]),
                ])
                ->action(function ($data) {
                    $clientesIds = $data['clientes_seleccionados'];
                    $nuevoAsesorId = $data['nuevo_asesor_id'];
                    
                    $clientes = \App\Models\Cliente::with(['persona', 'grupos'])
                        ->whereIn('id', $clientesIds)
                        ->get();
                    
                    $nuevoAsesor = \App\Models\Asesor::with('persona')->find($nuevoAsesorId);
                    $nombreNuevoAsesor = $nuevoAsesor->persona->nombre . ' ' . $nuevoAsesor->persona->apellidos;
                    
                    $clientesSinGrupo = collect();
                    $gruposAfectados = collect();
                    
                    // Clasificar clientes según si pertenecen a grupos
                    foreach ($clientes as $cliente) {
                        if ($cliente->tieneGrupoActivo()) {
                            $grupo = $cliente->grupos()->where('estado_grupo', 'Activo')->first();
                            if ($grupo && !$gruposAfectados->contains('id', $grupo->id)) {
                                $gruposAfectados->push($grupo);
                            }
                        } else {
                            $clientesSinGrupo->push($cliente);
                        }
                    }
                    
                    // Trasladar clientes sin grupo directamente
                    if ($clientesSinGrupo->isNotEmpty()) {
                        foreach ($clientesSinGrupo as $cliente) {
                            $cliente->asesor_id = $nuevoAsesorId;
                            $cliente->save();
                        }
                        
                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title('Clientes Trasladados')
                            ->body($clientesSinGrupo->count() . " cliente(s) sin grupo han sido trasladados exitosamente al asesor {$nombreNuevoAsesor}.")
                            ->send();
                    }
                    
                    // Procesar grupos que necesitan traslado completo
                    if ($gruposAfectados->isNotEmpty()) {
                        static::procesarTrasladoGrupos($gruposAfectados, $nuevoAsesorId, $nombreNuevoAsesor);
                    }
                })
                ->modalHeading('Trasladar Clientes a Otro Asesor')
                ->modalSubmitActionLabel('Trasladar Clientes')
                ->modalWidth('4xl'),
        ];
    }

    protected function getTableActions(): array
    {
        return [
            Action::make('activar')
                ->label('Activar')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn ($record) => $record->estado_cliente === 'Inactivo')
                ->action(function ($record) {
                    $record->estado_cliente = 'Activo';
                    $record->save();
                })
                ->requiresConfirmation()
                ->modalHeading('¿Activar cliente?')
                ->modalDescription('¿Estás seguro de que quieres activar este cliente?')
                ->modalSubmitActionLabel('Sí, activar')
                ->modalCancelActionLabel('No, cancelar'),
        ];
    }

    protected function getTableBulkActions(): array
    {
        return [
            BulkAction::make('delete')
                ->label('Inactivar seleccionados')
                ->color('danger')
                ->icon('heroicon-o-trash')
                ->requiresConfirmation()
                ->action(function ($records) {
                    // En lugar de eliminar, cambiar el estado a inactivo para todos los registros seleccionados
                    $records->each(function ($record) {
                        $record->estado_cliente = 'Inactivo';
                        $record->save();
                    });
                })
                ->deselectRecordsAfterCompletion(),

            BulkAction::make('activate')
                ->label('Activar seleccionados')
                ->color('success')
                ->icon('heroicon-o-check-circle')
                ->requiresConfirmation()
                ->action(function ($records) {
                    $records->each(function ($record) {
                        $record->estado_cliente = 'Activo';
                        $record->save();
                    });
                })
                ->deselectRecordsAfterCompletion()
        ];
    }

    /**
     * Procesar traslado de grupos completos cuando se seleccionan clientes que pertenecen a grupos
     */
    protected static function procesarTrasladoGrupos($gruposAfectados, $nuevoAsesorId, $nombreNuevoAsesor)
    {
        $gruposParaTrasladar = collect();
        $gruposConflictivos = collect();
        
        foreach ($gruposAfectados as $grupo) {
            $totalIntegrantes = $grupo->clientes()->count();
            
            // Si el grupo tiene más de un integrante, necesita confirmación adicional
            if ($totalIntegrantes > 1) {
                $gruposConflictivos->push([
                    'grupo' => $grupo,
                    'integrantes' => $totalIntegrantes
                ]);
            } else {
                $gruposParaTrasladar->push($grupo);
            }
        }
        
        // Trasladar grupos de un solo integrante
        if ($gruposParaTrasladar->isNotEmpty()) {
            foreach ($gruposParaTrasladar as $grupo) {
                $grupo->asesor_id = $nuevoAsesorId;
                $grupo->save();
                
                // Actualizar también el cliente
                $cliente = $grupo->clientes()->first();
                $cliente->asesor_id = $nuevoAsesorId;
                $cliente->save();
            }
            
            \Filament\Notifications\Notification::make()
                ->success()
                ->title('Grupos Trasladados')
                ->body($gruposParaTrasladar->count() . " grupo(s) de un integrante han sido trasladados exitosamente al asesor {$nombreNuevoAsesor}.")
                ->send();
        }
        
        // Mostrar alerta para grupos con múltiples integrantes
        if ($gruposConflictivos->isNotEmpty()) {
            $mensajeGrupos = $gruposConflictivos->map(function ($item) {
                return "• {$item['grupo']->nombre_grupo} ({$item['integrantes']} integrantes)";
            })->join("\n");
            
            \Filament\Notifications\Notification::make()
                ->warning()
                ->title('Grupos con Múltiples Integrantes Detectados')
                ->body("Los siguientes grupos requieren traslado completo de todos sus integrantes:\n\n{$mensajeGrupos}\n\nPor favor, vaya al módulo de Grupos y use la función 'Cambiar Asesor' para trasladar estos grupos completos al asesor {$nombreNuevoAsesor}.")
                ->persistent()
                ->send();
        }
    }
}
