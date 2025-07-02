<?php

namespace App\Filament\Dashboard\Resources\PagoResource\Pages;

use App\Filament\Dashboard\Resources\PagoResource;
use App\Models\Grupo;
use App\Models\Pago;
use Filament\Resources\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Filament\Actions;
use Filament\Notifications\Notification;

class GrupoDetallePagos extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = PagoResource::class;
    protected static string $view = 'filament.dashboard.pages.grupo-detalle-pagos';

    public Grupo $grupo;


    public function mount(int|Grupo $grupo): void
    {

        if (is_int($grupo)) {
            $this->grupo = Grupo::findOrFail($grupo);
        } else {

            $this->grupo = $grupo;
        }


        $user = Auth::user();
        if ($user->hasRole('Asesor')) {
            $asesor = \App\Models\Asesor::where('user_id', $user->id)->first();
            if (!$asesor || $this->grupo->asesor_id !== $asesor->id) {
                abort(403, 'No tienes permisos para ver los pagos de este grupo.');
            }
        }
    }

    protected function getTableQuery(): Builder
    {
        return Pago::query()
            ->whereHas('cuotaGrupal.prestamo', function ($query) {
                $query->where('grupo_id', $this->grupo->id);
            })
            ->with([
                'cuotaGrupal.prestamo.grupo',
                'cuotaGrupal.mora'
            ])
            ->orderBy('created_at', 'desc');
    }
