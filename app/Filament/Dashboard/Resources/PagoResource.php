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
use Illuminate\Database\Eloquent\Builder;

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
                    $user = request()->user();
                    $query = \App\Models\Grupo::whereHas('prestamos', function($q) {
                        $q->where('estado', 'Aprobado');
                    })->orderBy('nombre_grupo', 'asc');

                    if ($user->hasRole('Asesor')) {
                        $asesor = \App\Models\Asesor::where('user_id', $user->id)->first();
                        if ($asesor) {
                            $query->where('asesor_id', $asesor->id);
                        } else {
                            return []; // Si el asesor no existe, retornar vacío
                        }
                    } elseif (!$user->hasAnyRole(['super_admin', 'Jefe de operaciones', 'Jefe de creditos'])) {
                        return []; // Si no tiene roles permitidos, retornar vacío
                    }

                    return $query->pluck('nombre_grupo', 'id');
                })
                ->afterStateHydrated(function ($component, $state, $record) {
                    if ($record && $record->cuotaGrupal && $record->cuotaGrupal->prestamo && $record->cuotaGrupal->prestamo->grupo) {
                        $component->state($record->cuotaGrupal->prestamo->grupo->id);
                        $component->disabled(true);
                    } else {
                        $component->disabled(false);
                    }
                })
                ->afterStateUpdated(function ($state, callable $set) {
                    $cuotas = CuotasGrupales::whereHas('prestamo', function ($query) use ($state) {
                        $query->where('grupo_id', $state);
                    })
                    ->whereIn('estado_cuota_grupal', ['vigente', 'mora'])
                    ->orderBy('numero_cuota', 'asc')
                    ->get();

                    foreach ($cuotas as $cuota) {
                        if ($cuota->saldoPendiente() > 0) {
                            $set('cuota_grupal_id', $cuota->id);
                            $set('numero_cuota', $cuota->numero_cuota);
                            $set('monto_cuota', $cuota->monto_cuota_grupal);
                            $set('monto_mora_pagada', $cuota->mora ? abs($cuota->mora->monto_mora_calculado) : 0);
                            $set('saldo_pendiente_actual', $cuota->saldoPendiente());
                            return;
                        }
                    }

                    $set('cuota_grupal_id', null);
                    $set('numero_cuota', null);
                    $set('monto_cuota', null);
                    $set('monto_mora_pagada', 0.00);
                    $set('saldo_pendiente_actual', 0.00);
                })

                ->searchable()
                ->required()
                ->reactive()
                // Deshabilitar en modo edición
                ->disabled(fn ($record) => $record !== null),

            Hidden::make('cuota_grupal_id')->required(),

            TextInput::make('numero_cuota')
                ->label('Número de Cuota')
                ->numeric()
                ->disabled() // Ya estaba deshabilitado
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
                ->disabled() // Ya estaba deshabilitado
                ->required()
                ->dehydrated()
                ->afterStateHydrated(function ($component, $state, $record) {
                    if ($record && $record->cuotaGrupal) {
                        $component->state($record->cuotaGrupal->monto_cuota_grupal);
                    }
                }),

            TextInput::make('monto_mora_pagada')
                ->label('Monto de Mora Aplicado')
                ->numeric()
                ->disabled() // Ya estaba deshabilitado
                ->dehydrated(true)
                ->default(0.00)
                ->afterStateHydrated(function ($component, $state, $record) {
                    if ($record && $record->cuotaGrupal && $record->cuotaGrupal->mora) {
                        $component->state(abs($record->cuotaGrupal->mora->monto_mora_calculado));
                    } else {
                        $component->state(0.00);
                    }
                }),

                TextInput::make('saldo_pendiente_actual')
                    ->label('Saldo Pendiente')
                    ->numeric()
                    ->disabled()
                    ->dehydrated(false)
                    ->prefix('S/.')
                    ->afterStateHydrated(function ($component, $state, $record, callable $get) {
                        if ($record && $record->cuotaGrupal) {
                            $component->state($record->cuotaGrupal->saldoPendiente());
                        } else {
                            $cuotaId = $get('cuota_grupal_id');
                            if ($cuotaId && $cuota = \App\Models\CuotasGrupales::with('mora')->find($cuotaId)) {
                                $component->state($cuota->saldoPendiente());
                            }
                        }
                    }),

            Select::make('tipo_pago')
                ->label('Tipo de Pago')
                ->options([
                    'pago_completo' => 'Pago Completo',
                    'pago_parcial' => 'Pago Parcial',
                ])
                ->required()
                ->reactive()
                ->dehydrated(true)
                ->afterStateUpdated(function ($state, callable $set, callable $get) {
                    $cuotaId = $get('cuota_grupal_id');
                    if (!$cuotaId) return;

                    $cuota = CuotasGrupales::with('mora')->find($cuotaId);
                    $montoCuota = $cuota ? floatval($cuota->monto_cuota_grupal) : 0;
                    $montoMora = $cuota && $cuota->mora ? abs($cuota->mora->monto_mora_calculado) : 0;

                    // Calcular saldo pendiente actual
                    $pagosAprobados = $cuota ? $cuota->pagos()->where('estado_pago', 'Aprobado')->sum('monto_pagado') : 0;
                    $saldoPendiente = max(($montoCuota + $montoMora) - $pagosAprobados, 0);

                    if ($state === 'pago_completo') {
                        $set('monto_pagado', $saldoPendiente);
                        $set('monto_mora_pagada', $montoMora);
                    } elseif ($state === 'pago_parcial') {
                        // En pago parcial no cambiar el monto_pagado automáticamente
                        $set('monto_mora_pagada', $montoMora);
                    }
                })
                // Deshabilitar en modo edición
                ->disabled(fn ($record) => $record !== null),

            TextInput::make('monto_pagado')
                ->label('Monto Pagado')
                ->numeric()
                ->required()
                ->disabled(function (callable $get, $record) {
                    // Si está en modo edición, deshabilitar siempre
                    if ($record !== null) {
                        return true;
                    }
                    // Si está en modo creación, aplicar la lógica original
                    return $get('tipo_pago') === 'pago_completo';
                })
                ->dehydrated()
                ->live(onBlur: true)
                ->afterStateUpdated(function ($state, callable $set, callable $get) {
                    // Validar que el monto pagado no exceda el saldo pendiente
                    $saldoPendiente = floatval($get('saldo_pendiente_actual') ?? 0);
                    $montoPagado = floatval($state ?? 0);

                    if ($montoPagado > $saldoPendiente && $saldoPendiente > 0) {
                        $set('monto_pagado', $saldoPendiente);

                    }
                })
                ->helperText(function (callable $get) {
                    $saldoPendiente = $get('saldo_pendiente_actual');
                    if ($saldoPendiente > 0) {
                        return 'Máximo a pagar: S/. ' . number_format($saldoPendiente, 2);
                    }
                    return null;
                }),

            TextInput::make('codigo_operacion')
                ->label('Código de Operación')
                ->required()
                ->maxLength(255)
                ->afterStateHydrated(function ($component, $state, $record) {
                    // Asegurarse de que el código de operación se cargue correctamente en edición
                    if ($record && $record->codigo_operacion) {
                        $component->state($record->codigo_operacion);
                    }
                })
                // Deshabilitar en modo edición
                ->disabled(fn ($record) => $record !== null),

