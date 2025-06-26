<?php


namespace App\Filament\Dashboard\Resources;


use App\Filament\Dashboard\Resources\GrupoResource\Pages;
use App\Filament\Dashboard\Resources\GrupoResource\RelationManagers;
use App\Models\Grupo;
use App\Models\Cliente;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;


class GrupoResource extends Resource
{
    protected static ?string $model = Grupo::class;


protected static ?string $navigationIcon = 'heroicon-o-user-group';



    public static function form(Form $form): Form
    {
        $user = request()->user();
        $record = request()->route('record');
        $grupo = $record ? \App\Models\Grupo::find($record) : null;
        $isInactivo = $grupo && $grupo->estado_grupo === 'Inactivo';
        return $form
            ->schema([
                // Campo asesor solo para super_admin y jefe de operaciones
                Forms\Components\Select::make('asesor_id')
                    ->label('Asesor')
                    ->options(function () {
                        return \App\Models\Asesor::where('estado_asesor', 'Activo')
                            ->with('persona')
                            ->get()
                            ->mapWithKeys(function ($asesor) {
                                return [$asesor->id => $asesor->persona->nombre . ' ' . $asesor->persona->apellidos];
                            });
                    })
                    ->searchable()
                    ->required()
                    ->reactive()
                    ->visible(fn () => $user && $user->hasAnyRole(['super_admin', 'Jefe de operaciones']))
                    ->disabled(fn () => $isInactivo),
                Forms\Components\TextInput::make('nombre_grupo')
                    ->maxLength(255)
                    ->prefixIcon('heroicon-o-tag')
                    ->required()
                    ->disabled(fn () => $isInactivo),
                Forms\Components\DatePicker::make('fecha_registro')
                    ->required()
                    ->prefixIcon('heroicon-o-calendar')
                    ->disabled(fn () => $isInactivo),
                Forms\Components\TextInput::make('calificacion_grupo')
                    ->prefixIcon('heroicon-o-star')
                    ->maxLength(255)
                    ->disabled(fn () => $isInactivo),
                Forms\Components\TextInput::make('estado_grupo')
                    ->prefixIcon('heroicon-o-check-circle')
                    ->default('Activo')
                    ->maxLength(255)
                    ->disabled()
                    ->dehydrated(),
                Forms\Components\Select::make('clientes')
                    ->label('Integrantes')
                    ->prefixIcon('heroicon-o-user-group')
                    ->multiple()
                    ->options(function (callable $get) use ($user) {
                        // Si es asesor, mostrar solo sus clientes
                        if ($user->hasRole('Asesor')) {
                            $asesor = \App\Models\Asesor::where('user_id', $user->id)->first();
                            if ($asesor) {
                                return Cliente::where('asesor_id', $asesor->id)
                                    ->with(['persona', 'grupos' => function ($query) {
                                        $query->where('estado_grupo', 'Activo');
                                    }])
                                    ->join('personas', 'clientes.persona_id', '=', 'personas.id')
                                    ->orderBy('personas.nombre')
                                    ->orderBy('personas.apellidos')
                                    ->select('clientes.*')
                                    ->get()
                                    ->mapWithKeys(function($cliente) {
                                        if ($cliente->tieneGrupoActivo()) {
                                            $grupoActivo = $cliente->grupoActivo;
                                            return [$cliente->id => "{$cliente->persona->nombre} {$cliente->persona->apellidos} (DNI: {$cliente->persona->DNI}) - Ya pertenece al grupo: {$grupoActivo->nombre_grupo}"];
                                        }
                                        return [$cliente->id => "{$cliente->persona->nombre} {$cliente->persona->apellidos} (DNI: {$cliente->persona->DNI})"];
                                    });
                            }
                        }
                        // Si es super_admin o jefe de operaciones, filtrar por asesor seleccionado
                        if ($user->hasAnyRole(['super_admin', 'Jefe de operaciones', 'Jefe de creditos'])) {
                            $asesorId = $get('asesor_id');
                            if ($asesorId) {
                                return Cliente::where('asesor_id', $asesorId)
                                    ->with(['persona', 'grupos' => function ($query) {
                                        $query->where('estado_grupo', 'Activo');
                                    }])
                                    ->join('personas', 'clientes.persona_id', '=', 'personas.id')
                                    ->orderBy('personas.nombre')
                                    ->orderBy('personas.apellidos')
                                    ->select('clientes.*')
                                    ->get()
                                    ->mapWithKeys(function($cliente) {
                                        if ($cliente->tieneGrupoActivo()) {
                                            $grupoActivo = $cliente->grupoActivo;
                                            return [$cliente->id => "{$cliente->persona->nombre} {$cliente->persona->apellidos} (DNI: {$cliente->persona->DNI}) - Ya pertenece al grupo: {$grupoActivo->nombre_grupo}"];
                                        }
                                        return [$cliente->id => "{$cliente->persona->nombre} {$cliente->persona->apellidos} (DNI: {$cliente->persona->DNI})"];
                                    });
                            }
                            return [];
                        }
                        // Si no aplica, retornar vacío
                        return [];
                    })
                    ->searchable()
                    ->preload()
                    ->required()
                    ->reactive()
                    ->afterStateUpdated(function ($state, callable $get, callable $set) {
                        if (!empty($state)) {
                            // Obtener el record actual (grupo en edición)
                            $record = request()->route('record');
                            $grupo = $record ? \App\Models\Grupo::find($record) : null;
                            
                            // Verificar si hay clientes con grupo activo (excluyendo el grupo actual si estamos editando)
                            $clientesConGrupo = Cliente::whereIn('clientes.id', $state)
                                ->get()
                                ->filter(function ($cliente) use ($grupo) {
                                    if (!$cliente->tieneGrupoActivo()) {
                                        return false;
                                    }
                                    // Si estamos editando, permitir clientes que ya pertenecen a este grupo
                                    if ($grupo) {
                                        $grupoActivo = $cliente->grupoActivo;
                                        return $grupoActivo->id !== $grupo->id;
                                    }
                                    return true;
                                });
                            
                            if ($clientesConGrupo->isNotEmpty()) {
                                $mensajesError = $clientesConGrupo->map(function ($cliente) {
                                    $grupoActivo = $cliente->grupoActivo;
                                    return "{$cliente->persona->nombre} {$cliente->persona->apellidos} ya pertenece al grupo {$grupoActivo->nombre_grupo}";
                                })->join("\n");
                                
                                Notification::make()
                                    ->danger()
                                    ->title('Error al agregar clientes')
                                    ->body($mensajesError)
                                    ->persistent()
                                    ->send();
                                
                                // Solo remover los clientes conflictivos, mantener los demás
                                $clientesConflictivos = $clientesConGrupo->pluck('id')->toArray();
                                $clientesValidos = array_diff($state, $clientesConflictivos);
                                $set('clientes', array_values($clientesValidos));
                            }
                        }
                    })
                    ->disabled(fn () => $isInactivo),
                Forms\Components\Select::make('lider_grupal')
    ->label('Líder Grupal')
    ->prefixIcon('heroicon-o-user-circle')
    ->options(function (callable $get) {
        $ids = $get('clientes') ?? [];
        return Cliente::with('persona')
            ->whereIn('clientes.id', $ids)
            ->join('personas', 'clientes.persona_id', '=', 'personas.id')
            ->orderBy('personas.nombre')
            ->orderBy('personas.apellidos')
            ->select('clientes.*')
            ->get()
            ->mapWithKeys(function($cliente) {
                return [$cliente->id => $cliente->persona->nombre . ' ' . $cliente->persona->apellidos . ' (DNI: ' . $cliente->persona->DNI . ')'];
            })
            ->toArray();
                    })
                    ->required()
                    ->searchable()
                    ->visible(fn(callable $get) => !empty($get('clientes')))
                    ->disabled(fn () => $isInactivo),
                Forms\Components\TextInput::make('numero_integrantes')
                    ->label('Numero de Integrantes')
                    ->prefixIcon('heroicon-o-hashtag')
                    ->disabled()
                    ->dehydrated(false)
                    ->reactive()
                    ->afterStateHydrated(function ($component, $state, $record) {
                        if ($record) {
                            $component->state($record->clientes()->count());
                        }
                    })
                    ->afterStateUpdated(function ($state, $set, $get) {
                        $set('numero_integrantes', is_array($get('clientes')) ? count($get('clientes')) : 0);
                    }),

                // Campo para mostrar información sobre restricciones de préstamos
                Forms\Components\Placeholder::make('restriccion_prestamos')
                    ->label('Información importante')
                    ->content(function ($record) {
                        if ($record && $record->tienePrestamosActivos()) {
                            return '⚠️ Este grupo tiene préstamos activos. No se pueden realizar cambios en los integrantes usando las acciones de la tabla.';
                        }
                        return '✅ Este grupo no tiene préstamos activos. Se pueden realizar cambios en los integrantes usando las acciones de la tabla.';
                    })
                    ->visible(fn ($record) => $record !== null)
                    ->extraAttributes(['class' => 'font-semibold']),

                // Campo para mostrar ex-integrantes
                Forms\Components\Placeholder::make('ex_integrantes_info')
                    ->label('Ex-integrantes')
                    ->content(function ($record) {
                        if (!$record) return 'Ninguno';
                        $exIntegrantes = $record->exIntegrantes;
                        if ($exIntegrantes->isEmpty()) {
                            return 'Ninguno';
                        }
                        return $exIntegrantes->map(function($cliente) {
                            $fechaSalida = $cliente->pivot->fecha_salida ? 
                                ' (Salió: ' . \Carbon\Carbon::parse($cliente->pivot->fecha_salida)->format('d/m/Y') . ')' : '';
                            return '• ' . $cliente->persona->nombre . ' ' . $cliente->persona->apellidos . $fechaSalida;
                        })->implode("\n");
                    })
                    ->visible(fn ($record) => $record !== null)
                    ->extraAttributes(['style' => 'white-space: pre-line;']),

                // Sección para gestión rápida de integrantes (solo en edición)
                Forms\Components\Section::make('Gestión Rápida de Integrantes')
                    ->schema([
                        Forms\Components\Placeholder::make('gestion_info')
                            ->content('Utiliza estos campos para realizar cambios rápidos. Los cambios se aplicarán al guardar el formulario.')
                            ->visible(fn ($record) => $record !== null && !$record->tienePrestamosActivos()),
                        
                        Forms\Components\Placeholder::make('gestion_bloqueada')
                            ->content('⚠️ No se pueden realizar cambios porque el grupo tiene préstamos activos.')
                            ->visible(fn ($record) => $record !== null && $record->tienePrestamosActivos())
                            ->extraAttributes(['class' => 'text-red-600 font-semibold']),

                        Forms\Components\Select::make('remover_integrantes_form')
                            ->label('Remover Integrantes')
                            ->multiple()
                            ->options(function ($record) {
                                if (!$record) return [];
                                return $record->clientes()
                                    ->with('persona')
                                    ->get()
                                    ->mapWithKeys(function($cliente) {
                                        $esLider = $cliente->pivot->rol === 'Líder Grupal' ? ' (LÍDER GRUPAL)' : '';
                                        return [$cliente->id => $cliente->persona->nombre . ' ' . $cliente->persona->apellidos . ' (DNI: ' . $cliente->persona->DNI . ')' . $esLider];
                                    });
                            })
                            ->helperText('⚠️ No se puede remover al líder grupal sin antes cambiar el liderazgo')
                            ->visible(fn ($record) => $record !== null && !$record->tienePrestamosActivos())
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $set, $record) {
                                if (!empty($state) && $record) {
                                    // Verificar si se está intentando remover al líder grupal
                                    $lider = $record->clientes()->wherePivot('rol', 'Líder Grupal')->first();
                                    if ($lider && in_array($lider->id, $state)) {
                                        $integrantesRestantes = $record->clientes()->whereNotIn('clientes.id', $state)->count();
                                        if ($integrantesRestantes > 0) {
                                            Notification::make()
                                                ->danger()
                                                ->title('No se puede remover al líder grupal')
                                                ->body('Debe cambiar el líder grupal antes de removerlo')
                                                ->send();
                                            // Remover el líder de la selección
                                            $set('remover_integrantes_form', array_values(array_diff($state, [$lider->id])));
                                        }
                                    }
                                }
                            })
                            ->dehydrated(true),

                        Forms\Components\Fieldset::make('transferir_integrante_form')
                            ->label('Transferir Integrante')
                            ->schema([
                                Forms\Components\Select::make('cliente_transferir')
                                    ->label('Cliente a transferir')
                                    ->options(function ($record) {
                                        if (!$record) return [];
                                        return $record->clientes()
                                            ->with('persona')
                                            ->get()
                                            ->mapWithKeys(function($cliente) {
                                                $esLider = $cliente->pivot->rol === 'Líder Grupal' ? ' (LÍDER GRUPAL)' : '';
                                                return [$cliente->id => $cliente->persona->nombre . ' ' . $cliente->persona->apellidos . ' (DNI: ' . $cliente->persona->DNI . ')' . $esLider];
                                            });
                                    })
                                    ->helperText('⚠️ Si transfiere al líder grupal, debe asignar un nuevo líder')
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, callable $set, $record) {
                                        if ($state && $record) {
                                            $cliente = $record->clientes()->where('clientes.id', $state)->first();
                                            if ($cliente && $cliente->pivot->rol === 'Líder Grupal') {
                                                $integrantesRestantes = $record->clientes()->where('clientes.id', '!=', $state)->count();
                                                if ($integrantesRestantes > 0) {
                                                    Notification::make()
                                                        ->warning()
                                                        ->title('Transfiriendo al líder grupal')
                                                        ->body('El grupo se quedará sin líder. Asegúrese de asignar un nuevo líder.')
                                                        ->send();
                                                }
                                            }
                                        }
                                    })
                                    ->dehydrated(true),
                                Forms\Components\Select::make('grupo_destino_form')
                                    ->label('Grupo destino')
                                    ->options(function ($record) use ($user) {
                                        if (!$record) return [];
                                        
                                        $query = \App\Models\Grupo::where('id', '!=', $record->id)
                                            ->where('estado_grupo', 'Activo');
                                        
                                        // Filtrar por asesor si es necesario
                                        if ($user->hasRole('Asesor')) {
                                            $asesor = \App\Models\Asesor::where('user_id', $user->id)->first();
                                            if ($asesor) {
                                                $query->where('asesor_id', $asesor->id);
                                            }
                                        }
                                        
                                        return $query->get()
                                            ->filter(function($grupo) {
                                                return !$grupo->tienePrestamosActivos();
                                            })
                                            ->mapWithKeys(function($grupo) {
                                                return [$grupo->id => $grupo->nombre_grupo . ' (' . $grupo->clientes()->count() . ' integrantes)'];
                                            });
                                    })
                                    ->helperText('Solo grupos sin préstamos activos')
                                    ->dehydrated(true),
                            ])
                            ->visible(fn ($record) => $record !== null && !$record->tienePrestamosActivos()),
                    ])
                    ->visible(fn ($record) => $record !== null)
                    ->collapsible(),
            ]);
    }


    public static function table(Table $table): Table
    {
        $columns = [
            Tables\Columns\TextColumn::make('nombre_grupo')
                ->searchable(),
            Tables\Columns\TextColumn::make('numero_integrantes_real')
                ->label('N° Integrantes')
                ->getStateUsing(fn($record) => $record->clientes()->count()),
            Tables\Columns\TextColumn::make('fecha_registro')
                ->date()
                ->sortable(),
            Tables\Columns\TextColumn::make('estado_grupo')
                ->searchable()
                ->badge()
                ->color(fn($state) => $state === 'Inactivo' ? 'danger' : ($state === 'Activo' ? 'success' : 'warning')),
            Tables\Columns\TextColumn::make('integrantes_nombres')
                ->label('Integrantes')
                ->limit(50)
                ->tooltip(fn($record) => $record->integrantes_nombres),
            Tables\Columns\TextColumn::make('lider_grupal')
                ->label('Líder Grupal')
                ->getStateUsing(function($record) {
                    $lider = $record->clientes()->wherePivot('rol', 'Líder Grupal')->with('persona')->first();
                    return $lider ? ($lider->persona->nombre . ' ' . $lider->persona->apellidos) : '-';
                }),
            Tables\Columns\TextColumn::make('ex_integrantes')
                ->label('Ex-integrantes')
                ->getStateUsing(function($record) {
                    $count = $record->exIntegrantes()->count();
                    return $count > 0 ? $count . ' ex-integrantes' : '-';
                })
                ->badge()
                ->color(fn($state) => $state === '-' ? 'gray' : 'warning')
                ->tooltip(function($record) {
                    $exIntegrantes = $record->exIntegrantes()->with('persona')->get();
                    if ($exIntegrantes->isEmpty()) {
                        return 'No hay ex-integrantes';
                    }
                    return $exIntegrantes->map(function($cliente) {
                        $fechaSalida = $cliente->pivot->fecha_salida ? 
                            ' (Salió: ' . \Carbon\Carbon::parse($cliente->pivot->fecha_salida)->format('d/m/Y') . ')' : '';
                        return $cliente->persona->nombre . ' ' . $cliente->persona->apellidos . $fechaSalida;
                    })->implode("\n");
                }),
            Tables\Columns\IconColumn::make('tiene_prestamos_activos')
                ->label('Préstamos')
                ->getStateUsing(fn($record) => $record->tienePrestamosActivos())
                ->boolean()
                ->trueIcon('heroicon-o-lock-closed')
                ->falseIcon('heroicon-o-lock-open')
                ->trueColor('danger')
                ->falseColor('success')
                ->tooltip(function($record) {
                    return $record->tienePrestamosActivos() ? 
                        'Grupo con préstamos activos - No se pueden modificar integrantes' : 
                        'Grupo sin préstamos activos - Se pueden modificar integrantes';
                })
        ];

        // Agregar columna de asesor solo para roles administrativos al final
        $user = request()->user();
        if ($user && $user->hasAnyRole(['super_admin', 'Jefe de operaciones', 'Jefe de creditos'])) {
            $columns[] = Tables\Columns\TextColumn::make('asesor.persona.nombre')
                ->label('Asesor')
                ->formatStateUsing(fn ($record) =>
                    $record->asesor ? ($record->asesor->persona->nombre . ' ' . $record->asesor->persona->apellidos) : '-')
                ->sortable()
                ->searchable();
        }

        return $table->columns($columns)
            ->filters([
                Tables\Filters\SelectFilter::make('asesor')
                    ->label('Asesor')
                    ->options(function () {
                        return \App\Models\Asesor::where('estado_asesor', 'Activo')
                            ->with('persona')
                            ->get()
                            ->mapWithKeys(function ($asesor) {
                                return [$asesor->persona->nombre . ' ' . $asesor->persona->apellidos => $asesor->persona->nombre . ' ' . $asesor->persona->apellidos];
                            });
                    })
                    ->query(function (Builder $query, array $data) {
                        if (!empty($data['value'])) {
                            $query->whereHas('asesor.persona', function ($q) use ($data) {
                                $q->whereRaw("CONCAT(nombre, ' ', apellidos) = ?", [$data['value']]);
                            });
                        }
                        return $query;
                    })
                    ->visible(fn () => request()->user() && request()->user()->hasAnyRole(['super_admin', 'Jefe de operaciones', 'Jefe de creditos'])),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->icon('heroicon-o-pencil-square'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->label('Desactivar Seleccionados')
                        ->modalHeading('Desactivar Grupos Seleccionados')
                        ->modalDescription('¿Estás seguro de que quieres desactivar los grupos seleccionados? Los grupos pasarán a estado Inactivo.')
                        ->modalSubmitActionLabel('Sí, desactivar')
                        ->action(function ($records) {
                            $count = 0;
                            $records->each(function ($record) use (&$count) {
                                if ($record->estado_grupo === 'Activo') {
                                    // Desactivar el grupo
                                    $record->update(['estado_grupo' => 'Inactivo']);
                                    
                                    // Actualizar estado_grupo_cliente en la tabla pivot para todos los integrantes
                                    $record->clientes()->updateExistingPivot(
                                        $record->clientes->pluck('id')->toArray(),
                                        ['estado_grupo_cliente' => 'Inactivo']
                                    );
                                    
                                    $count++;
                                }
                            });

                            if ($count > 0) {
                                \Filament\Notifications\Notification::make()
                                    ->success()
                                    ->title('Grupos Desactivados')
                                    ->body("Se han desactivado $count grupos exitosamente. También se actualizó el estado de los integrantes.")
                                    ->send();
                            }
                        }),
                    Tables\Actions\BulkAction::make('cambiar_asesor')
                        ->label('Cambiar Asesor')
                        ->icon('heroicon-o-user')
                        ->visible(fn () => (request()->user() && request()->user()->hasAnyRole(['super_admin', 'Jefe de operaciones'])))
                        ->form([
                            Forms\Components\Select::make('asesor_id')
                                ->label('Nuevo Asesor')
                                ->options(function () {
                                    return \App\Models\Asesor::where('estado_asesor', 'Activo')
                                        ->with('persona')
                                        ->get()
                                        ->mapWithKeys(function ($asesor) {
                                            return [$asesor->id => $asesor->persona->nombre . ' ' . $asesor->persona->apellidos];
                                        });
                                })
                                ->required(),
                        ])
                        ->action(function ($records, $data) {
                            foreach ($records as $grupo) {
                                $grupo->asesor_id = $data['asesor_id'];
                                $grupo->save();
                                // Actualizar asesor_id de todos los clientes activos en el grupo
                                $clientes = $grupo->clientes()->get();
                                foreach ($clientes as $cliente) {
                                    $cliente->asesor_id = $data['asesor_id'];
                                    $cliente->save();
                                }
                            }
                            Notification::make()
                                ->success()
                                ->title('Asesor actualizado')
                                ->body('El asesor de los grupos seleccionados y de sus clientes ha sido cambiado correctamente.')
                                ->send();
                        }),
                ]),
            ]);
    }


    public static function getEloquentQuery(): Builder
    {
        $user = request()->user();


        $query = parent::getEloquentQuery();


        if ($user->hasRole('Asesor')) {
            $asesor = \App\Models\Asesor::where('user_id', $user->id)->first();


            if ($asesor) {
                // Filtrar grupos directamente por asesor_id, no por clientes activos
                // Esto permite ver todos los grupos del asesor, incluso los que no tienen integrantes
                $query->where('asesor_id', $asesor->id);
            }
        }


        return $query;
    }


    public static function getRelations(): array
    {
        return [
            //
        ];
    }


    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGrupos::route('/'),
            'create' => Pages\CreateGrupo::route('/create'),
            'edit' => Pages\EditGrupo::route('/{record}/edit'),
        ];
    }


    public static function mutateFormDataBeforeSave(array $data): array
    {
        $user = request()->user();
        if ($user->hasRole('Asesor')) {
            $asesor = \App\Models\Asesor::where('user_id', $user->id)->first();
            if ($asesor) {
                $data['asesor_id'] = $asesor->id;
                Log::info('Asesor ID asignado:', ['asesor_id' => $asesor->id]);
            } else {
                Log::warning('No se encontró un asesor para el usuario:', ['user_id' => $user->id]);
            }
        } else {
            Log::info('El usuario no tiene el rol de Asesor:', ['roles' => $user->roles]);
        }
        // Aseguramos que todas las rutas devuelvan $data
        return $data;
    }
}
