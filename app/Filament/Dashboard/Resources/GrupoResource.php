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


class GrupoResource extends Resource
{
    protected static ?string $model = Grupo::class;


protected static ?string $navigationIcon = 'heroicon-o-user-group';



    public static function form(Form $form): Form
    {
        $user = request()->user();
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
                    ->visible(fn () => $user && $user->hasAnyRole(['super_admin', 'Jefe de operaciones'])),
                Forms\Components\TextInput::make('nombre_grupo')
                    ->maxLength(255)
                    ->prefixIcon('heroicon-o-tag')
                    ->required(),
                Forms\Components\DatePicker::make('fecha_registro')
                    ->required()
                    ->prefixIcon('heroicon-o-calendar'),
                Forms\Components\TextInput::make('calificacion_grupo')
                    ->prefixIcon('heroicon-o-star')
                    ->maxLength(255),
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
                    ->relationship('clientes', 'id')
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
                            $clientesConGrupo = Cliente::whereIn('clientes.id', $state)
                                ->get()
                                ->filter(function ($cliente) {
                                    return $cliente->tieneGrupoActivo();
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
                                // Remover los clientes que ya tienen grupo ACTIVO
                                $set('clientes', array_values(array_diff($state, $clientesConGrupo->pluck('id')->toArray())));
                            }
                        }
                    }),
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
                    ->visible(fn(callable $get) => !empty($get('clientes'))),
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
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->icon('heroicon-o-pencil-square'),
                Tables\Actions\Action::make('imprimir_contratos')
                    ->label('Imprimir Contratos')
                    ->icon('heroicon-o-printer')
                    ->color('success')
                    ->url(fn($record) => $record ? route('contratos.grupo.imprimir', $record->id) : '#')
                    ->openUrlInNewTab()
                    ->visible(fn($record) => $record !== null),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
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
                $query->whereHas('clientes', function ($subQuery) use ($asesor) {
                    $subQuery->whereHas('asesor', function ($asesorQuery) use ($asesor) {
                        $asesorQuery->where('id', $asesor->id);
                    });
                });
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
