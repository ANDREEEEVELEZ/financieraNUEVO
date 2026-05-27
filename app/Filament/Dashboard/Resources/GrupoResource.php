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
        $isInactivo = $grupo && $grupo->estado_grupo === 'INACTIVO';
        return $form
            ->schema([
                Forms\Components\Section::make('Datos del Grupo')
                    ->description('Información básica del grupo')
                    ->schema([
                        Forms\Components\Select::make('asesor_id')
                            ->label('Asesor')
                            ->options(function () {
                                return \App\Models\Asesor::where('estado_asesor', 'ACTIVO')
                                    ->with('persona')
                                    ->get()
                                    ->mapWithKeys(function ($asesor) {
                                        return [$asesor->id => $asesor->persona->nombre . ' ' . $asesor->persona->apellidos];
                                    });
                            })
                            ->searchable()
                            ->required()
                            ->live(onBlur: true)  // Optimizado: solo actualiza al salir del campo
                            ->visible(fn() => $user && $user->hasAnyRole(['super_admin', 'Jefe de operaciones']))
                            ->disabled(fn() => $isInactivo),
                        Forms\Components\TextInput::make('nombre_grupo')
                            ->maxLength(255)
                            ->prefixIcon('heroicon-o-tag')
                            ->required()
                            ->disabled(fn() => $isInactivo),
                        Forms\Components\DatePicker::make('fecha_registro')
                            ->required()
                            ->prefixIcon('heroicon-o-calendar')
                            ->default(now()->format('Y-m-d'))
                            ->disabled(fn() => $isInactivo),
                        Forms\Components\Select::make('calificacion_grupo')
                            ->prefixIcon('heroicon-o-star')
                            ->options([
                                '1' => '1',
                                '2' => '2',
                                '3' => '3',
                                '4' => '4',
                                '5' => '5',
                                '6' => '6',
                                '7' => '7',
                                '8' => '8',
                                '9' => '9',
                                '10' => '10',
                            ])
                            ->native(false)
                            ->rules(['numeric', 'between:1,10'])
                            ->disabled(fn() => $isInactivo),
                        Forms\Components\TextInput::make('estado_grupo')
                            ->prefixIcon('heroicon-o-check-circle')
                            ->default('ACTIVO')
                            ->maxLength(255)
                            ->disabled()
                            ->dehydrated(),
                    ]),
                Forms\Components\Section::make('Integrantes del Grupo')
                    ->description('Selecciona los integrantes del grupo de forma dinámica')
                    ->icon('heroicon-o-user-group')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Card::make()
                                    ->schema([
                                        Forms\Components\Select::make('clientes')
                                            ->label('Seleccionar Integrantes')
                                            ->prefixIcon('heroicon-o-user-plus')
                                            ->multiple()
                                            ->options(function (callable $get) use ($user) {
                                                // Obtener el grupo actual si estamos editando
                                                $record = request()->route('record');
                                                $grupoActual = $record ? \App\Models\Grupo::find($record) : null;

                                                // Si es asesor, mostrar solo sus clientes
                                                if ($user->hasRole('Asesor')) {
                                                    $asesor = \App\Models\Asesor::where('user_id', $user->id)->first();
                                                    if ($asesor) {
                                                        return Cliente::where('asesor_id', $asesor->id)
                                                            ->with(['persona'])
                                                            ->whereDoesntHave('grupos', function ($query) use ($grupoActual) {
                                                                $query->where('estado_grupo', 'Activo')
                                                                    ->whereNull('grupo_cliente.fecha_salida');
                                                                // Si estamos editando, excluir el grupo actual del filtro
                                                                if ($grupoActual) {
                                                                    $query->where('grupos.id', '!=', $grupoActual->id);
                                                                }
                                                            })
                                                            ->join('personas', 'clientes.persona_id', '=', 'personas.id')
                                                            ->orderBy('personas.nombre')
                                                            ->orderBy('personas.apellidos')
                                                            ->select('clientes.*')
                                                            ->get()
                                                            ->mapWithKeys(function ($cliente) {
                                                                return [$cliente->id => " {$cliente->persona->nombre} {$cliente->persona->apellidos} (DNI: {$cliente->persona->DNI})"];
                                                            });
                                                    }
                                                }
                                                // Si es super_admin o jefe de operaciones, filtrar por asesor seleccionado
                                                if ($user->hasAnyRole(['super_admin', 'Jefe de operaciones', 'Jefe de creditos'])) {
                                                    $asesorId = $get('asesor_id');
                                                    if ($asesorId) {
                                                        return Cliente::where('asesor_id', $asesorId)
                                                            ->with(['persona'])
                                                            ->whereDoesntHave('grupos', function ($query) use ($grupoActual) {
                                                                $query->where('estado_grupo', 'Activo')
                                                                    ->whereNull('grupo_cliente.fecha_salida');
                                                                // Si estamos editando, excluir el grupo actual del filtro
                                                                if ($grupoActual) {
                                                                    $query->where('grupos.id', '!=', $grupoActual->id);
                                                                }
                                                            })
                                                            ->join('personas', 'clientes.persona_id', '=', 'personas.id')
                                                            ->orderBy('personas.nombre')
                                                            ->orderBy('personas.apellidos')
                                                            ->select('clientes.*')
                                                            ->get()
                                                            ->mapWithKeys(function ($cliente) {
                                                                return [$cliente->id => " {$cliente->persona->nombre} {$cliente->persona->apellidos} (DNI: {$cliente->persona->DNI})"];
                                                            });
                                                    }
                                                    return [];
                                                }
                                                return [];
                                            })
                                            ->preload()
                                            ->required()
                                            ->live(onBlur: true)  // Optimizado: solo actualiza al confirmar selección
                                            ->afterStateUpdated(function ($state, callable $get, callable $set) {
                                                // Limitar automáticamente a máximo 6 integrantes
                                                // Validación eliminada - Ya no hay límite de integrantes
                                    
                                                // Actualizar el contador
                                                $contador = is_array($state) ? count($state) : 0;
                                                $set('numero_integrantes', $contador);
                                            })
                                            ->disabled(fn() => $isInactivo),
                                        Forms\Components\TextInput::make('numero_integrantes')
                                            ->label('Numero de Integrantes')
                                            ->prefixIcon('heroicon-o-hashtag')
                                            ->disabled()
                                            ->dehydrated(false)
                                            ->live()  // Optimizado: removido duplicate reactive
                                            ->afterStateHydrated(function ($component, $state, $record) {
                                                if ($record) {
                                                    $component->state($record->clientes()->count());
                                                }
                                            }),
                                    ])
                                    ->columnSpan(1),
                                Forms\Components\Card::make()
                                    ->schema([
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
                                                    ->mapWithKeys(function ($cliente) {
                                                        return [$cliente->id => '👑 ' . $cliente->persona->nombre . ' ' . $cliente->persona->apellidos . ' (DNI: ' . $cliente->persona->DNI . ')'];
                                                    })
                                                    ->toArray();
                                            })
                                            ->required()
                                            ->searchable()
                                            ->visible(fn(callable $get) => !empty($get('clientes')))
                                            ->disabled(fn() => $isInactivo),
                                        Forms\Components\Placeholder::make('info_lider')
                                            ->label('Información del Líder')
                                            ->content('👑 El líder grupal será el responsable principal del grupo y aparecerá destacado en las listas.')
                                            ->extraAttributes(['class' => 'text-amber-600 font-semibold text-sm']),
                                    ])
                                    ->columnSpan(1),
                            ]),
                        Forms\Components\Placeholder::make('info_integrantes')
                            ->label('📋 Instrucciones Importantes')
                            ->content('👑 Debes elegir un líder grupal entre los integrantes seleccionados')
                            ->extraAttributes(['class' => 'text-blue-600 font-medium', 'style' => 'white-space: pre-line;']),
                    ])
                    ->collapsible()
                    ->collapsed(false)
                    ->persistCollapsed(),  // Optimizado: recuerda estado del usuario
                Forms\Components\Section::make('Restricciones y Ex-integrantes')
                    ->schema([
                        Forms\Components\Placeholder::make('restriccion_prestamos')
                            ->label('Información importante')
                            ->content(function ($record) {
                                if ($record && $record->tienePrestamosActivos()) {
                                    return '⚠️ Este grupo tiene préstamos activos. No se pueden realizar cambios en los integrantes usando las acciones de la tabla.';
                                }
                                return '✅ Este grupo no tiene préstamos activos. Se pueden realizar cambios en los integrantes usando las acciones de la tabla.';
                            })
                            ->visible(fn($record) => $record !== null)
                            ->extraAttributes(['class' => 'font-semibold']),
                        Forms\Components\Placeholder::make('ex_integrantes_info')
                            ->label('Ex-integrantes')
                            ->content(function ($record) {
                                if (!$record)
                                    return 'Ninguno';
                                $exIntegrantes = $record->exIntegrantes;
                                if ($exIntegrantes->isEmpty()) {
                                    return 'Ninguno';
                                }
                                return $exIntegrantes->map(function ($cliente) {
                                    $fechaSalida = $cliente->pivot->fecha_salida ?
                                        ' (Salió: ' . \Carbon\Carbon::parse($cliente->pivot->fecha_salida)->format('d/m/Y') . ')' : '';
                                    return '• ' . $cliente->persona->nombre . ' ' . $cliente->persona->apellidos . $fechaSalida;
                                })->implode("\n");
                            })
                            ->visible(fn($record) => $record !== null)
                            ->extraAttributes(['style' => 'white-space: pre-line;']),
                    ]),
            ]);
    }


    public static function table(Table $table): Table
    {
        $columns = [
            Tables\Columns\TextColumn::make('nombre_grupo')
                ->searchable(),
            Tables\Columns\TextColumn::make('numero_integrantes_real')
                ->label('N° Integrantes')
                ->getStateUsing(fn($record) => $record->clientes->count()),
            Tables\Columns\TextColumn::make('fecha_registro')
                ->date()
                ->sortable(),
            Tables\Columns\TextColumn::make('estado_grupo')
                ->searchable()
                ->badge()
                ->color(fn($state) => $state === 'INACTIVO' ? 'danger' : ($state === 'ACTIVO' ? 'success' : 'warning')),
            Tables\Columns\TextColumn::make('integrantes_nombres')
                ->label('Integrantes')
                ->limit(50)
                ->tooltip(fn($record) => $record->integrantes_nombres),
            Tables\Columns\TextColumn::make('lider_grupal')
                ->label('Líder Grupal')
                ->getStateUsing(function ($record) {
                    $lider = $record->clientes->first(function ($cliente) {
                        return $cliente->pivot && $cliente->pivot->rol === 'Líder Grupal';
                    });
                    return $lider ? ($lider->persona->nombre . ' ' . $lider->persona->apellidos) : '-';
                }),
            Tables\Columns\TextColumn::make('ex_integrantes')
                ->label('Ex-integrantes')
                ->getStateUsing(function ($record) {
                    $count = $record->exIntegrantes->count();
                    return $count > 0 ? $count . ' ex-integrantes' : '-';
                })
                ->badge()
                ->color(fn($state) => $state === '-' ? 'gray' : 'warning')
                ->tooltip(function ($record) {
                    $exIntegrantes = $record->exIntegrantes;
                    if ($exIntegrantes->isEmpty()) {
                        return 'No hay ex-integrantes';
                    }
                    return $exIntegrantes->map(function ($cliente) {
                        $fechaSalida = $cliente->pivot->fecha_salida ?
                            ' (Salió: ' . \Carbon\Carbon::parse($cliente->pivot->fecha_salida)->format('d/m/Y') . ')' : '';
                        return ($cliente->persona->nombre ?? '') . ' ' . ($cliente->persona->apellidos ?? '') . $fechaSalida;
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
                ->tooltip(function ($record) {
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
                ->formatStateUsing(fn($record) =>
                    $record->asesor ? ($record->asesor->persona->nombre . ' ' . $record->asesor->persona->apellidos) : '-')
                ->sortable()
                ->searchable();
        }

        return $table->columns($columns)
            ->filters([
                // Filtro por Estado (visible para todos los roles)
                Tables\Filters\SelectFilter::make('estado_grupo')
                    ->label('Estado del Grupo')
                    ->options([
                        'ACTIVO' => 'ACTIVO',
                        'INACTIVO' => 'INACTIVO',
                    ])
                    ->default('ACTIVO'),

                // Filtro por Asesor (visible solo para roles administrativos, NO para Asesor)
                Tables\Filters\SelectFilter::make('asesor')
                    ->label('Asesor')
                    ->options(function () {
                        return \App\Models\Asesor::with('persona')
                            ->get()
                            ->mapWithKeys(function ($asesor) {
                                $estadoBadge = $asesor->estado_asesor === 'Activo' ? '' : ' (Inactivo)';
                                return [$asesor->id => $asesor->persona->nombre . ' ' . $asesor->persona->apellidos . $estadoBadge];
                            });
                    })
                    ->query(function (Builder $query, array $data) {
                        if (!empty($data['value'])) {
                            $query->where('asesor_id', $data['value']);
                        }
                        return $query;
                    })
                    ->visible(fn() => request()->user() && !request()->user()->hasRole('Asesor')),
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
                            $inactivos = 0;
                            $records->each(function ($record) use (&$count, &$inactivos) {
                                if ($record->estado_grupo === 'ACTIVO') {
                                    // Desactivar el grupo
                                    $record->update(['estado_grupo' => 'INACTIVO']);

                                    // Actualizar estado_grupo_cliente en la tabla pivot para todos los integrantes
                                    $record->clientes()->updateExistingPivot(
                                        $record->clientes->pluck('id')->toArray(),
                                        ['estado_grupo_cliente' => 'INACTIVO']
                                    );

                                    $count++;
                                } else {
                                    $inactivos++;
                                }
                            });

                            if ($count > 0) {
                                \Filament\Notifications\Notification::make()
                                    ->success()
                                    ->title('Grupos Desactivados')
                                    ->body("Se han desactivado $count grupos exitosamente. También se actualizó el estado de los integrantes." . ($inactivos > 0 ? " $inactivos ya estaban inactivos." : ""))
                                    ->send();
                            } elseif ($inactivos > 0) {
                                \Filament\Notifications\Notification::make()
                                    ->warning()
                                    ->title('Sin cambios')
                                    ->body("Los grupos seleccionados ya están inactivos.")
                                    ->send();
                            }
                        }),
                    Tables\Actions\BulkAction::make('cambiar_asesor')
                        ->label('Cambiar Asesor')
                        ->icon('heroicon-o-user')
                        ->visible(fn() => (request()->user() && request()->user()->hasAnyRole(['super_admin', 'Jefe de operaciones'])))
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

        // Eager loading de relaciones para evitar N+1 queries
        $query = parent::getEloquentQuery()
            ->with([
                'asesor:id,persona_id,user_id,estado_asesor',
                'asesor.persona:id,nombre,apellidos',
                'clientes:id,persona_id,ciclo,estado_cliente',
                'clientes.persona:id,nombre,apellidos,DNI',
                'exIntegrantes:id,persona_id,ciclo,estado_cliente',
                'exIntegrantes.persona:id,nombre,apellidos,DNI',
                'prestamos:id,grupo_id,estado',
            ])
            ->withCount('clientes');  // Para número de integrantes

        if ($user->hasRole('Asesor')) {
            // Usar CacheService para obtener el asesor (evita query repetida)
            $asesor = \App\Infrastructure\Cache\CacheService::getAsesorByUserId($user->id);

            if ($asesor) {
                $query->where('asesor_id', $asesor->id);
            }
        }

        return $query->orderBy('created_at', 'desc');
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