DateTimePicker::make('fecha_pago')
    ->label('Fecha de Pago')
    ->required()
    ->dehydrated(true)
    ->disabled(fn ($record) => $record !== null)
    ->default(function () {
        return now()->format('Y-m-d H:i:s');
    }),

            TextInput::make('observaciones')
                ->label('Observaciones')
                ->maxLength(255)
                // Deshabilitar en modo edición
                ->disabled(fn ($record) => $record !== null),

            Select::make('estado_pago')
                ->label('Estado del Pago')
                ->options([
                    'Pendiente' => 'Pendiente',
                    'aprobado' => 'Aprobado',
                    'Rechazado' => 'Rechazado',
                ])
                ->default('Pendiente')
                ->disabled() // Ya estaba deshabilitado
                ->dehydrated(),

            // Campo para mostrar en edición (mantenemos el original para compatibilidad)
            TextInput::make('saldo_pendiente')
                ->label('Saldo Pendiente (Total a Pagar)')
                ->numeric()
                ->disabled() // Ya estaba deshabilitado
                ->dehydrated(false)
                ->visible(fn ($record) => $record !== null && false) // Ocultamos este campo ya que tenemos el nuevo
                ->afterStateHydrated(function ($component, $state, $record) {
                    // Código original mantenido para compatibilidad
                    if ($record && $record->cuotaGrupal) {
                        $cuota = $record->cuotaGrupal->fresh();
                        $saldo = floatval($cuota->saldo_pendiente);
                        $mora = $cuota->mora ? abs($cuota->mora->monto_mora_calculado) : 0;

                        if (strtolower($record->estado_pago) === 'pendiente') {
                            $component->state($saldo + $mora);
                        } else {
                            $pagosAprobados = $cuota->pagos()->where('estado_pago', 'Aprobado')->sum('monto_pagado');
                            $saldoReal = max(($saldo + $mora) - $pagosAprobados, 0);
                            $component->state($saldoReal);
                        }
                    } else {
                        $component->state(null);
                    }
                })
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('cuotaGrupal.numero_cuota')
                    ->label('Cuota')
                    ->sortable()
                    ->alignLeft()
                    ->searchable()
                    ->width('45px'),

                Tables\Columns\TextColumn::make('cuotaGrupal.prestamo.grupo.nombre_grupo')
                    ->label('Grupo')
                    ->sortable()
                    ->searchable()
                    ->alignLeft()
                    ->width('80px'),

                Tables\Columns\TextColumn::make('tipo_pago')
                    ->label('Tipo')
                    ->alignLeft()
                    ->searchable()
                    ->width('65px'),

                Tables\Columns\TextColumn::make('cuotaGrupal.fecha_vencimiento')
                    ->label('F.Venc.')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->alignLeft()
                    ->width('75px'),

                Tables\Columns\TextColumn::make('cuotaGrupal.monto_cuota_grupal')
                    ->label('Cuota')
                    ->alignLeft()
                    ->sortable()
                    ->money('PEN')
                    ->width('70px'),

                Tables\Columns\TextColumn::make('cuotaGrupal.mora.monto_mora_calculado')
                    ->label('Mora')
                    ->alignLeft()
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
                    ->alignLeft()
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
                    ->alignLeft()
                    ->searchable()
                    ->money('PEN')
                    ->width('65px'),

                Tables\Columns\TextColumn::make('fecha_pago')
                    ->label('F. Pago')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->alignLeft()
                    ->default(now())
                    ->width('75px'),

                Tables\Columns\TextColumn::make('cuotaGrupal.saldo_pendiente')
                    ->label('Saldo Pendiente')
                    ->formatStateUsing(fn($record) => 'S/. ' . number_format($record->cuotaGrupal->saldoPendiente(), 2))
                    ->sortable()
                    ->formatStateUsing(function ($state, $record) {
                        // Si el pago está rechazado, no mostrar saldo
                        if ($record->estado_pago === 'Rechazado') {
                            return 'N/A';
                        }

                        $cuota = $record->cuotaGrupal?->fresh();

                        if (!$cuota) {
                            return '-';
                        }

                        $montoCuota = floatval($cuota->monto_cuota_grupal);
                        $montoMora = $cuota->mora ? abs($cuota->mora->monto_mora_calculado) : 0;

                        $pagosAprobados = $cuota->pagos()
                            ->where('estado_pago', 'Aprobado')
                            ->sum('monto_pagado');

                        $saldo = max(($montoCuota + $montoMora) - $pagosAprobados, 0);

                        return number_format($saldo, 2);
                    })
                    ->width('70px'),

                Tables\Columns\TextColumn::make('estado_pago')
                    ->label('Estado')
                    ->alignLeft()
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

    public static function getEloquentQuery(): Builder
    {
        $user = request()->user();
        $query = parent::getEloquentQuery();

        if ($user->hasRole('Asesor')) {
            $asesor = \App\Models\Asesor::where('user_id', $user->id)->first();

            if ($asesor) {
                $query->whereHas('cuotaGrupal.prestamo.grupo', function ($subQuery) use ($asesor) {
                    $subQuery->where('asesor_id', $asesor->id);
                });
            }
        }

        return $query;
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
