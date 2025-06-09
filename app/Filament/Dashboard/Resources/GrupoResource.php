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


    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('nombre_grupo')
                    ->maxLength(255)
                    ->required(),
                Forms\Components\DatePicker::make('fecha_registro')
                    ->required(),
                Forms\Components\TextInput::make('calificacion_grupo')
                    ->maxLength(255),
                Forms\Components\TextInput::make('estado_grupo')
                    ->default('Activo')
                    ->maxLength(255)
                    ->disabled()
                    ->dehydrated(),
Forms\Components\Select::make('clientes')
    ->label('Integrantes')
    ->multiple()
    ->relationship('clientes', 'id')
    ->options(function () {
        $user = request()->user();

        // Si es asesor, mostrar solo sus clientes
        if ($user->hasRole('Asesor')) {
            $asesor = \App\Models\Asesor::where('user_id', $user->id)->first();

            if ($asesor) {
                return Cliente::where('asesor_id', $asesor->id)
                    ->with(['persona', 'grupos' => function ($query) {
                        $query->where('estado_grupo', 'Activo');
                    }])
                    ->join('personas', 'clientes.persona_id', '=', 'personas.id')
                    ->orderBy('personas.nombre') // Ordenar por nombre
                    ->orderBy('personas.apellidos') // Luego por apellidos
                    ->select('clientes.*') // Seleccionar solo las columnas de clientes
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

        // Si es admin, jefe de operaciones o jefe de crédito, mostrar todos
        if ($user->hasAnyRole(['super_admin', 'Jefe de operaciones', 'Jefe de creditos'])) {
            return Cliente::with(['persona', 'grupos' => function ($query) {
                    $query->where('estado_grupo', 'Activo');
                }])
                ->join('personas', 'clientes.persona_id', '=', 'personas.id')
                ->orderBy('personas.nombre') // Ordenar por nombre
                ->orderBy('personas.apellidos') // Luego por apellidos
                ->select('clientes.*') // Seleccionar solo las columnas de clientes
                ->get()
                ->mapWithKeys(function($cliente) {
                    if ($cliente->tieneGrupoActivo()) {
                        $grupoActivo = $cliente->grupoActivo;
                        return [$cliente->id => "{$cliente->persona->nombre} {$cliente->persona->apellidos} (DNI: {$cliente->persona->DNI}) - Ya pertenece al grupo: {$grupoActivo->nombre_grupo}"];
                    }
                    return [$cliente->id => "{$cliente->persona->nombre} {$cliente->persona->apellidos} (DNI: {$cliente->persona->DNI})"];
                });
        }

        // Si no aplica, retornar vacío
        return [];
})


                    ->searchable()
                    ->preload()
                    ->required()
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
        if (auth()->user()->hasAnyRole(['super_admin', 'Jefe de operaciones', 'Jefe de creditos'])) {
            $columns[] = Tables\Columns\TextColumn::make('asesor.persona.nombre')
                ->label('Asesor')
                ->formatStateUsing(fn ($record) => 
                    $record->asesor ? ($record->asesor->persona->nombre . ' ' . $record->asesor->persona->apellidos) : '-'
                )
                ->sortable()
                ->searchable();
        }

        return $table->columns($columns)
            ->filters([])
            ->actions([
                Tables\Actions\EditAction::make(),
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
