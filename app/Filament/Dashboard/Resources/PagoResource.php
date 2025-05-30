<?php

namespace App\Filament\Dashboard\Resources;

use App\Filament\Dashboard\Resources\PagoResource\Pages;
use App\Models\Pago;
use App\Models\CuotasGrupales;
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
                    return CuotasGrupales::with('prestamo.grupo')
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
                    $cuota = CuotasGrupales::whereHas('prestamo', function ($query) use ($state) {
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
                    'cuota_mora' => 'Pago de Cuota + Mora',
                    'solo_mora' => 'Mora',
                    'pago_parcial' => 'Pago Parcial',
                ])
                ->required()
                ->reactive()
                ->afterStateUpdated(function ($state, callable $set, callable $get) {
                    $cuotaId = $get('cuota_grupal_id');
                    $cuota = \App\Models\CuotasGrupales::with('mora')->find($cuotaId);
                    $saldoPendiente = $cuota ? floatval($cuota->saldo_pendiente) : 0;
                    $montoMora = $cuota && $cuota->mora ? abs($cuota->mora->monto_mora_calculado) : 0;
                    if ($state === 'cuota') {
                        $set('monto_pagado', $saldoPendiente);
                        $set('monto_mora_aplicada', 0);
                    } elseif ($state === 'cuota_mora') {
                        $set('monto_mora_aplicada', $montoMora);
                        $set('monto_pagado', $saldoPendiente + $montoMora);
                    } elseif ($state === 'solo_mora') {
                        $set('monto_pagado', $montoMora);
                        $set('monto_mora_aplicada', $montoMora);
                    } else {
                        $set('monto_pagado', null);
                        $set('monto_mora_aplicada', 0);
                    }
                }),

            TextInput::make('monto_mora_aplicada')
                ->label('Monto de Mora Aplicado')
                ->numeric()
                ->disabled()
                ->dehydrated(false)
                ->visible(fn (callable $get) => $get('tipo_pago') === 'cuota_mora')
                ->afterStateHydrated(function ($component, $state, $record) {
                    if ($record && $record->tipo_pago === 'cuota_mora' && $record->cuotaGrupal && $record->cuotaGrupal->mora) {
                        $component->state(abs($record->cuotaGrupal->mora->monto_mora_calculado));
                    }
                }),

            TextInput::make('monto_pagado')
                ->label('Monto Pagado')
                ->numeric()
                ->required()
                ->disabled(fn (callable $get) => $get('tipo_pago') === 'cuota' || $get('tipo_pago') === 'cuota_mora' || $get('tipo_pago') === 'solo_mora')
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
                    'Rechazado' => 'Rechazado',
                ])
                ->default('Pendiente')
                ->disabled()
                ->dehydrated(),

            TextInput::make('saldo_pendiente')
                ->label('Saldo Pendiente (Total a Pagar)')
                ->numeric()
                ->disabled()
                ->dehydrated(false)
                ->visible(fn ($get, $record) => $record !== null)
                ->afterStateHydrated(function ($component, $state, $record) {
                    if ($record && $record->cuotaGrupal) {
                        // Sumar todos los pagos aprobados para esta cuota
                        $pagosAprobados = $record->cuotaGrupal->pagos()->where('estado_pago', 'Aprobado')->sum('monto_pagado');
                        $saldo = floatval($record->cuotaGrupal->saldo_pendiente);
                        $mora = $record->cuotaGrupal->mora ? abs($record->cuotaGrupal->mora->monto_mora_calculado) : 0;
                        $saldoReal = max(($saldo + $mora) - $pagosAprobados, 0);
                        $component->state($saldoReal);
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
                Tables\Columns\TextColumn::make('cuotaGrupal.numero_cuota')
                    ->label('Cuota')
                    ->sortable()
                    ->alignCenter()
                    ->searchable()
                    ->width('45px'),

                Tables\Columns\TextColumn::make('cuotaGrupal.prestamo.grupo.nombre_grupo')
                    ->label('Grupo')
                    ->sortable()
                    ->searchable()
                    ->alignCenter()
                    ->width('80px'),

                Tables\Columns\TextColumn::make('tipo_pago')
                    ->label('Tipo')
                    ->alignCenter()
                    ->searchable()
                    ->width('65px'),

                Tables\Columns\TextColumn::make('cuotaGrupal.fecha_vencimiento')
                    ->label('F.Venc.')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->alignCenter()
                    ->width('75px'),

                Tables\Columns\TextColumn::make('cuotaGrupal.monto_cuota_grupal')
                    ->label('Cuota')
                    ->alignCenter()
                    ->sortable()
                    ->money('PEN')
                    ->width('70px'),

                Tables\Columns\TextColumn::make('cuotaGrupal.mora.monto_mora_calculado')
                    ->label('Mora')
                    ->alignCenter()
                    ->money('PEN')
                    ->formatStateUsing(function($state, $record) {
                        $mora = $record->cuotaGrupal && $record->cuotaGrupal->mora ? $record->cuotaGrupal->mora : null;
                        // Siempre mostrar el monto de mora calculado, aunque esté pagada
                        if (!$mora || !isset($mora->monto_mora_calculado)) {
                            return number_format(0, 2);
                        }
                        return number_format(abs($mora->monto_mora_calculado), 2);
                    })
                    ->width('65px'),

                Tables\Columns\TextColumn::make('cuotaGrupal.monto_total_a_pagar')
                    ->label('Total')
                    ->alignCenter()
                    ->money('PEN')
                    ->formatStateUsing(function ($state, $record) {
                        $cuota = $record->cuotaGrupal;
                        $saldo = $cuota ? floatval($cuota->monto_cuota_grupal) : 0;
                        $mora = $cuota && $cuota->mora ? abs($cuota->mora->monto_mora_calculado) : 0;
                        // Siempre mostrar la suma cuota + mora, aunque ya esté pagada
                        return number_format($saldo + $mora, 2);
                    })
                    ->width('75px'),

                Tables\Columns\TextColumn::make('monto_pagado')
                    ->label('Pagado')
                    ->alignCenter()
                    ->searchable()
                    ->money('PEN')
                    ->width('65px'),

                Tables\Columns\TextColumn::make('fecha_pago')
                    ->label('F. Pago')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->alignCenter()
                    ->width('75px'),

                Tables\Columns\TextColumn::make('cuotaGrupal.saldo_pendiente')
                    ->label('Saldo')
                    ->alignCenter()
                    ->sortable()
                    ->formatStateUsing(function ($state, $record) {
                        $cuota = $record->cuotaGrupal;
                        $saldo = $cuota ? floatval($cuota->saldo_pendiente) : 0;
                        $mora = $cuota && $cuota->mora ? abs($cuota->mora->monto_mora_calculado) : 0;
                        // Si la mora está pagada, no sumarla
                        if ($cuota && $cuota->mora && isset($cuota->mora->estado_mora) && strtolower($cuota->mora->estado_mora) === 'pagada') {
                            $mora = 0;
                        }
                        // Sumar todos los pagos aprobados para esta cuota
                        $pagosAprobados = $cuota ? $cuota->pagos()->where('estado_pago', 'Aprobado')->sum('monto_pagado') : 0;
                        $saldoReal = max(($saldo + $mora) - $pagosAprobados, 0);
                        return number_format($saldoReal, 2);
                    })
                    ->width('70px'),

                Tables\Columns\TextColumn::make('estado_pago')
                    ->label('Estado')
                    ->alignCenter()
                    ->searchable()
                    ->width('60px'),
            ])
            ->filters([
                Tables\Filters\Filter::make('fecha_pago')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('Desde'),
                        Forms\Components\DatePicker::make('until')->label('Hasta'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'], fn ($q, $date) => $q->whereDate('fecha_pago', '>=', $date))
                            ->when($data['until'], fn ($q, $date) => $q->whereDate('fecha_pago', '<=', $date));
                    }),
                // ...otros filtros si tienes...
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
