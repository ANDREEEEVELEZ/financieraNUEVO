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
                    Forms\Components\Section::make('Paso 1: Seleccionar Asesor de Origen')
                        ->description('Seleccione el asesor del cual desea trasladar clientes')
                        ->schema([
                            Forms\Components\Select::make('asesor_origen_id')
                                ->label('Asesor de Origen')
                                ->required()
                                ->options(function () {
                                    return \App\Models\Asesor::where('estado_asesor', 'Activo')
                                        ->with('persona')
                                        ->get()
                                        ->mapWithKeys(function ($asesor) {
                                            $cantidadClientes = \App\Models\Cliente::where('asesor_id', $asesor->id)
                                                ->where('estado_cliente', 'Activo')
                                                ->count();
                                            return [$asesor->id => $asesor->persona->nombre . ' ' . $asesor->persona->apellidos . " ({$cantidadClientes} clientes)"];
                                        });
                                })
                                ->searchable()
                                ->reactive()
                                ->helperText('Seleccione el asesor que actualmente tiene los clientes que desea trasladar')
                                ->afterStateUpdated(fn (callable $set) => $set('clientes_seleccionados', [])),
                        ]),
                    
                    Forms\Components\Section::make('Paso 2: Seleccionar Clientes a Trasladar')
                        ->description('Seleccione los clientes específicos o todos los clientes del asesor')
                        ->schema([
                            Forms\Components\Toggle::make('trasladar_todos')
                                ->label('Trasladar TODOS los clientes del asesor')
                                ->helperText('Active esta opción para trasladar todos los clientes activos del asesor seleccionado')
                                ->reactive()
                                ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                    if ($state) {
                                        // Si se activa "todos", limpiar selección individual
                                        $set('clientes_seleccionados', []);
                                    }
                                }),
                            
                            Forms\Components\CheckboxList::make('clientes_seleccionados')
                                ->label('O seleccionar clientes específicos')
                                ->options(function (callable $get) {
                                    $asesorOrigenId = $get('asesor_origen_id');
                                    if (!$asesorOrigenId) {
                                        return [];
                                    }
                                    
                                    return \App\Models\Cliente::with(['persona', 'grupos'])
                                        ->where('asesor_id', $asesorOrigenId)
                                        ->where('estado_cliente', 'Activo')
                                        ->get()
                                        ->mapWithKeys(function ($cliente) {
                                            $grupoInfo = $cliente->tieneGrupoActivo() 
                                                ? " 🏢 Grupo: {$cliente->grupos()->where('estado_grupo', 'Activo')->first()->nombre_grupo}"
                                                : " 👤 Sin grupo";
                                            return [
                                                $cliente->id => "📋 {$cliente->persona->nombre} {$cliente->persona->apellidos} - DNI: {$cliente->persona->DNI}{$grupoInfo}"
                                            ];
                                        });
                                })
                                ->searchable()
                                ->columns(1)
                                ->hidden(fn (callable $get) => $get('trasladar_todos'))
                                ->reactive()
                                ->afterStateUpdated(function ($state, callable $set) {
                                    if (!empty($state)) {
                                        // Si se seleccionan clientes específicos, desactivar "todos"
                                        $set('trasladar_todos', false);
                                    }
                                })
                                ->helperText('Seleccione clientes específicos si no desea trasladar todos'),
                            
                            Forms\Components\Placeholder::make('info_seleccion')
                                ->content(function (callable $get) {
                                    $asesorOrigenId = $get('asesor_origen_id');
                                    $trasladarTodos = $get('trasladar_todos');
                                    $clientesSeleccionados = $get('clientes_seleccionados') ?? [];
                                    
                                    if (!$asesorOrigenId) {
                                        return '⚠️ Primero seleccione un asesor de origen';
                                    }
                                    
                                    if ($trasladarTodos) {
                                        $totalClientes = \App\Models\Cliente::where('asesor_id', $asesorOrigenId)
                                            ->where('estado_cliente', 'Activo')
                                            ->count();
                                        return "✅ Se trasladarán TODOS los clientes ({$totalClientes} clientes)";
                                    }
                                    
                                    if (!empty($clientesSeleccionados)) {
                                        return "✅ Se trasladarán " . count($clientesSeleccionados) . " cliente(s) seleccionado(s)";
                                    }
                                    
                                    return '⚠️ Seleccione "Trasladar todos" o elija clientes específicos';
                                })
                                ->extraAttributes(['class' => 'text-sm font-medium']),
                        ]),
                    
                    Forms\Components\Section::make('Paso 3: Seleccionar Nuevo Asesor')
                        ->description('Seleccione el asesor al cual se trasladarán los clientes')
                        ->schema([
                            Forms\Components\Select::make('nuevo_asesor_id')
                                ->label('Nuevo Asesor (Destino)')
                                ->required()
                                ->options(function (callable $get) {
                                    $asesorOrigenId = $get('asesor_origen_id');
                                    return \App\Models\Asesor::where('estado_asesor', 'Activo')
                                        ->when($asesorOrigenId, fn($query) => $query->where('id', '!=', $asesorOrigenId))
                                        ->with('persona')
                                        ->get()
                                        ->mapWithKeys(function ($asesor) {
                                            $cantidadClientes = \App\Models\Cliente::where('asesor_id', $asesor->id)
                                                ->where('estado_cliente', 'Activo')
                                                ->count();
                                            return [$asesor->id => $asesor->persona->nombre . ' ' . $asesor->persona->apellidos . " ({$cantidadClientes} clientes actuales)"];
                                        });
                                })
                                ->searchable()
                                ->helperText('Seleccione el asesor que recibirá los clientes trasladados'),
                        ]),
                ])
                ->action(function ($data) {
                    $asesorOrigenId = $data['asesor_origen_id'];
                    $nuevoAsesorId = $data['nuevo_asesor_id'];
                    $trasladarTodos = $data['trasladar_todos'] ?? false;
                    $clientesSeleccionadosIds = $data['clientes_seleccionados'] ?? [];
                    
                    // Obtener información de asesores
                    $asesorOrigen = \App\Models\Asesor::with('persona')->find($asesorOrigenId);
                    $nuevoAsesor = \App\Models\Asesor::with('persona')->find($nuevoAsesorId);
                    $nombreAsesorOrigen = $asesorOrigen->persona->nombre . ' ' . $asesorOrigen->persona->apellidos;
                    $nombreNuevoAsesor = $nuevoAsesor->persona->nombre . ' ' . $nuevoAsesor->persona->apellidos;
                    
                    // Determinar qué clientes trasladar
                    if ($trasladarTodos) {
                        $clientes = \App\Models\Cliente::with(['persona', 'grupos'])
                            ->where('asesor_id', $asesorOrigenId)
                            ->where('estado_cliente', 'Activo')
                            ->get();
                    } else {
                        $clientes = \App\Models\Cliente::with(['persona', 'grupos'])
                            ->whereIn('id', $clientesSeleccionadosIds)
                            ->get();
                    }
                    
                    if ($clientes->isEmpty()) {
                        \Filament\Notifications\Notification::make()
                            ->warning()
                            ->title('Sin clientes para trasladar')
                            ->body('No se encontraron clientes para trasladar.')
                            ->send();
                        return;
                    }
                    
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
                            ->title('Clientes Trasladados Exitosamente')
                            ->body($clientesSinGrupo->count() . " cliente(s) sin grupo han sido trasladados de {$nombreAsesorOrigen} a {$nombreNuevoAsesor}.")
                            ->send();
                    }
                    
                    // Procesar grupos que necesitan traslado completo
                    if ($gruposAfectados->isNotEmpty()) {
                        static::procesarTrasladoGrupos($gruposAfectados, $nuevoAsesorId, $nombreNuevoAsesor, $nombreAsesorOrigen);
                    }
                    
                    // Mensaje final de resumen
                    \Filament\Notifications\Notification::make()
                        ->info()
                        ->title('Traslado Completado')
                        ->body("✅ Proceso de traslado finalizado.\n📤 Origen: {$nombreAsesorOrigen}\n📥 Destino: {$nombreNuevoAsesor}\n👥 Total procesado: {$clientes->count()} cliente(s)")
                        ->persistent()
                        ->send();
                })
                ->modalHeading('🔄 Trasladar Clientes Entre Asesores')
                ->modalSubmitActionLabel('Ejecutar Traslado')
                ->modalWidth('5xl'),
            
            // Acción para generar PDF de Declaración Jurada PEP
            Actions\Action::make('generar_pep_pdf')
                ->label('Generar DJ PEP')
                ->icon('heroicon-o-document-text')
                ->color('info')
                ->visible(fn () => request()->user() && request()->user()->hasAnyRole(['super_admin', 'Jefe de creditos', 'Jefe de operaciones']))
                ->form([
                    Forms\Components\Select::make('cliente_id')
                        ->label('Seleccionar Cliente PEP')
                        ->placeholder('Buscar cliente...')
                        ->options(function () {
                            return \App\Models\Cliente::where('condicion_personal', 'PEP')
                                ->where('estado_cliente', 'Activo')
                                ->with('persona')
                                ->get()
                                ->mapWithKeys(function ($cliente) {
                                    return [$cliente->id => $cliente->persona->nombre . ' ' . $cliente->persona->apellidos . ' - DNI: ' . $cliente->persona->DNI];
                                });
                        })
                        ->searchable()
                        ->required()
                        ->helperText('Solo se muestran clientes con condición PEP activos')
                ])
                ->action(function ($data) {
                    $cliente = \App\Models\Cliente::with('persona')->find($data['cliente_id']);
                    
                    if (!$cliente) {
                        \Filament\Notifications\Notification::make()
                            ->danger()
                            ->title('Error')
                            ->body('Cliente no encontrado.')
                            ->send();
                        return;
                    }

                    try {
                        $pdfContent = app(\App\Services\PepDocumentService::class)->generatePdf($cliente);
                        $filename = "DJ_PEP_{$cliente->persona->DNI}_{$cliente->persona->nombre}_{$cliente->persona->apellidos}.pdf";
                        
                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title('PDF Generado')
                            ->body('Declaración Jurada PEP generada exitosamente.')
                            ->send();

                        return response()->streamDownload(function () use ($pdfContent) {
                            echo $pdfContent;
                        }, $filename);
                        
                    } catch (\Exception $e) {
                        \Filament\Notifications\Notification::make()
                            ->danger()
                            ->title('Error al generar PDF')
                            ->body('Ocurrió un error al generar el documento: ' . $e->getMessage())
                            ->send();
                    }
                })
                ->modalHeading('📄 Generar Declaración Jurada PEP')
                ->modalSubmitActionLabel('Generar PDF')
                ->modalWidth('lg'),
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
    protected static function procesarTrasladoGrupos($gruposAfectados, $nuevoAsesorId, $nombreNuevoAsesor, $nombreAsesorOrigen = null)
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
            
            $mensajeOrigen = $nombreAsesorOrigen ? " (desde {$nombreAsesorOrigen})" : "";
            \Filament\Notifications\Notification::make()
                ->success()
                ->title('Grupos Trasladados')
                ->body($gruposParaTrasladar->count() . " grupo(s) de un integrante han sido trasladados exitosamente{$mensajeOrigen} al asesor {$nombreNuevoAsesor}.")
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
