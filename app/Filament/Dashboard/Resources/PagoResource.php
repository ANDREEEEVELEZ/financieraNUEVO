<?php

namespace App\Filament\Dashboard\Resources;

use App\Filament\Dashboard\Resources\PagoResource\Pages;
use App\Models\Pago;
use App\Models\Cuotas_Grupales;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;
use Illuminate\Support\Facades\Auth;

class PagoResource extends Resource
{
    protected static ?string $model = Pago::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationLabel = 'Pagos';
    protected static ?string $modelLabel = 'Pago';
    protected static ?string $pluralModelLabel = 'Pagos';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('grupo_id')
                    ->label('Grupo')
                    ->options(function () {
                        return Cuotas_Grupales::with('prestamo.grupo')
                            ->get()
                            ->mapWithKeys(function ($cuota) {
                                $grupo = $cuota->prestamo->grupo ?? null;
                                if ($grupo) {
                                    return [$grupo->id => $grupo->nombre_grupo];
                                }
                                return [];
                            })
                            ->unique();
                    })
                    ->searchable()
                    ->required()
                    ->reactive()
                    ->afterStateUpdated(function ($state, callable $set) {
                        $cuota = Cuotas_Grupales::whereHas('prestamo', function ($query) use ($state) {
                                $query->where('grupo_id', $state);
                            })
                            ->whereIn('estado_cuota_grupal', ['vigente', 'mora'])
                            ->whereIn('estado_pago', ['pendiente', 'parcial'])
                            ->orderBy('numero_cuota', 'asc')
                            ->first();

                        if ($cuota) {
                            $set('cuota_grupal_id', $cuota->id);
                            $set('numero_cuota', $cuota->numero_cuota);
                            $set('monto_cuota', $cuota->monto_cuota_grupal);
                        } else {
                            $set('cuota_grupal_id', null);
                            $set('numero_cuota', null);
                            $set('monto_cuota', null);
                        }
                    }),

                Hidden::make('cuota_grupal_id')->required(),

                TextInput::make('numero_cuota')
                    ->label('Número de Cuota')
                    ->numeric()
                    ->disabled()
                    ->required()
                    ->dehydrated(),

                TextInput::make('monto_cuota')
                    ->label('Monto de la Cuota')
                    ->numeric()
                    ->disabled()
                    ->required()
                    ->dehydrated(),

                Select::make('tipo_pago')
                    ->label('Tipo de Pago')
                    ->options([
                        'cuota' => 'Pago de Cuota',
                        'amortizacion' => 'Amortización Adicional',
                    ])
                    ->required()
                    ->reactive()
                    ->afterStateUpdated(function ($state, callable $set, callable $get) {
                        if ($state === 'cuota') {
                            $set('monto_pagado', $get('monto_cuota'));
                        } else {
                            $set('monto_pagado', null);
                        }
                    }),

                TextInput::make('monto_pagado')
                    ->label('Monto Pagado')
                    ->numeric()
                    ->required()
                    ->disabled(fn (callable $get) => $get('tipo_pago') === 'cuota')
                    ->dehydrated(),

                DateTimePicker::make('fecha_pago')
                    ->label('Fecha de Pago')
                    ->required(),

                TextInput::make('observaciones')
                    ->label('Observaciones')
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('cuotaGrupal.prestamo.grupo.nombre_grupo')
                    ->label('Grupo')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('tipo_pago')
                    ->label('Tipo de Pago')
                    ->searchable(),

                Tables\Columns\TextColumn::make('monto_pagado')
                    ->label('Monto Pagado')
                    ->searchable(),

                Tables\Columns\TextColumn::make('fecha_pago')
                    ->label('Fecha de Pago')
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('estado_pago')
                    ->label('Estado del Pago')
                    ->searchable(),

                Tables\Columns\TextColumn::make('observaciones')
                    ->label('Observaciones')
                    ->searchable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha de Registro')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Última Actualización')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
          
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()->label('Eliminar seleccionados'),
                ]),
            ]);
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
            'index' => Pages\ListPagos::route('/'),
            'create' => Pages\CreatePago::route('/crear'),
            'edit' => Pages\EditPago::route('/{record}/editar'),
        ];
    }
}
