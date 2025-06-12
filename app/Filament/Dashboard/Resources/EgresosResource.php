<?php

namespace App\Filament\Dashboard\Resources;

use App\Filament\Dashboard\Resources\EgresosResource\Pages;
use App\Models\Egreso;
use App\Models\Categoria;
use App\Models\Subcategoria;
use App\Models\Prestamo;
use App\Models\Grupo;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class EgresosResource extends Resource
{
    protected static ?string $model = Egreso::class;
    protected static ?string $navigationGroup = 'Movimientos financieros';
    protected static ?string $navigationIcon = 'heroicon-o-minus-circle';
    protected static ?string $modelLabel = 'Egreso';
    protected static ?string $pluralModelLabel = 'Egresos';

    public static function form(Form $form): Form
    {
        return $form->schema(function (Forms\Get $get, ?Egreso $record) {
            $isEditing = filled($record);

            return [
                // Campo oculto para detectar edición
                Forms\Components\Hidden::make('id'),

                // Tipo de Egreso - Solo gasto para creación manual
                Forms\Components\TextInput::make('tipo_egreso')
                    ->label('Tipo de Egreso')
                    ->default('gasto')
                    ->disabled() // Siempre deshabilitado
                    ->dehydrated(true) // Asegura que sí se guarde en la BD
                    ->required(),

                // Fecha
                Forms\Components\DatePicker::make('fecha')
                    ->label('Fecha')
                    ->default(now())
                    ->required()
                    ->disabled($isEditing),

                // Campos específicos para GASTO - Solo visibles para gastos
                Forms\Components\Select::make('categoria_id')
                    ->label('Categoría')
                    ->relationship('categoria', 'nombre_categoria')
                    ->searchable()
                    ->preload()
                    ->required(fn (Get $get) => $get('tipo_egreso') === 'gasto' || (!$isEditing && $get('tipo_egreso') === 'gasto'))
                    ->visible(fn (Get $get) => $get('tipo_egreso') === 'gasto' || (!$isEditing && $get('tipo_egreso') === 'gasto'))
                    ->live()
                    ->disabled($isEditing)
                    ->afterStateUpdated(function (Set $set, Get $get) {
                        $set('subcategoria_id', null);
                        self::updateDescripcion($set, $get);
                    })
                    ->createOptionForm([
                        Forms\Components\TextInput::make('nombre_categoria')
                            ->label('Nombre de la Categoría')
                            ->required()
                            ->maxLength(255),
                    ])
                    ->createOptionAction(fn ($action) =>
                        $action->modalHeading('Crear Nueva Categoría')->modalSubmitActionLabel('Crear')->modalWidth('lg')
                    ),

                Forms\Components\Select::make('subcategoria_id')
                    ->label('Subcategoría')
                    ->options(fn (Get $get): array =>
                        Subcategoria::query()
                            ->where('categoria_id', $get('categoria_id'))
                            ->pluck('nombre_subcategoria', 'id')
                            ->toArray()
                    )
                    ->searchable()
                    ->required(fn (Get $get) => $get('tipo_egreso') === 'gasto' || (!$isEditing && $get('tipo_egreso') === 'gasto'))
                    ->visible(fn (Get $get) => $get('tipo_egreso') === 'gasto' || (!$isEditing && $get('tipo_egreso') === 'gasto'))
                    ->live()
                    ->disabled($isEditing)
                    ->afterStateUpdated(function (Set $set, Get $get) {
                        self::updateDescripcion($set, $get);
                    })
                    ->createOptionForm([
                        Forms\Components\Hidden::make('categoria_id')
                            ->default(fn (Get $get) => $get('categoria_id')),
                        Forms\Components\TextInput::make('nombre_subcategoria')
                            ->label('Nombre de la Subcategoría')
                            ->required()
                            ->maxLength(255),
                    ])
                    ->createOptionUsing(function (array $data, Get $get) {
                        $data['categoria_id'] = $get('categoria_id');
                        return Subcategoria::create($data)->id;
                    })
                    ->createOptionAction(fn ($action) =>
                        $action->modalHeading('Crear Nueva Subcategoría')->modalSubmitActionLabel('Crear')->modalWidth('lg')
                    ),

                // Campo para mostrar grupo en desembolsos
        /*Forms\Components\TextInput::make('grupo_nombre')
            ->label('Grupo')
            ->visible(fn (Get $get) => $get('tipo_egreso') === 'desembolso')
            ->disabled()
            ->dehydrated(false)
            ->live()
            ->afterStateUpdated(function (Set $set, Get $get) {
                $grupoId = $get('grupo_id');
                if ($grupoId) {
                    $grupo = Grupo::find($grupoId);
                    $set('grupo_nombre', $grupo ? $grupo->nombre_grupo : 'Sin grupo');
                }
            })
            ->afterStateHydrated(function (Forms\Components\TextInput $component, $state, $record) {
                if ($record && $record->grupo_id) {
                    $grupo = Grupo::find($record->grupo_id);
                    $component->state($grupo ? $grupo->nombre_grupo : 'Sin grupo');
                }
            }),
                    */

                // Campo oculto para grupo_id (en caso de que existan desembolsos creados automáticamente)
                Forms\Components\Hidden::make('grupo_id'),

                // Monto
                Forms\Components\TextInput::make('monto')
                    ->label('Monto')
                    ->numeric()
                    ->prefix('S/.')
                    ->step(0.01)
                    ->required()
                    ->disabled($isEditing)
                    ->dehydrated(true),

                // Descripción
                Forms\Components\Textarea::make('descripcion')
                    ->label('Descripción')
                    ->rows(3)
                    ->required()
                    ->disabled($isEditing)
                    ->placeholder('Se generará automáticamente como: Categoría + Subcategoría (o puedes editarlo)')
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (Set $set, Get $get, $state) {
                        if (empty(trim($state))) {
                            self::updateDescripcion($set, $get);
                        }
                    }),
            ];
        });
    }

    /**
     * Método auxiliar para actualizar la descripción según el tipo de egreso
     */
    private static function updateDescripcion(Set $set, Get $get): void
    {
        $tipoEgreso = $get('tipo_egreso');
        $descripcionActual = trim($get('descripcion') ?? '');

        // Solo actualizar si la descripción está vacía
        if (empty($descripcionActual)) {
            if ($tipoEgreso === 'gasto') {
                self::updateDescripcionGasto($set, $get);
            } elseif ($tipoEgreso === 'desembolso') {
                self::updateDescripcionDesembolso($set, $get);
            }
        }
    }

    /**
     * Método auxiliar para actualizar la descripción de gastos
     */
    private static function updateDescripcionGasto(Set $set, Get $get): void
    {
        $categoriaId = $get('categoria_id');
        $subcategoriaId = $get('subcategoria_id');

        if ($categoriaId && $subcategoriaId) {
            $categoria = Categoria::find($categoriaId);
            $subcategoria = Subcategoria::find($subcategoriaId);

            if ($categoria && $subcategoria) {
                $descripcionAutomatica = $categoria->nombre_categoria . ' de ' . $subcategoria->nombre_subcategoria;
                $set('descripcion', $descripcionAutomatica);
            }
        }
    }

    /**
     * Método auxiliar para actualizar la descripción de desembolsos
     */
    private static function updateDescripcionDesembolso(Set $set, Get $get): void
    {
        $grupoId = $get('grupo_id');

        if ($grupoId) {
            $grupo = Grupo::find($grupoId);
            if ($grupo) {
                $descripcionAutomatica = "Desembolso para {$grupo->nombre_grupo}";
                $set('descripcion', $descripcionAutomatica);
            }
        }
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('fecha')->label('Fecha')->date('d/m/Y')->sortable(),
                Tables\Columns\BadgeColumn::make('tipo_egreso')->label('Tipo')->colors([
                    'danger' => 'gasto',
                    'warning' => 'desembolso',
                ])->formatStateUsing(fn (string $state): string => $state === 'gasto' ? 'Gasto' : 'Desembolso'),
                Tables\Columns\TextColumn::make('descripcion')->label('Descripción')->limit(50)->tooltip(fn ($column) =>
                    strlen($column->getState()) > 50 ? $column->getState() : null),
              /*  Tables\Columns\TextColumn::make('categoria.nombre_categoria')
                    ->label('Categoría')
                    ->formatStateUsing(fn ($state, $record) =>
                        $record->tipo_egreso === 'desembolso' ? 'N/A' : ($state ?? 'N/A')
                    ),
                Tables\Columns\TextColumn::make('subcategoria.nombre_subcategoria')
                    ->label('Subcategoría')
                    ->formatStateUsing(fn ($state, $record) =>
                        $record->tipo_egreso === 'desembolso' ? 'N/A' : ($state ?? 'N/A')
                    ),
                */
                Tables\Columns\TextColumn::make('grupo_nombre')
                    ->label('Grupo')
                    ->getStateUsing(function ($record) {
                        if ($record->tipo_egreso === 'gasto') {
                            return 'N/A';
                        }
                        // Para desembolsos, obtener el nombre del grupo
                        if ($record->grupo_id) {
                            $grupo = Grupo::find($record->grupo_id);
                            return $grupo ? $grupo->nombre_grupo : 'Sin grupo';
                        }
                        return 'Sin grupo';
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('monto')->label('Monto')->money('PEN')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('tipo_egreso')->label('Tipo de Egreso')->options([
                    'gasto' => 'Gasto',
                    'desembolso' => 'Desembolso',
                ]),
                Tables\Filters\SelectFilter::make('categoria_id')
                    ->label('Categoría')
                    ->relationship('categoria', 'nombre_categoria')
                    ->visible(fn () => request()->has('tableFilters.tipo_egreso.value') && request()->get('tableFilters.tipo_egreso.value') === 'gasto'),
                Tables\Filters\SelectFilter::make('grupo_id')
                    ->label('Grupo')
                    ->options(Grupo::all()->pluck('nombre_grupo', 'id'))
                    ->visible(fn () => request()->has('tableFilters.tipo_egreso.value') && request()->get('tableFilters.tipo_egreso.value') === 'desembolso'),
                Tables\Filters\Filter::make('fecha')->form([
                    Forms\Components\DatePicker::make('fecha_desde')->label('Desde'),
                    Forms\Components\DatePicker::make('fecha_hasta')->label('Hasta'),
                ])->query(function (Builder $query, array $data): Builder {
                    return $query
                        ->when($data['fecha_desde'], fn (Builder $query, $date): Builder => $query->whereDate('fecha', '>=', $date))
                        ->when($data['fecha_hasta'], fn (Builder $query, $date): Builder => $query->whereDate('fecha', '<=', $date));
                }),
            ])
           /* ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])*/
            ->defaultSort('fecha', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function shouldRegisterNavigation(): bool
    {
        return Auth::check() && Auth::user()->hasRole(['super_admin', 'Jefe de operaciones']);
    }

    public static function canAccess(): bool
    {
        return Auth::check() && Auth::user()->hasRole(['super_admin', 'Jefe de operaciones']);
    }


    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEgresos::route('/'),
            'create' => Pages\CreateEgresos::route('/create'),
            'edit' => Pages\EditEgresos::route('/{record}/edit'),
        ];
    }
}
