<?php

namespace App\Filament\Dashboard\Resources\GrupoResource\Pages;

use App\Filament\Dashboard\Resources\GrupoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use App\Models\Grupo;
use App\Models\Cliente;
use App\Models\Asesor;
use Filament\Forms;

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

        // Acción de traslado masivo de grupos entre asesores (solo para super_admin y jefe de operaciones)
        if ($user && $user->hasAnyRole(['super_admin', 'Jefe de operaciones'])) {
            $actions[] = Actions\Action::make('trasladar_grupos_asesor')
                ->label('Trasladar Grupos de Asesor')
                ->icon('heroicon-o-arrow-right-circle')
                ->color('warning')
                ->form([
                    \Filament\Forms\Components\Section::make('Paso 1: Seleccionar Asesor de Origen')
                        ->description('Seleccione el asesor del cual desea trasladar grupos')
                        ->schema([
                            \Filament\Forms\Components\Select::make('asesor_origen_id')
                                ->label('Asesor de Origen')
                                ->required()
                                ->options(function () {
                                    return \App\Models\Asesor::where('estado_asesor', 'Activo')
                                        ->with('persona')
                                        ->get()
                                        ->mapWithKeys(function ($asesor) {
                                            $cantidadGrupos = \App\Models\Grupo::where('asesor_id', $asesor->id)
                                                ->where('estado_grupo', 'Activo')
                                                ->count();
                                            $cantidadClientes = \App\Models\Cliente::where('asesor_id', $asesor->id)
                                                ->where('estado_cliente', 'Activo')
                                                ->count();
                                            return [$asesor->id => $asesor->persona->nombre . ' ' . $asesor->persona->apellidos . " ({$cantidadGrupos} grupos, {$cantidadClientes} clientes)"];
                                        });
                                })
                                ->searchable()
                                ->reactive()
                                ->helperText('Seleccione el asesor del cual desea redistribuir grupos para equilibrar carga de trabajo')
                                ->afterStateUpdated(fn (callable $set) => $set('grupos_seleccionados', [])),
                        ]),
                    
                    \Filament\Forms\Components\Section::make('Paso 2: Seleccionar Grupos a Trasladar')
                        ->description('Elija si trasladar todos los grupos o seleccionar grupos específicos')
                        ->schema([
                            \Filament\Forms\Components\Toggle::make('trasladar_todos_grupos')
                                ->label('Trasladar TODOS los grupos del asesor')
                                ->helperText('Active esta opción para trasladar todos los grupos activos del asesor seleccionado')
                                ->reactive()
                                ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                    if ($state) {
                                        // Si se activa "todos", limpiar selección individual
                                        $set('grupos_seleccionados', []);
                                    }
                                }),
                            
                            \Filament\Forms\Components\CheckboxList::make('grupos_seleccionados')
                                ->label('O seleccionar grupos específicos')
                                ->options(function (callable $get) {
                                    $asesorOrigenId = $get('asesor_origen_id');
                                    if (!$asesorOrigenId) {
                                        return [];
                                    }
                                    
                                    return \App\Models\Grupo::where('asesor_id', $asesorOrigenId)
                                        ->where('estado_grupo', 'Activo')
                                        ->with(['clientes'])
                                        ->get()
                                        ->mapWithKeys(function ($grupo) {
                                            $integrantes = $grupo->clientes()->count();
                                            $clientesSueltos = \App\Models\Cliente::where('asesor_id', $grupo->asesor_id)
                                                ->where('estado_cliente', 'Activo')
                                                ->whereDoesntHave('grupos', function($query) {
                                                    $query->where('estado_grupo', 'Activo');
                                                })
                                                ->count();
                                            
                                            $infoAdicional = $clientesSueltos > 0 ? " + {$clientesSueltos} clientes sueltos" : "";
                                            return [
                                                $grupo->id => "🏢 {$grupo->nombre_grupo} ({$integrantes} integrantes{$infoAdicional})"
                                            ];
                                        });
                                })
                                ->searchable()
                                ->columns(1)
                                ->hidden(fn (callable $get) => $get('trasladar_todos_grupos'))
                                ->reactive()
                                ->afterStateUpdated(function ($state, callable $set) {
                                    if (!empty($state)) {
                                        // Si se seleccionan grupos específicos, desactivar "todos"
                                        $set('trasladar_todos_grupos', false);
                                    }
                                })
                                ->helperText('Seleccione grupos específicos para equilibrar la carga de trabajo entre asesores'),
                            
                            \Filament\Forms\Components\Placeholder::make('info_seleccion_grupos')
                                ->content(function (callable $get) {
                                    $asesorOrigenId = $get('asesor_origen_id');
                                    $trasladarTodos = $get('trasladar_todos_grupos');
                                    $gruposSeleccionados = $get('grupos_seleccionados') ?? [];
                                    
                                    if (!$asesorOrigenId) {
                                        return '⚠️ Primero seleccione un asesor de origen';
                                    }
                                    
                                    if ($trasladarTodos) {
                                        $grupos = \App\Models\Grupo::where('asesor_id', $asesorOrigenId)
                                            ->where('estado_grupo', 'Activo')
                                            ->with(['clientes'])
                                            ->get();
                                        
                                        if ($grupos->isEmpty()) {
                                            return '❌ Este asesor no tiene grupos activos';
                                        }
                                        
                                        $totalClientes = $grupos->sum(function($grupo) {
                                            return $grupo->clientes()->count();
                                        });
                                        
                                        $clientesSueltos = \App\Models\Cliente::where('asesor_id', $asesorOrigenId)
                                            ->where('estado_cliente', 'Activo')
                                            ->whereDoesntHave('grupos', function($query) {
                                                $query->where('estado_grupo', 'Activo');
                                            })
                                            ->count();
                                        
                                        return "✅ Se trasladarán TODOS los grupos:\n🏢 {$grupos->count()} grupos\n👥 {$totalClientes} clientes en grupos\n👤 {$clientesSueltos} clientes sueltos\n📊 Total: " . ($totalClientes + $clientesSueltos) . " clientes";
                                    }
                                    
                                    if (!empty($gruposSeleccionados)) {
                                        $grupos = \App\Models\Grupo::whereIn('id', $gruposSeleccionados)
                                            ->with(['clientes'])
                                            ->get();
                                        
                                        $totalClientes = $grupos->sum(function($grupo) {
                                            return $grupo->clientes()->count();
                                        });
                                        
                                        return "✅ Se trasladarán " . count($gruposSeleccionados) . " grupo(s) seleccionado(s)\n👥 {$totalClientes} clientes en total";
                                    }
                                    
                                    return '⚠️ Seleccione "Trasladar todos" o elija grupos específicos';
                                })
                                ->extraAttributes(['class' => 'text-sm font-medium whitespace-pre-line']),
                        ]),
                    
                    \Filament\Forms\Components\Section::make('Paso 3: Seleccionar Nuevo Asesor')
                        ->description('Seleccione el asesor que recibirá los grupos y clientes')
                        ->schema([
                            \Filament\Forms\Components\Select::make('nuevo_asesor_id')
                                ->label('Nuevo Asesor (Destino)')
                                ->required()
                                ->options(function (callable $get) {
                                    $asesorOrigenId = $get('asesor_origen_id');
                                    return \App\Models\Asesor::where('estado_asesor', 'Activo')
                                        ->when($asesorOrigenId, fn($query) => $query->where('id', '!=', $asesorOrigenId))
                                        ->with('persona')
                                        ->get()
                                        ->mapWithKeys(function ($asesor) {
                                            $cantidadGrupos = \App\Models\Grupo::where('asesor_id', $asesor->id)
                                                ->where('estado_grupo', 'Activo')
                                                ->count();
                                            $cantidadClientes = \App\Models\Cliente::where('asesor_id', $asesor->id)
                                                ->where('estado_cliente', 'Activo')
                                                ->count();
                                            return [$asesor->id => $asesor->persona->nombre . ' ' . $asesor->persona->apellidos . " ({$cantidadGrupos} grupos actuales, {$cantidadClientes} clientes actuales)"];
                                        });
                                })
                                ->searchable()
                                ->helperText('Este asesor recibirá los grupos seleccionados y todos sus clientes'),
                        ]),
                ])
                ->action(function ($data) {
                    $asesorOrigenId = $data['asesor_origen_id'];
                    $nuevoAsesorId = $data['nuevo_asesor_id'];
                    $trasladarTodos = $data['trasladar_todos_grupos'] ?? false;
                    $gruposSeleccionadosIds = $data['grupos_seleccionados'] ?? [];
                    
                    // Obtener información de asesores
                    $asesorOrigen = \App\Models\Asesor::with('persona')->find($asesorOrigenId);
                    $nuevoAsesor = \App\Models\Asesor::with('persona')->find($nuevoAsesorId);
                    $nombreAsesorOrigen = $asesorOrigen->persona->nombre . ' ' . $asesorOrigen->persona->apellidos;
                    $nombreNuevoAsesor = $nuevoAsesor->persona->nombre . ' ' . $nuevoAsesor->persona->apellidos;
                    
                    // Determinar qué grupos trasladar
                    if ($trasladarTodos) {
                        $grupos = \App\Models\Grupo::where('asesor_id', $asesorOrigenId)
                            ->where('estado_grupo', 'Activo')
                            ->get();
                        $tipoTraslado = "TODOS los grupos";
                    } else {
                        $grupos = \App\Models\Grupo::whereIn('id', $gruposSeleccionadosIds)->get();
                        $tipoTraslado = count($gruposSeleccionadosIds) . " grupo(s) seleccionado(s)";
                    }
                    
                    if ($grupos->isEmpty()) {
                        \Filament\Notifications\Notification::make()
                            ->warning()
                            ->title('Sin grupos para trasladar')
                            ->body('No se encontraron grupos para trasladar.')
                            ->send();
                        return;
                    }
                    
                    $totalGruposTrasladados = 0;
                    $totalClientesTrasladados = 0;
                    
                    // Trasladar cada grupo y sus clientes
                    foreach ($grupos as $grupo) {
                        // Trasladar grupo
                        $grupo->asesor_id = $nuevoAsesorId;
                        $grupo->save();
                        $totalGruposTrasladados++;
                        
                        // Trasladar todos los clientes del grupo
                        $clientesGrupo = $grupo->clientes;
                        foreach ($clientesGrupo as $cliente) {
                            $cliente->asesor_id = $nuevoAsesorId;
                            $cliente->save();
                            $totalClientesTrasladados++;
                        }
                    }
                    
                    // Si trasladó TODOS los grupos, también trasladar clientes sueltos
                    if ($trasladarTodos) {
                        $clientesSueltos = \App\Models\Cliente::where('asesor_id', $asesorOrigenId)
                            ->where('estado_cliente', 'Activo')
                            ->whereDoesntHave('grupos', function($query) {
                                $query->where('estado_grupo', 'Activo');
                            })
                            ->get();
                        
                        foreach ($clientesSueltos as $clienteSuelto) {
                            $clienteSuelto->asesor_id = $nuevoAsesorId;
                            $clienteSuelto->save();
                            $totalClientesTrasladados++;
                        }
                    }
                    
                    // Notificación de éxito
                    \Filament\Notifications\Notification::make()
                        ->success()
                        ->title('Traslado de Grupos Completado Exitosamente')
                        ->body("✅ Traslado completado:\n📤 Desde: {$nombreAsesorOrigen}\n📥 Hacia: {$nombreNuevoAsesor}\n📋 Trasladados: {$tipoTraslado}\n🏢 {$totalGruposTrasladados} grupos trasladados\n👥 {$totalClientesTrasladados} clientes trasladados\n\n🎉 Los grupos y clientes seleccionados ahora pertenecen a {$nombreNuevoAsesor}")
                        ->persistent()
                        ->send();
                })
                ->modalHeading('🔄 Trasladar Grupos Entre Asesores')
                ->modalSubmitActionLabel('Ejecutar Traslado')
                ->modalWidth('5xl')
                ->requiresConfirmation()
                ->modalDescription('Esta acción trasladará los grupos seleccionados y todos sus clientes al nuevo asesor. Los clientes de estos grupos también cambiarán de asesor automáticamente.');
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
