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
use Filament\Tables\Actions\ActionGroup;
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
        return $form->schema([
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
                ->afterStateHydrated(function ($component, $state, $record) {
                    if ($record && $record->cuotaGrupal && $record->cuotaGrupal->prestamo && $record->cuotaGrupal->prestamo->grupo) {
                        $component->state($record->cuotaGrupal->prestamo->grupo->id);
                        $component->disabled(true);
                    } else {
                        $component->disabled(false);
                    }
                })
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
                ->dehydrated()
                ->afterStateHydrated(function ($component, $state, $record) {
                    if ($record && $record->cuotaGrupal) {
                        $component->state($record->cuotaGrupal->numero_cuota);
                    }
                }),

            TextInput::make('monto_cuota')
                ->label('Monto de la Cuota')
                ->numeric()
                ->disabled()
                ->required()
                ->dehydrated()
                ->afterStateHydrated(function ($component, $state, $record) {
                    if ($record && $record->cuotaGrupal) {
                        $component->state($record->cuotaGrupal->monto_cuota_grupal);
                    }
                }),

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

            Select::make('estado_pago')
                ->label('Estado del Pago')
                ->options([
                    'Pendiente' => 'Pendiente',
                    'Aprobado' => 'Aprobado',
                    'Rechazado' => 'Rechazado'
                ])
                ->default('Pendiente')
                ->disabled(function ($get, $record) {
                    if (Auth::user()?->hasRole('Asesor')) {
                        return true;
                    }
                    $estado = strtolower($get('estado_pago') ?? $record?->estado_pago);
                    return in_array($estado, ['aprobado', 'rechazado']);
                }),

            TextInput::make('saldo_pendiente')
                ->label('Saldo Pendiente')
                ->numeric()
                ->disabled()
                ->dehydrated(false)
                ->visible(fn ($get, $record) => $record !== null)
                ->afterStateHydrated(function ($component, $state, $record) {
                    if ($record && $record->cuotaGrupal) {
                        $component->state($record->cuotaGrupal->saldo_pendiente);
                    } else {
                        $component->state(null);
                    }
                }),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('cuotaGrupal.prestamo.grupo.nombre_grupo')
                    ->label('Grupo')
                    ->sortable()
                    ->searchable()
                    ->alignCenter()
                    ->width('130px'),

                Tables\Columns\TextColumn::make('cuotaGrupal.numero_cuota')
                    ->label('N°')
                    ->sortable()
                    ->alignCenter()
                    ->width('70px'),

                Tables\Columns\TextColumn::make('cuotaGrupal.monto_cuota_grupal')
                    ->label('Monto Cuota')
                     ->alignCenter()
                    ->sortable()
                    ->money('PEN')
                    ->width('110px'),

                Tables\Columns\TextColumn::make('cuotaGrupal.fecha_vencimiento')
                    ->label('Vencimiento')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->alignCenter()
                    ->width('120px'),

                Tables\Columns\TextColumn::make('tipo_pago')
                    ->label('Tipo')
                    ->alignCenter()
                    ->width('100px'),

                Tables\Columns\TextColumn::make('monto_pagado')
                    ->label('Pagado')
                     ->alignCenter()
                    ->searchable()
                    ->money('PEN')
                    ->width('100px'),

                Tables\Columns\TextColumn::make('fecha_pago')
                    ->label('Fecha Pago')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->alignCenter()
                    ->width('120px'),

                Tables\Columns\TextColumn::make('estado_pago')
                    ->label('Estado')
                    ->alignCenter()
                    ->width('100px'),

                Tables\Columns\TextColumn::make('cuotaGrupal.saldo_pendiente')
                    ->label('Saldo')
                     ->alignCenter()
                    ->sortable()
                    ->width('100px'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Registrado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->alignCenter()
                    ->width('140px'),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->alignCenter()
                    ->width('140px'),
            ])
            ->actions([
                ActionGroup::make([
                    Tables\Actions\EditAction::make(),

                    Action::make('aprobar')
                        ->label('Aprobar')
                        ->icon('heroicon-m-check-circle')
                        ->color('success')
                        ->visible(fn ($record) => in_array(strtolower($record->estado_pago), ['pendiente']) && Auth::user()?->hasAnyRole(['super_admin', 'Jefe de operaciones', 'Jefe de creditos']))
                        ->action(function ($record) {
                            $record->aprobar();
                            \Filament\Notifications\Notification::make()
                                ->title('Pago aprobado')
                                ->success()
                                ->send();
                        }),

                    Action::make('rechazar')
                        ->label('Rechazar')
                        ->icon('heroicon-m-x-circle')
                        ->color('danger')
                        ->visible(fn ($record) => in_array(strtolower($record->estado_pago), ['pendiente']) && Auth::user()?->hasAnyRole(['super_admin', 'Jefe de operaciones', 'Jefe de creditos']))
                        ->action(function ($record) {
                            $record->rechazar();
                            \Filament\Notifications\Notification::make()
                                ->title('Pago rechazado')
                                ->danger()
                                ->send();
                        }),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
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
