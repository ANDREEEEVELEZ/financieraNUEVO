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

class EgresosResource extends Resource
{
    protected static ?string $model = Egreso::class;
    protected static ?string $navigationGroup = 'Movimientos financieros';
    protected static ?string $navigationIcon = 'heroicon-o-minus-circle';
    protected static ?string $modelLabel = 'Egreso';
    protected static ?string $pluralModelLabel = 'Egresos';

  public static function form(Form $form): Form
{
    return $form
        ->schema([
            // Campo oculto para detectar edición
            Forms\Components\Hidden::make('id'),

            // Tipo de Egreso
            Forms\Components\Select::make('tipo_egreso')
                ->label('Tipo de Egreso')
                ->options([
                    'gasto' => 'Gasto',
                    'desembolso' => 'Desembolso'
                ])
                ->required()
                ->default('gasto')
                ->live()
                ->disabled(fn (Get $get) => (bool)$get('id'))
                ->afterStateUpdated(function (Set $set, $state) {
                    $set('categoria_id', null);
                    $set('subcategoria_id', null);
                    $set('prestamo_id', null);
                    $set('monto', null);
                    $set('descripcion', null);
                    if ($state === 'desembolso') {
                        $set('fecha', now());
                    }
                }),

            // Fecha
            Forms\Components\DatePicker::make('fecha')
                ->label('Fecha')
                ->default(now())
                ->required()
                ->disabled(fn (Get $get) => $get('tipo_egreso') === 'desembolso' || (bool)$get('id')),

            // Campos para GASTO MANUAL
            Forms\Components\Select::make('categoria_id')
                ->label('Categoría')
                ->relationship('categoria', 'nombre_categoria')
                ->searchable()
                ->preload()
                ->required()
                ->live()
                ->disabled(fn (Get $get) => (bool)$get('id'))
                ->afterStateUpdated(function (Set $set, Get $get) {
                    $set('subcategoria_id', null);
                    self::updateDescripcionGasto($set, $get);
                })
                ->createOptionForm([
                    Forms\Components\TextInput::make('nombre_categoria')
                        ->label('Nombre de la Categoría')
                        ->required()
                        ->maxLength(255),
                ])
                ->createOptionAction(fn ($action) =>
                    $action->modalHeading('Crear Nueva Categoría')->modalSubmitActionLabel('Crear')->modalWidth('lg')
                )
                ->visible(fn (Get $get): bool => $get('tipo_egreso') === 'gasto'),

            Forms\Components\Select::make('subcategoria_id')
                ->label('Subcategoría')
                ->options(fn (Get $get): array =>
                    Subcategoria::query()
                        ->where('categoria_id', $get('categoria_id'))
                        ->pluck('nombre_subcategoria', 'id')
                        ->toArray()
                )
                ->searchable()
                ->required()
                ->live()
                ->disabled(fn (Get $get) => (bool)$get('id'))
                ->afterStateUpdated(function (Set $set, Get $get) {
                    self::updateDescripcionGasto($set, $get);
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
                )
                ->visible(fn (Get $get): bool => $get('tipo_egreso') === 'gasto'),

            Forms\Components\TextInput::make('monto')
                ->label(fn (Get $get): string =>
                    $get('tipo_egreso') === 'desembolso' ? 'Monto Prestado' : 'Monto'
                )
                ->numeric()
                ->prefix('S/.')
                ->step(0.01)
                ->required()
                ->disabled(fn (Get $get) => $get('tipo_egreso') === 'desembolso' || (bool)$get('id'))
                ->dehydrated(true)
                ->helperText(fn (Get $get): ?string =>
                    $get('tipo_egreso') === 'desembolso'
                        ? 'Este monto se obtiene automáticamente del préstamo seleccionado (monto prestado, no monto a devolver)'
                        : null
                ),

            Forms\Components\Textarea::make('descripcion')
                ->label('Descripción')
                ->rows(3)
                ->required()
                ->disabled(fn (Get $get) => (bool)$get('id'))
                ->placeholder(fn (Get $get): string =>
                    $get('tipo_egreso') === 'desembolso'
                        ? 'Se completará automáticamente al seleccionar el préstamo (puedes editarlo)'
                        : 'Se generará automáticamente como: Categoría + Subcategoría (o puedes editarlo)'
                )
                ->live(onBlur: true)
                ->afterStateUpdated(function (Set $set, Get $get, $state) {
                    if (empty(trim($state))) {
                        if ($get('tipo_egreso') === 'gasto') {
                            self::updateDescripcionGasto($set, $get);
                        } elseif ($get('tipo_egreso') === 'desembolso' && $get('prestamo_id')) {
                            $prestamo = Prestamo::with('grupo')->find($get('prestamo_id'));
                            if ($prestamo) {
                                $grupoNombre = $prestamo->grupo->nombre_grupo ?? 'Sin grupo';
                                $descripcion = "Desembolso {$grupoNombre}";
                                $set('descripcion', $descripcion);
                            }
                        }
                    }
                }),

            // Campos para DESEMBOLSO
            Forms\Components\Select::make('prestamo_id')
                ->label('Seleccionar Préstamo')
                ->options(function (Get $get): array {
                    $query = Prestamo::with('grupo');

                    $currentPrestamoId = $get('prestamo_id');
                    if ($currentPrestamoId) {
                        $query->where(function ($q) use ($currentPrestamoId) {
                            $q->whereNotIn('id', function ($subQuery) use ($currentPrestamoId) {
                                $subQuery->select('prestamo_id')
                                    ->from('egresos')
                                    ->where('tipo_egreso', 'desembolso')
                                    ->where('prestamo_id', '!=', $currentPrestamoId)
                                    ->whereNotNull('prestamo_id');
                            })->orWhere('id', $currentPrestamoId);
                        });
                    } else {
                        $query->whereNotIn('id', function ($subQuery) {
                            $subQuery->select('prestamo_id')
                                ->from('egresos')
                                ->where('tipo_egreso', 'desembolso')
                                ->whereNotNull('prestamo_id');
                        });
                    }

                    return $query->get()
                        ->mapWithKeys(function ($prestamo) {
                            $grupoNombre = $prestamo->grupo->nombre_grupo ?? 'Sin grupo';
                            return [$prestamo->id => $grupoNombre];
                        })
                        ->toArray();
                })
                ->searchable()
                ->required()
                ->live()
                ->disabled(fn (Get $get) => (bool)$get('id'))
                ->afterStateUpdated(function (Set $set, $state) {
                    if ($state) {
                        $prestamo = Prestamo::with('grupo')->find($state);
                        if ($prestamo) {
                            $set('monto', $prestamo->monto_prestado_total);
                            $set('fecha', $prestamo->fecha_prestamo);
                            $grupoNombre = $prestamo->grupo->nombre_grupo ?? 'Sin grupo';
                            $descripcion = "Desembolso {$grupoNombre}";
                            $set('descripcion', $descripcion);
                        }
                    } else {
                        $set('monto', null);
                        $set('descripcion', null);
                    }
                })
                ->helperText('Solo se muestran préstamos que aún no tienen desembolso registrado')
                ->visible(fn (Get $get): bool => $get('tipo_egreso') === 'desembolso'),
        ]);
}

/**
 * Método auxiliar para actualizar la descripción de gastos
 */
private static function updateDescripcionGasto(Set $set, Get $get): void
{
    $categoriaId = $get('categoria_id');
    $subcategoriaId = $get('subcategoria_id');
    $descripcionActual = trim($get('descripcion') ?? '');

    if (empty($descripcionActual) && $categoriaId && $subcategoriaId) {
        $categoria = Categoria::find($categoriaId);
        $subcategoria = Subcategoria::find($subcategoriaId);

        if ($categoria && $subcategoria) {
            $descripcionAutomatica = $categoria->nombre_categoria . ' de ' . $subcategoria->nombre_subcategoria;
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
               /* Tables\Columns\TextColumn::make('categoria.nombre_categoria')
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
                Tables\Columns\TextColumn::make('monto')->label('Monto')->money('PEN')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('tipo_egreso')->label('Tipo de Egreso')->options([
                    'gasto' => 'Gasto',
                    'desembolso' => 'Desembolso',
                ]),
                //Tables\Filters\SelectFilter::make('categoria_id')->label('Categoría')->relationship('categoria', 'nombre_categoria'),
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
                Tables\Actions\EditAction::make()->visible(fn ($record) => $record->tipo_egreso === 'gasto'),
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEgresos::route('/'),
            'create' => Pages\CreateEgresos::route('/create'),
            'edit' => Pages\EditEgresos::route('/{record}/edit'),
        ];
    }
}