public function table(Table $table): Table
{
    return $table
        ->query($this->getTableQuery())

        ->recordAction('edit')
        ->columns([
            Tables\Columns\TextColumn::make('cuotaGrupal.numero_cuota')
                ->label('Cuota')
                ->sortable()
                ->alignCenter()
                ->badge()
                ->color('primary'),
            Tables\Columns\TextColumn::make('estado_pago')
                ->label('Estado')
                ->alignCenter()
                ->badge()
                ->color(fn ($state) => match(strtolower($state)) {
                    'pendiente' => 'warning',
                    'aprobado' => 'success',
                    'rechazado' => 'danger',
                    default => 'gray'
                }),

            Tables\Columns\TextColumn::make('tipo_pago')
                ->label('Tipo')
                ->alignCenter()
                ->badge()
                ->color(fn ($state) => match($state) {
                    'pago_completo' => 'success',
                    'pago_parcial' => 'warning',
                    default => 'gray'
                }),

            Tables\Columns\TextColumn::make('codigo_operacion')
                ->label('Código Operación')
                ->searchable()
                ->copyable()
                ->copyMessage('Copiado!')
                ->weight('medium'),

            Tables\Columns\TextColumn::make('fecha_pago')
                ->label('Fecha Pago')
                ->dateTime('d/m/Y H:i')
                ->sortable()
                ->alignCenter(),

            Tables\Columns\TextColumn::make('cuotaGrupal.fecha_vencimiento')
                ->label('Fecha Vencimiento')
                ->date('d/m/Y')
                ->sortable()
                ->alignCenter(),

            Tables\Columns\TextColumn::make('cuotaGrupal.monto_cuota_grupal')
                ->label('Monto Cuota')
                ->money('PEN')
                ->alignRight()
                ->weight('medium'),

            Tables\Columns\TextColumn::make('monto_mora')
                ->label('Mora')
                ->money('PEN')
                ->alignRight()
                ->getStateUsing(function ($record) {
                    return $record->cuotaGrupal && $record->cuotaGrupal->mora
                        ? abs($record->cuotaGrupal->mora->monto_mora_calculado)
                        : 0;
                }),

            Tables\Columns\TextColumn::make('monto_pagado')
                ->label('Monto Pagado')
                ->money('PEN')
                ->alignRight()
                ->weight('bold')
                ->color('success'),

            Tables\Columns\TextColumn::make('saldo_pendiente')
                ->label('Saldo Pendiente')
                ->alignRight()
                ->weight('medium')
                ->getStateUsing(function ($record) {
                    if ($record->estado_pago === 'Rechazado') {
                        return 'N/A';
                    }

                    $cuota = $record->cuotaGrupal?->fresh();
                    if (!$cuota) {
                        return 0;
                    }

                    return $cuota->saldoPendiente();
                })
                ->formatStateUsing(fn ($state) => $state === 'N/A' ? $state : 'S/. ' . number_format($state, 2))
                ->color(fn ($state) => $state > 0 ? 'danger' : 'success'),

            Tables\Columns\TextColumn::make('observaciones')
                ->label('Observaciones')
                ->limit(30)
                ->tooltip(function ($record) {
                    return $record->observaciones;
                })
                ->toggleable(),
        ])
        ->filters([
            Tables\Filters\SelectFilter::make('estado_pago')
                ->label('Estado')
                ->options([
                    'Pendiente' => 'Pendiente',
                    'aprobado' => 'Aprobado',
                    'Rechazado' => 'Rechazado',
                ]),

            Tables\Filters\SelectFilter::make('tipo_pago')
                ->label('Tipo de Pago')
                ->options([
                    'pago_completo' => 'Pago Completo',
                    'pago_parcial' => 'Pago Parcial',
                ]),

            Tables\Filters\Filter::make('fecha_pago')
                ->form([
                    \Filament\Forms\Components\DatePicker::make('from')->label('Desde'),
                    \Filament\Forms\Components\DatePicker::make('until')->label('Hasta'),
                ])
                ->query(function (Builder $query, array $data): Builder {
                    return $query
                        ->when($data['from'], fn ($q, $date) => $q->whereDate('fecha_pago', '>=', $date))
                        ->when($data['until'], fn ($q, $date) => $q->whereDate('fecha_pago', '<=', $date));
                }),
        ])
        ->actions([
            Tables\Actions\ActionGroup::make([
                Tables\Actions\EditAction::make()
                    ->label(function ($record) {
                        return 'Ver Detalles';
                    })
                    ->icon(function ($record) {
                        $user = Auth::user();
                        $esPendiente = strtolower($record->estado_pago) === 'pendiente';
                        $esAsesor = $user->hasRole('Asesor');
                        return ($esPendiente && $esAsesor) ? 'heroicon-m-pencil-square' : 'heroicon-m-eye';
                    })
                    ->color(function ($record) {
                        $user = Auth::user();
                        $esPendiente = strtolower($record->estado_pago) === 'pendiente';
                        $esAsesor = $user->hasRole('Asesor');
                        return ($esPendiente && $esAsesor) ? 'primary' : 'gray';
                    })
                    ->form([
                        \Filament\Forms\Components\Actions::make([
                            \Filament\Forms\Components\Actions\Action::make('aprobarPago')
                                ->label('Aprobar')
                                ->color('success')
                                ->visible(function ($livewire, $record) {
                                    $user = Auth::user();
                                    return strtolower($record->estado_pago) === 'pendiente' &&
                                        $user->hasAnyRole(['super_admin', 'Jefe de operaciones']);
                                })
                                ->action(function ($livewire, $record) {
                                    $record->aprobar();
                                    \Filament\Notifications\Notification::make()
                                        ->title('Pago aprobado correctamente')
                                        ->success()
                                        ->send();
                                    $livewire->dispatch('closeEditModal');
                                }),

                            \Filament\Forms\Components\Actions\Action::make('rechazarPago')
                                ->label('Rechazar')
                                ->color('danger')
                                ->visible(function ($livewire, $record) {
                                    $user = Auth::user();
                                    return strtolower($record->estado_pago) === 'pendiente' &&
                                        $user->hasAnyRole(['super_admin', 'Jefe de operaciones']);
                                })
                                ->action(function ($livewire, $record) {
                                    $record->rechazar();
                                    \Filament\Notifications\Notification::make()
                                        ->title('Pago rechazado correctamente')
                                        ->danger()
                                        ->send();
                                    $livewire->dispatch('closeEditModal');
                                }),
                        ])->columnSpanFull(),

                        \Filament\Forms\Components\Section::make('Información de la Cuota')
                            ->description('Datos de la cuota y saldos')
                            ->icon('heroicon-o-information-circle')
                            ->schema([
                                \Filament\Forms\Components\Grid::make(3)
                                    ->schema([
                                        \Filament\Forms\Components\Select::make('grupo_id')
                                            ->label('Grupo')
                                            ->prefixIcon('heroicon-o-user-group')
                                            ->options(function ($record) {
                                                if ($record && $record->cuotaGrupal && $record->cuotaGrupal->prestamo && $record->cuotaGrupal->prestamo->grupo) {
                                                    return [$record->cuotaGrupal->prestamo->grupo->id => $record->cuotaGrupal->prestamo->grupo->nombre_grupo];
                                                }
                                                return [];
                                            })
                                            ->disabled()
                                            ->dehydrated(false),

                                        \Filament\Forms\Components\TextInput::make('numero_cuota')
                                            ->label('N° Cuota')
                                            ->prefixIcon('heroicon-o-hashtag')
                                            ->disabled()
                                            ->dehydrated(false),

                                        \Filament\Forms\Components\TextInput::make('monto_cuota')
                                            ->label('Monto Cuota')
                                            ->prefix('S/.')
                                            ->prefixIcon('heroicon-o-banknotes')
                                            ->disabled()
                                            ->dehydrated(false),
                                    ]),

                                \Filament\Forms\Components\Grid::make(2)
                                    ->schema([
                                        \Filament\Forms\Components\TextInput::make('monto_mora_pagada')
                                            ->label('Mora Aplicada')
                                            ->prefix('S/.')
                                            ->prefixIcon('heroicon-o-exclamation-triangle')
                                            ->disabled()
                                            ->dehydrated(false),

                                        \Filament\Forms\Components\TextInput::make('saldo_pendiente_actual')
                                            ->label('Saldo Pendiente')
                                            ->prefix('S/.')
                                            ->prefixIcon('heroicon-o-clock')
                                            ->disabled()
                                            ->dehydrated(false)
                                            ->extraAttributes(['class' => 'font-bold text-red-600'])
                                            ->helperText('💡 Saldo que queda por pagar de esta cuota')
                                            ->afterStateHydrated(function ($component, $state, $record) {
                                                if ($record && $record->cuotaGrupal) {
                                                    $cuota = $record->cuotaGrupal->fresh();
                                                    $montoCuota = floatval($cuota->monto_cuota_grupal);
                                                    $montoMora = $cuota->mora ? abs($cuota->mora->monto_mora_calculado) : 0;
                                                    $pagosAprobados = $cuota->pagos()
                                                        ->where('estado_pago', 'aprobado')
                                                        ->sum('monto_pagado');
                                                    $saldoPendiente = max(($montoCuota + $montoMora) - $pagosAprobados, 0);
                                                    $component->state($saldoPendiente);
                                                } else {
                                                    $component->state(0);
                                                }
                                            }),
                                    ]),
                            ])
                            ->collapsible()
                            ->collapsed(false),

                        \Filament\Forms\Components\Section::make('Detalles del Pago')
                            ->description(function ($record) {

                                return strtolower($record->estado_pago) === 'pendiente'
                                    ? 'Información del pago a editar'
                                    : 'Información del pago (Solo lectura)';
                            })
                            ->icon('heroicon-o-credit-card')
                            ->schema([
                                \Filament\Forms\Components\Grid::make(2)
                                    ->schema([
                                        \Filament\Forms\Components\Select::make('tipo_pago')
                                            ->label('Tipo de Pago')
                                            ->prefixIcon('heroicon-o-adjustments-horizontal')
                                            ->options([
                                                'pago_completo' => '💰 Pago Completo',
                                                'pago_parcial' => '📊 Pago Parcial',
                                            ])
                                            ->required()
                                            ->reactive()

                                            ->disabled(function ($record) {
                                                $user = Auth::user();
                                                $esPendiente = strtolower($record->estado_pago) === 'pendiente';
                                                $esAsesor = $user->hasRole('Asesor');
                                                return !($esPendiente && $esAsesor);
                                            })
                                            ->afterStateUpdated(function ($state, callable $set, callable $get, $record) {
                                                if (!$record || !$record->cuotaGrupal) return;

                                                $cuota = $record->cuotaGrupal;
                                                $montoCuota = floatval($cuota->monto_cuota_grupal);
                                                $montoMora = $cuota->mora ? abs($cuota->mora->monto_mora_calculado) : 0;

                                                $pagosAprobados = $cuota->pagos()
                                                    ->where('estado_pago', 'aprobado')
                                                    ->where('id', '!=', $record->id)
                                                    ->sum('monto_pagado');

                                                $saldoPendiente = max(($montoCuota + $montoMora) - $pagosAprobados, 0);

                                                if ($state === 'pago_completo') {
                                                    $set('monto_pagado', $saldoPendiente);
                                                } elseif ($state === 'pago_parcial') {
                                                    $set('monto_pagado', null);
                                                }
                                            }),

                                        \Filament\Forms\Components\TextInput::make('monto_pagado')
                                            ->label('Monto a Pagar')
                                            ->prefix('S/.')
                                            ->prefixIcon('heroicon-o-currency-dollar')
                                            ->numeric()
                                            ->required()
                                            ->minValue(0.01)
                                            ->disabled(function (callable $get, $record) {
                                                $user = Auth::user();
                                                $esPendiente = strtolower($record->estado_pago) === 'pendiente';
                                                $esAsesor = $user->hasRole('Asesor');
                                                return !($esPendiente && $esAsesor) || $get('tipo_pago') === 'pago_completo';
                                            })
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function ($state, callable $set, callable $get, $record) {
                                                if (!$record || !$record->cuotaGrupal || $get('tipo_pago') === 'pago_completo') return;

                                                $cuota = $record->cuotaGrupal;
                                                $montoCuota = floatval($cuota->monto_cuota_grupal);
                                                $montoMora = $cuota->mora ? abs($cuota->mora->monto_mora_calculado) : 0;

                                                $pagosAprobados = $cuota->pagos()
                                                    ->where('estado_pago', 'aprobado')
                                                    ->where('id', '!=', $record->id)
                                                    ->sum('monto_pagado');

                                                $saldoPendiente = max(($montoCuota + $montoMora) - $pagosAprobados, 0);
                                                $montoPagado = floatval($state ?? 0);

                                                if ($montoPagado > $saldoPendiente && $saldoPendiente > 0) {
                                                    $set('monto_pagado', $saldoPendiente);
                                                }
                                            })
                                            ->helperText(function (callable $get, $record) {
                                                if (!$record || !$record->cuotaGrupal) return null;

                                                $cuota = $record->cuotaGrupal;
                                                $montoCuota = floatval($cuota->monto_cuota_grupal);
                                                $montoMora = $cuota->mora ? abs($cuota->mora->monto_mora_calculado) : 0;

                                                $pagosAprobados = $cuota->pagos()
                                                    ->where('estado_pago', 'aprobado')
                                                    ->where('id', '!=', $record->id)
                                                    ->sum('monto_pagado');

                                                $saldoPendiente = max(($montoCuota + $montoMora) - $pagosAprobados, 0);

                                                if ($saldoPendiente > 0 && strtolower($record->estado_pago) === 'pendiente') {
                                                    return '💡 Máximo: S/. ' . number_format($saldoPendiente, 2);
                                                }
                                                return null;
                                            })
                                            ->rules([
                                                function (callable $get, $record) {
                                                    return function (string $attribute, $value, \Closure $fail) use ($get, $record) {
                                                        if (!$record || !$record->cuotaGrupal) return;

                                                        $cuota = $record->cuotaGrupal;
                                                        $montoCuota = floatval($cuota->monto_cuota_grupal);
                                                        $montoMora = $cuota->mora ? abs($cuota->mora->monto_mora_calculado) : 0;

                                                        $pagosAprobados = $cuota->pagos()
                                                            ->where('estado_pago', 'aprobado')
                                                            ->where('id', '!=', $record->id)
                                                            ->sum('monto_pagado');

                                                        $saldoPendiente = max(($montoCuota + $montoMora) - $pagosAprobados, 0);

                                                        if (floatval($value) > $saldoPendiente) {
                                                            $fail("El monto no puede ser mayor al saldo pendiente (S/. " . number_format($saldoPendiente, 2) . ")");
                                                        }

                                                        if (floatval($value) <= 0) {
                                                            $fail("El monto debe ser mayor a 0");
                                                        }
                                                    };
                                                },
                                            ]),
                                    ]),

                                \Filament\Forms\Components\Grid::make(2)
                                    ->schema([
                                        \Filament\Forms\Components\TextInput::make('codigo_operacion')
                                            ->label('Código de Operación')
                                            ->prefixIcon('heroicon-o-qr-code')
                                            ->required()
                                            ->maxLength(255)
                                            ->placeholder('Ej: OP-12345678')

                                            ->disabled(function ($record) {
                                                $user = Auth::user();
                                                $esPendiente = strtolower($record->estado_pago) === 'pendiente';
                                                $esAsesor = $user->hasRole('Asesor');
                                                return !($esPendiente && $esAsesor);
                                            }),

                                        \Filament\Forms\Components\DateTimePicker::make('fecha_pago')
                                            ->label('Fecha del Pago')
                                            ->prefixIcon('heroicon-o-calendar-days')
                                            ->required()
                                            ->default(now())
                                            ->displayFormat('d/m/Y H:i')
                                            ->seconds(false)

                                            ->disabled(function ($record) {
                                                $user = Auth::user();
                                                $esPendiente = strtolower($record->estado_pago) === 'pendiente';
                                                $esAsesor = $user->hasRole('Asesor');
                                                return !($esPendiente && $esAsesor);
                                            }),
                                    ]),

                                \Filament\Forms\Components\Textarea::make('observaciones')
                                    ->label('💬 Observaciones')
                                    ->maxLength(500)
                                    ->rows(2)
                                    ->placeholder('Agregar observaciones adicionales (opcional)...')

                                    ->disabled(function ($record) {
                                        $user = Auth::user();
                                        $esPendiente = strtolower($record->estado_pago) === 'pendiente';
                                        $esAsesor = $user->hasRole('Asesor');
                                        return !($esPendiente && $esAsesor);
                                    }),

                                \Filament\Forms\Components\TextInput::make('estado_pago')
                                    ->label('Estado Actual')
                                    ->prefixIcon('heroicon-o-flag')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->visible(function ($record) {
                                        return strtolower($record->estado_pago) !== 'pendiente';
                                    }),

                            ])
                           // ->collapsible()
                            ->collapsed(false),
                    ])
                    ->mutateRecordDataUsing(function (array $data, $record): array {
                        $data['grupo_id'] = $record->cuotaGrupal?->prestamo?->grupo?->id;
                        $data['numero_cuota'] = $record->cuotaGrupal?->numero_cuota;
                        $data['monto_cuota'] = $record->cuotaGrupal?->monto_cuota_grupal;
                        $data['monto_mora_pagada'] = $record->cuotaGrupal && $record->cuotaGrupal->mora
                            ? abs($record->cuotaGrupal->mora->monto_mora_calculado)
                            : 0;


                        if ($record->cuotaGrupal) {
                            $cuota = $record->cuotaGrupal;
                            $montoCuota = floatval($cuota->monto_cuota_grupal);
                            $montoMora = $cuota->mora ? abs($cuota->mora->monto_mora_calculado) : 0;

                            $pagosAprobados = $cuota->pagos()
                                ->where('estado_pago', 'aprobado')
                                ->where('id', '!=', $record->id)
                                ->sum('monto_pagado');

                            $data['saldo_pendiente_actual'] = max(($montoCuota + $montoMora) - $pagosAprobados, 0);
                        } else {
                            $data['saldo_pendiente_actual'] = 0;
                        }

                        return $data;
                    })

                    ->visible(function ($record) {
                        $user = Auth::user();

                        // Super admin y jefes pueden ver todos los pagos
                        if ($user->hasAnyRole(['super_admin', 'Jefe de operaciones'])) {
                            return true;
                        }

                        // Asesores pueden ver y editar sus propios pagos
                        if ($user->hasRole('Asesor')) {
                            $asesor = \App\Models\Asesor::where('user_id', $user->id)->first();
                            $grupo = $record->cuotaGrupal?->prestamo?->grupo;
                            return $asesor && $grupo && $grupo->asesor_id === $asesor->id;
                        }

                        return false;
                    })

                    ->action(function ($record, array $data) {
                        $user = Auth::user();
                        $esPendiente = strtolower($record->estado_pago) === 'pendiente';
                        $esAsesor = $user->hasRole('Asesor');

                        if ($esPendiente && $esAsesor) {
                            $record->update($data);
                            Notification::make()
                                ->title('Pago actualizado correctamente')
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Acción no permitida')
                                ->body('Solo los asesores pueden editar pagos pendientes.')
                                ->warning()
                                ->send();
                        }
                    }),

                Tables\Actions\Action::make('aprobar')
                    ->label('Aprobar')
                    ->icon('heroicon-m-check-circle')
                    ->color('success')
                    ->size('sm')
                    ->visible(function ($record) {
                        $user = Auth::user();
                        return strtolower($record->estado_pago) === 'pendiente' &&
                            $user->hasAnyRole(['super_admin', 'Jefe de operaciones']);
                    })
                    ->action(function ($record) {
                        $record->aprobar();
                        Notification::make()
                            ->title('Pago aprobado correctamente')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('rechazar')
                    ->label('Rechazar')
                    ->icon('heroicon-m-x-circle')
                    ->color('danger')
                    ->size('sm')
                    ->visible(function ($record) {
                        $user = Auth::user();
                        return strtolower($record->estado_pago) === 'pendiente' &&
                            $user->hasAnyRole(['super_admin', 'Jefe de operaciones']);
                    })
                    ->action(function ($record) {
                        $record->rechazar();
                        Notification::make()
                            ->title('Pago rechazado')
                            ->danger()
                            ->send();
                    }),
            ]),
        ])
        ->bulkActions([
            Tables\Actions\BulkActionGroup::make([
                Tables\Actions\BulkAction::make('aprobar_masivo')
                    ->label('Aprobar Seleccionados')
                    ->icon('heroicon-m-check-circle')
                    ->color('success')
                    ->visible(function () {
                        $user = Auth::user();
                        return $user->hasAnyRole(['super_admin', 'Jefe de operaciones']);
                    })
                    ->action(function ($records) {
                        $aprobados = 0;
                        foreach ($records as $record) {
                            if (strtolower($record->estado_pago) === 'pendiente') {
                                $record->aprobar();
                                $aprobados++;
                            }
                        }

                        Notification::make()
                            ->title("Se aprobaron {$aprobados} pagos")
                            ->success()
                            ->send();
                    }),
            ]),
        ])
        ->defaultSort('created_at', 'desc')
        ->striped()
        ->paginated([10, 25, 50]);
}

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('volver')
                ->label('Volver a Pagos')
                ->icon('heroicon-m-arrow-left')
                ->color('gray')
                ->url(PagoResource::getUrl('index')),

            Actions\Action::make('crear_pago')
                ->label('Nuevo Pago')
                ->icon('heroicon-m-plus')
                ->color('primary')
                ->url(PagoResource::getUrl('create'))
                ->visible(function () {
                    $user = Auth::user();
                    return $user->hasRole('Asesor');
                }),
        ];
    }

    protected $listeners = ['closeEditModal' => 'closeEditActionModal'];

public function closeEditActionModal()
{
    $this->dispatch('closeEditAction');
}

    public function getTitle(): string
    {
        return "Pagos del Grupo: {$this->grupo->nombre_grupo}";
    }

    public function getBreadcrumbs(): array
    {
        return [
            PagoResource::getUrl('index') => 'Pagos',
            '' => "Grupo: {$this->grupo->nombre_grupo}",
        ];
    }
}
