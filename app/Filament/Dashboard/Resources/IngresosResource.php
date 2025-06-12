<?php

namespace App\Filament\Dashboard\Resources;

use App\Filament\Dashboard\Resources\IngresosResource\Pages;
use App\Models\Ingreso;
use App\Models\Pago;
use App\Models\Grupo;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Dashboard\Resources\Badge;
use Filament\Tables\Columns\BadgeColumn;
use Illuminate\Support\Facades\Auth;



class IngresosResource extends Resource
{
    protected static ?string $model = Ingreso::class;
    protected static ?string $navigationGroup = 'Movimientos financieros';
    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationLabel = 'Ingresos';
    protected static ?string $modelLabel = 'Ingreso';
    protected static ?string $pluralModelLabel = 'Ingresos';

public static function form(Form $form): Form
{
    return $form
        ->schema(function (Forms\Get $get, ?Ingreso $record) {
            $isEditing = filled($record);

            return [
            TextInput::make('tipo_ingreso')
                ->label('Tipo de Ingreso')
                ->default('transferencia')
                ->disabled() // Siempre deshabilitado
                ->dehydrated() // Asegura que sí se guarde en la BD
                ->required(),

                Select::make('pago_id')
                    ->label('Pago de Grupo')
                    ->options(function (callable $get) {
                        $currentPagoId = $get('pago_id');

                        $pagosQuery = Pago::with(['cuotaGrupal.prestamo.grupo'])
                            ->where('estado_pago', 'aprobado')
                            ->whereDoesntHave('ingreso');

                        if ($currentPagoId) {
                            $pagos = $pagosQuery->orWhere('id', $currentPagoId)->get();
                        } else {
                            $pagos = $pagosQuery->get();
                        }

                        return $pagos->mapWithKeys(function ($pago) {
                            $nombreGrupo = 'Sin Grupo';
                            if ($pago->cuotaGrupal &&
                                $pago->cuotaGrupal->prestamo &&
                                $pago->cuotaGrupal->prestamo->grupo) {
                                $nombreGrupo = $pago->cuotaGrupal->prestamo->grupo->nombre_grupo;
                            }
                            return [
                                $pago->id => $nombreGrupo,
                            ];
                        });
                    })
                    ->searchable()
                    ->reactive()
                    ->visible(fn (Forms\Get $get) => $get('tipo_ingreso') === 'pago de cuota de grupo')
                    ->afterStateUpdated(function ($state, Forms\Set $set) {
                        if ($state) {
                            $pago = Pago::with(['cuotaGrupal.prestamo.grupo'])->find($state);
                            if ($pago) {
                                $set('monto', $pago->monto_pagado);
                                $set('fecha_hora', $pago->fecha_pago);
                                $set('codigo_operacion', $pago->codigo_operacion);

                                if ($pago->cuotaGrupal && $pago->cuotaGrupal->prestamo) {
                                    $set('grupo_id', $pago->cuotaGrupal->prestamo->grupo_id);
                                }
                            }
                        } else {
                            $set('monto', null);
                            $set('fecha_hora', now());
                            $set('codigo_operacion', null);
                            $set('grupo_id', null);
                        }
                    })
                    ->disabled($isEditing),

                Hidden::make('grupo_id'),

                TextInput::make('codigo_operacion')
                    ->label('Código de Operación')
                    ->visible(fn (Forms\Get $get) => $get('tipo_ingreso') === 'pago de cuota de grupo' && $get('pago_id'))
                    ->disabled() // Este siempre está deshabilitado
                    ->dehydrated(false)
                    ->afterStateHydrated(function (TextInput $component, $state, $record) {
                        if ($record && $record->tipo_ingreso === 'pago de cuota de grupo' && $record->pago) {
                            $component->state($record->pago->codigo_operacion);
                        }
                    }),

               TextInput::make('monto')
            ->label('Monto')
            ->numeric()
            ->step(0.01)
            ->prefix('S/')
            ->required()
          ->disabled(fn (Forms\Get $get) => $isEditing && in_array($get('tipo_ingreso'), ['transferencia', 'pago de cuota de grupo'])),



            DateTimePicker::make('fecha_hora')
                ->label('Fecha y Hora')
                ->required()
                ->default(now())
            ->disabled(fn (Forms\Get $get) => $isEditing && in_array($get('tipo_ingreso'), ['transferencia', 'pago de cuota de grupo'])),


        Textarea::make('descripcion')
            ->label('Descripción')
            ->rows(3)
            ->columnSpanFull()
            ->required()
            ->disabled(fn (Forms\Get $get) => $isEditing && in_array($get('tipo_ingreso'), ['transferencia', 'pago de cuota de grupo'])),

                    ];
                });
}


    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                //TextColumn::make('id')
                  //  ->label('ID')
                    //->sortable(),

                    TextColumn::make('fecha_hora')
                    ->label('Fecha y Hora')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),


                BadgeColumn::make('tipo_ingreso')
                    ->label('Tipo')
                    ->colors([
                        'primary' => 'transferencia',
                        'success' => 'pago de cuota de grupo',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'transferencia' => 'Transferencia',
                        'pago de cuota de grupo' => 'Pago de Cuota',
                        default => $state,
                    }),

                TextColumn::make('grupo_nombre')
                    ->label('Grupo')
                    ->getStateUsing(fn ($record) => $record->pago?->cuotaGrupal?->prestamo?->grupo?->nombre_grupo ?? 'Sin grupo')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('descripcion')
                    ->label('Descripción')
                    ->limit(50)
                    ->tooltip(function (TextColumn $column): ?string {
                        $state = $column->getState();
                        if (strlen($state) <= 50) {
                            return null;
                        }
                        return $state;
                    }),

               /* TextColumn::make('codigo_operacion')
                   ->label('Código de Operación')
                    ->placeholder('N/A')
                    ->searchable()
                    ->getStateUsing(function ($record) {
                        // Solo mostrar para pagos de cuota de grupo
                        if ($record->tipo_ingreso === 'pago de cuota de grupo' && $record->pago) {
                            return $record->pago->codigo_operacion;
                        }
                        return null;
                    }),
                */
                TextColumn::make('monto')
                    ->label('Monto')
                    ->money('PEN')
                    ->sortable(),



            ])
            ->filters([
                Tables\Filters\SelectFilter::make('tipo_ingreso')
                    ->label('Tipo de Ingreso')
                    ->options([
                        'transferencia' => 'Transferencia',
                        'pago de cuota de grupo' => 'Pago de Cuota de Grupo',
                    ]),

                //Tables\Filters\SelectFilter::make('grupo_id')
                  //  ->label('Grupo')
                    //->relationship('grupo', 'nombre_grupo')
                    //->searchable(),

                Tables\Filters\Filter::make('fecha_rango')
                    ->form([
                        Forms\Components\DatePicker::make('desde')
                            ->label('Desde'),
                        Forms\Components\DatePicker::make('hasta')
                            ->label('Hasta'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['desde'],
                                fn (Builder $query, $date): Builder => $query->whereDate('fecha_hora', '>=', $date),
                            )
                            ->when(
                                $data['hasta'],
                                fn (Builder $query, $date): Builder => $query->whereDate('fecha_hora', '<=', $date),
                            );
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
            ->defaultSort('fecha_hora', 'desc')
            ->striped();
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
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
            'index' => Pages\ListIngresos::route('/'),
            'create' => Pages\CreateIngresos::route('/create'),
            'edit' => Pages\EditIngresos::route('/{record}/edit'),
        ];
    }
}
