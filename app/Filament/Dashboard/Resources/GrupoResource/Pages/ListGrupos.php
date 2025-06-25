<?php

namespace App\Filament\Dashboard\Resources\GrupoResource\Pages;

use App\Filament\Dashboard\Resources\GrupoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use App\Models\Grupo;
use App\Models\Cliente;
use App\Models\Asesor;

class ListGrupos extends ListRecords
{
    protected static string $resource = GrupoResource::class;

    protected function getHeaderActions(): array
    {
        $user = request()->user();
        
        $actions = [
            Actions\CreateAction::make()
                ->icon('heroicon-o-plus-circle'),
        ];

        // Solo mostrar acciones de gestión para roles autorizados
        if ($user && $user->hasAnyRole(['super_admin', 'Jefe de operaciones', 'Asesor'])) {
            $actions[] = Actions\Action::make('remover_integrantes_header')
                ->label('Remover Integrantes')
                ->icon('heroicon-o-user-minus')
                ->color('danger')
                ->form([
                    \Filament\Forms\Components\Select::make('grupo_seleccionado')
                        ->label('Seleccionar Grupo')
                        ->required()
                        ->searchable()
                        ->options(function () use ($user) {
                            $query = \App\Models\Grupo::where('estado_grupo', 'Activo');
                            
                            // Filtrar por asesor si es necesario
                            if ($user->hasRole('Asesor')) {
                                $asesor = \App\Models\Asesor::where('user_id', $user->id)->first();
                                if ($asesor) {
                                    $query->where('asesor_id', $asesor->id);
                                }
                            }
                            
                            return $query->get()
                                ->filter(function($grupo) {
                                    return !$grupo->tienePrestamosActivos() && $grupo->clientes()->count() > 0;
                                })
                                ->mapWithKeys(function($grupo) {
                                    return [$grupo->id => $grupo->nombre_grupo . ' (' . $grupo->clientes()->count() . ' integrantes)'];
                                });
                        })
                        ->reactive()
                        ->afterStateUpdated(function ($state, callable $set) {
                            if ($state) {
                                $grupo = \App\Models\Grupo::find($state);
                                if ($grupo) {
                                    $opciones = $grupo->clientes()
                                        ->with('persona')
                                        ->get()
                                        ->mapWithKeys(function($cliente) {
                                            $esLider = $cliente->pivot->rol === 'Líder Grupal' ? ' (LÍDER GRUPAL)' : '';
                                            return [$cliente->id => $cliente->persona->nombre . ' ' . $cliente->persona->apellidos . ' (DNI: ' . $cliente->persona->DNI . ')' . $esLider];
                                        });
                                    $set('clientes_disponibles', $opciones->toArray());
                                }
                            }
                        }),
                    \Filament\Forms\Components\Select::make('clientes_a_remover')
                        ->label('Seleccionar integrantes a remover')
                        ->multiple()
                        ->required()
                        ->options(function (callable $get) {
                            return $get('clientes_disponibles') ?? [];
                        })
                        ->helperText('⚠️ No se puede remover al líder grupal sin antes cambiar el liderazgo')
                        ->reactive()
                        ->afterStateUpdated(function ($state, callable $set, callable $get) {
                            if (!empty($state)) {
                                $grupoId = $get('grupo_seleccionado');
                                if ($grupoId) {
                                    $grupo = \App\Models\Grupo::find($grupoId);
                                    $lider = $grupo->clientes()->wherePivot('rol', 'Líder Grupal')->first();
                                    if ($lider && in_array($lider->id, $state)) {
                                        $integrantesRestantes = $grupo->clientes()->whereNotIn('clientes.id', $state)->count();
                                        if ($integrantesRestantes > 0) {
                                            \Filament\Notifications\Notification::make()
                                                ->danger()
                                                ->title('No se puede remover al líder grupal')
                                                ->body('Debe asignar un nuevo líder antes de remover al líder actual')
                                                ->send();
                                            $set('clientes_a_remover', array_values(array_diff($state, [$lider->id])));
                                        }
                                    }
                                }
                            }
                        }),
                    \Filament\Forms\Components\Hidden::make('clientes_disponibles'),
                    \Filament\Forms\Components\DatePicker::make('fecha_salida')
                        ->label('Fecha de salida')
                        ->default(now())
                        ->required()
                        ->helperText('Fecha en que el integrante sale del grupo'),
                ])
                ->action(function (array $data) {
                    try {
                        $grupo = \App\Models\Grupo::find($data['grupo_seleccionado']);
                        $clientesRemovidosNombres = [];
                        
                        foreach ($data['clientes_a_remover'] as $clienteId) {
                            $cliente = \App\Models\Cliente::with('persona')->find($clienteId);
                            $clientesRemovidosNombres[] = $cliente->persona->nombre . ' ' . $cliente->persona->apellidos;
                            
                            $grupo->removerCliente($clienteId, $data['fecha_salida']);
                        }
                        
                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title('Integrantes removidos exitosamente')
                            ->body('Se removieron ' . count($data['clientes_a_remover']) . ' integrantes del grupo "' . $grupo->nombre_grupo . '": ' . implode(', ', $clientesRemovidosNombres))
                            ->send();
                            
                    } catch (\Exception $e) {
                        \Filament\Notifications\Notification::make()
                            ->danger()
                            ->title('Error al remover integrantes')
                            ->body($e->getMessage())
                            ->send();
                    }
                })
                ->requiresConfirmation()
                ->modalHeading('Confirmar remoción de integrantes')
                ->modalDescription('¿Estás seguro de que deseas remover estos integrantes del grupo seleccionado?');

            $actions[] = Actions\Action::make('transferir_integrante_header')
                ->label('Transferir Integrante')
                ->icon('heroicon-o-arrow-right-circle')
                ->color('warning')
                ->form([
                    \Filament\Forms\Components\Select::make('grupo_origen')
                        ->label('Grupo Origen')
                        ->required()
                        ->searchable()
                        ->options(function () use ($user) {
                            $query = \App\Models\Grupo::where('estado_grupo', 'Activo');
                            
                            // Filtrar por asesor si es necesario
                            if ($user->hasRole('Asesor')) {
                                $asesor = \App\Models\Asesor::where('user_id', $user->id)->first();
                                if ($asesor) {
                                    $query->where('asesor_id', $asesor->id);
                                }
                            }
                            
                            return $query->get()
                                ->filter(function($grupo) {
                                    return !$grupo->tienePrestamosActivos() && $grupo->clientes()->count() > 0;
                                })
                                ->mapWithKeys(function($grupo) {
                                    return [$grupo->id => $grupo->nombre_grupo . ' (' . $grupo->clientes()->count() . ' integrantes)'];
                                });
                        })
                        ->reactive()
                        ->afterStateUpdated(function ($state, callable $set) use ($user) {
                            if ($state) {
                                $grupo = \App\Models\Grupo::find($state);
                                if ($grupo) {
                                    // Actualizar opciones de clientes
                                    $opcionesClientes = $grupo->clientes()
                                        ->with('persona')
                                        ->get()
                                        ->mapWithKeys(function($cliente) {
                                            $esLider = $cliente->pivot->rol === 'Líder Grupal' ? ' (LÍDER GRUPAL)' : '';
                                            return [$cliente->id => $cliente->persona->nombre . ' ' . $cliente->persona->apellidos . ' (DNI: ' . $cliente->persona->DNI . ')' . $esLider];
                                        });
                                    $set('clientes_origen_disponibles', $opcionesClientes->toArray());
                                    
                                    // Actualizar opciones de grupos destino
                                    $queryDestino = \App\Models\Grupo::where('id', '!=', $state)
                                        ->where('estado_grupo', 'Activo');
                                    
                                    if ($user->hasRole('Asesor')) {
                                        $asesor = \App\Models\Asesor::where('user_id', $user->id)->first();
                                        if ($asesor) {
                                            $queryDestino->where('asesor_id', $asesor->id);
                                        }
                                    }
                                    
                                    $opcionesDestino = $queryDestino->get()
                                        ->filter(function($grupo) {
                                            return !$grupo->tienePrestamosActivos();
                                        })
                                        ->mapWithKeys(function($grupo) {
                                            return [$grupo->id => $grupo->nombre_grupo . ' (' . $grupo->clientes()->count() . ' integrantes)'];
                                        });
                                    $set('grupos_destino_disponibles', $opcionesDestino->toArray());
                                }
                            }
                        }),
                    \Filament\Forms\Components\Select::make('cliente_a_transferir')
                        ->label('Cliente a transferir')
                        ->required()
                        ->options(function (callable $get) {
                            return $get('clientes_origen_disponibles') ?? [];
                        })
                        ->helperText('⚠️ Si transfiere al líder grupal, el grupo se quedará sin líder')
                        ->reactive()
                        ->afterStateUpdated(function ($state, callable $get) {
                            if ($state) {
                                $grupoId = $get('grupo_origen');
                                if ($grupoId) {
                                    $grupo = \App\Models\Grupo::find($grupoId);
                                    $cliente = $grupo->clientes()->where('clientes.id', $state)->first();
                                    if ($cliente && $cliente->pivot->rol === 'Líder Grupal') {
                                        $integrantesRestantes = $grupo->clientes()->where('clientes.id', '!=', $state)->count();
                                        if ($integrantesRestantes > 0) {
                                            \Filament\Notifications\Notification::make()
                                                ->warning()
                                                ->title('Transfiriendo al líder grupal')
                                                ->body('El grupo se quedará sin líder. Asegúrese de asignar un nuevo líder después.')
                                                ->send();
                                        }
                                    }
                                }
                            }
                        }),
                    \Filament\Forms\Components\Select::make('grupo_destino')
                        ->label('Grupo Destino')
                        ->required()
                        ->options(function (callable $get) {
                            return $get('grupos_destino_disponibles') ?? [];
                        })
                        ->helperText('Solo se muestran grupos sin préstamos activos'),
                    \Filament\Forms\Components\Hidden::make('clientes_origen_disponibles'),
                    \Filament\Forms\Components\Hidden::make('grupos_destino_disponibles'),
                    \Filament\Forms\Components\DatePicker::make('fecha_transferencia')
                        ->label('Fecha de transferencia')
                        ->default(now())
                        ->required()
                        ->helperText('Fecha en que se realiza la transferencia'),
                ])
                ->action(function (array $data) {
                    try {
                        $grupoOrigen = \App\Models\Grupo::find($data['grupo_origen']);
                        $grupoDestino = \App\Models\Grupo::find($data['grupo_destino']);
                        $cliente = \App\Models\Cliente::with('persona')->find($data['cliente_a_transferir']);
                        
                        $grupoOrigen->transferirClienteAGrupo(
                            $data['cliente_a_transferir'], 
                            $data['grupo_destino'], 
                            $data['fecha_transferencia']
                        );
                        
                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title('Integrante transferido exitosamente')
                            ->body("El cliente {$cliente->persona->nombre} {$cliente->persona->apellidos} ha sido transferido del grupo \"{$grupoOrigen->nombre_grupo}\" al grupo \"{$grupoDestino->nombre_grupo}\".")
                            ->send();
                            
                    } catch (\Exception $e) {
                        \Filament\Notifications\Notification::make()
                            ->danger()
                            ->title('Error al transferir integrante')
                            ->body($e->getMessage())
                            ->send();
                    }
                })
                ->requiresConfirmation()
                ->modalHeading('Confirmar transferencia de integrante')
                ->modalDescription('¿Estás seguro de que deseas transferir este integrante al grupo seleccionado?');
        }

        return $actions;
    }

    protected function getTableActions(): array
    {
        return [
            Actions\Action::make('imprimir_contratos')
                ->label('Imprimir Contratos')
                ->icon('heroicon-o-printer')
                ->color('success')
                ->url(fn($record) => route('contratos.grupo.imprimir', $record->id))
                ->openUrlInNewTab(),
        ];
    }
}
