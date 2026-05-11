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
use Exception;
use App\Models\Prestamo;
use App\Services\PagoService;

class GrupoDetallePagos extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = PagoResource::class;
    protected static string $view = 'filament.dashboard.pages.grupo-detalle-pagos';

    public Grupo $grupo;
    public Prestamo $prestamo;

    public function mount(Grupo|int $grupo, Prestamo|int $prestamo): void
    {
        if ($grupo instanceof Grupo) {
            $this->grupo = $grupo;
        } else {
            $this->grupo = Grupo::findOrFail($grupo);
        }
        if ($prestamo instanceof Prestamo) {
            $this->prestamo = $prestamo;
        } else {
            $this->prestamo = Prestamo::where('id', $prestamo)->where('grupo_id', $this->grupo->id)->firstOrFail();
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
            ->whereHas('cuotaGrupal', function ($query) {
                $query->where('prestamo_id', $this->prestamo->id);
            })
            ->with([
                'cuotaGrupal.prestamo.grupo',
                'cuotaGrupal.mora',
                'aplicacionesPago.cuota.cliente.persona',
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

            Tables\Columns\TextColumn::make('monto_mora_pagada')
                ->label('Mora Pagada')
                ->money('PEN')
                ->alignRight(),

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
                    'aprobado' => 'Aprobado',
                    'Pendiente' => 'Pendiente',
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
                                    try {
                                        app(PagoService::class)->aprobarPago($record);
                                        \Filament\Notifications\Notification::make()
                                            ->title('Pago aprobado correctamente')
                                            ->success()
                                            ->send();
                                    } catch (\Exception $e) {
                                        \Filament\Notifications\Notification::make()
                                            ->title('Error al aprobar')
                                            ->body($e->getMessage())
                                            ->danger()
                                            ->send();
                                    }
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
                                    try {
                                        app(PagoService::class)->rechazarPago($record);
                                        \Filament\Notifications\Notification::make()
                                            ->title('Pago rechazado correctamente')
                                            ->danger()
                                            ->send();
                                    } catch (\Exception $e) {
                                        \Filament\Notifications\Notification::make()
                                            ->title('Error al rechazar')
                                            ->body($e->getMessage())
                                            ->danger()
                                            ->send();
                                    }
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
                                                    // Poblar detallesPago igual que en PagoResource
                                                    if ($record->cuotaGrupal && $record->cuotaGrupal->prestamo) {
                                                        $prestamoId = $record->cuotaGrupal->prestamo->id;
                                                        $integrantes = \App\Models\PrestamoIndividual::where('prestamo_id', $prestamoId)->with('cliente.persona')->get();
                                                        $detalles = $integrantes->map(function($pi) {
                                                            $persona = optional($pi->cliente->persona);
                                                            $nombre = trim(($persona->nombre ?? '') . ' ' . ($persona->apellidos ?? '')) ?: 'Sin nombre';
                                                            return [
                                                                'prestamo_individual_id' => $pi->id,
                                                                'nombre_integrante' => $nombre,
                                                                'monto_pagado' => $pi->monto_cuota_prestamo_individual,
                                                            ];
                                                        })->toArray();
                                                        $set('detallesPago', $detalles);
                                                    }
                                                } elseif ($state === 'pago_parcial') {
                                                    $set('monto_pagado', null);
                                                    // Limpiar los montos de los integrantes
                                                    $detalles = $get('detallesPago') ?? [];
                                                    $detallesLimpios = collect($detalles)->map(function($detalle) {
                                                        return [
                                                            'prestamo_individual_id' => $detalle['prestamo_individual_id'] ?? null,
                                                            'nombre_integrante' => $detalle['nombre_integrante'] ?? 'Sin nombre',
                                                            'monto_pagado' => null,
                                                        ];
                                                    })->toArray();
                                                    $set('detallesPago', $detallesLimpios);
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

                                            // Si no es pendiente o no es asesor, deshabilitar
                                            if (!($esPendiente && $esAsesor)) {
                                                return true;
                                            }

                                            // Si es pago completo o pago parcial, deshabilitar (se calculará automáticamente)
                                            return in_array($get('tipo_pago'), ['pago_completo', 'pago_parcial']);
                                        })
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(function ($state, callable $set, callable $get, $record) {
                                            if (!$record || !$record->cuotaGrupal || in_array($get('tipo_pago'), ['pago_completo', 'pago_parcial'])) return;

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

                                            $tipoPago = $get('tipo_pago');

                                            if ($tipoPago === 'pago_parcial') {
                                                return '💡 Este campo se calculará automáticamente sumando los montos individuales';
                                            }

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

                        // Sección de detalle por integrante (solo lectura)
                        \Filament\Forms\Components\Section::make('Detalle por integrante')
                            ->description('Detalle de pago por cada integrante registrado en este pago')
                            ->icon('heroicon-o-users')
                            ->schema([
                               \Filament\Forms\Components\Repeater::make('detallesPago')
                                ->label('Integrantes')
                                ->schema([
                                    \Filament\Forms\Components\Placeholder::make('nombre_integrante')
                                        ->label('Integrante')
                                        ->content(function ($record, callable $get) {
                                            // Si existe el campo nombre_integrante en el array, úsalo
                                            $nombre = $get('nombre_integrante');
                                            if ($nombre && $nombre !== 'Sin nombre') {
                                                return $nombre;
                                            }
                                            // Si no, buscar por la relación
                                            if ($record && $record->prestamoIndividual) {
                                                $persona = optional($record->prestamoIndividual->cliente->persona);
                                                return trim(($persona->nombre ?? '') . ' ' . ($persona->apellidos ?? '')) ?: 'Sin nombre';
                                            }
                                            return 'Sin nombre';
                                        }),

                                    \Filament\Forms\Components\TextInput::make('monto_pagado')
                                        ->label('Monto Pagado')
                                        ->prefix('S/.')
                                        ->numeric()
                                        ->minValue(0)
                                        ->step(0.01)
                                        ->dehydrated(true)
                                        ->disabled(function ($record, callable $get) {
                                            // Obtener el record del pago principal usando la ruta correcta
                                            $pagoRecord = null;

                                            // Intentar diferentes formas de obtener el record principal
                                            if (method_exists($get, '__invoke')) {
                                                $pagoRecord = $get('../../');
                                            } else {
                                                // Si no funciona, intentar obtener desde el contexto
                                                $pagoRecord = $record;
                                            }

                                            if (!$pagoRecord) return true;

                                            $user = Auth::user();
                                            $esPendiente = false;
                                            $tipoPago = '';

                                            // Manejar diferentes tipos de datos del record
                                            if (is_array($pagoRecord)) {
                                                $esPendiente = strtolower($pagoRecord['estado_pago'] ?? '') === 'pendiente';
                                                $tipoPago = $pagoRecord['tipo_pago'] ?? '';
                                            } elseif (is_object($pagoRecord)) {
                                                $esPendiente = strtolower($pagoRecord->estado_pago ?? '') === 'pendiente';
                                                $tipoPago = $pagoRecord->tipo_pago ?? '';
                                            }

                                            $esAsesor = $user->hasRole('Asesor');

                                            // Si no es pendiente o no es asesor, deshabilitar
                                            if (!($esPendiente && $esAsesor)) {
                                                return true;
                                            }

                                            // Solo habilitar si es pago parcial
                                            return $tipoPago !== 'pago_parcial';
                                        })
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                            // Intentar obtener el tipo de pago desde diferentes rutas
                                            $tipoPago = '';
                                            try {
                                                $tipoPago = $get('../../tipo_pago') ?? '';
                                            } catch (Exception $e) {
                                                // Si falla, intentar otra ruta
                                                $tipoPago = $get('../../../tipo_pago') ?? '';
                                            }

                                            // Solo calcular suma si es pago parcial
                                            if ($tipoPago !== 'pago_parcial') {
                                                return;
                                            }

                                            // Obtener todos los detalles de pago
                                            try {
                                                $detallesPago = $get('../../detallesPago') ?? [];
                                            } catch (Exception $e) {
                                                $detallesPago = $get('../../../detallesPago') ?? [];
                                            }

                                            $sumaTotal = 0;

                                            // Sumar todos los montos individuales
                                            if (is_array($detallesPago)) {
                                                foreach ($detallesPago as $detalle) {
                                                    if (isset($detalle['monto_pagado']) && is_numeric($detalle['monto_pagado'])) {
                                                        $sumaTotal += floatval($detalle['monto_pagado']);
                                                    }
                                                }
                                            }

                                            // Actualizar el monto total pagado
                                            try {
                                                $set('../../monto_pagado', $sumaTotal);
                                            } catch (Exception $e) {
                                                try {
                                                    $set('../../../monto_pagado', $sumaTotal);
                                                } catch (Exception $e2) {
                                                    // Si no se puede establecer, al menos mostrar una notificación
                                                    \Filament\Notifications\Notification::make()
                                                        ->title('Suma calculada')
                                                        ->body('Total: S/. ' . number_format($sumaTotal, 2))
                                                        ->info()
                                                        ->send();
                                                }
                                            }

                                            // Validar que no exceda el saldo pendiente (opcional, ya que se valida en el action)
                                            // Esta validación se puede omitir aquí para evitar errores y dejarla solo en la validación del formulario
                                        })
                                        ->placeholder(function (callable $get) {
                                            $tipoPago = '';
                                            try {
                                                $tipoPago = $get('../../tipo_pago') ?? '';
                                            } catch (Exception $e) {
                                                $tipoPago = '';
                                            }
                                            return $tipoPago === 'pago_parcial' ? 'Ingrese el monto pagado' : '';
                                        })
                                        ->helperText(function (callable $get) {
                                            $tipoPago = '';
                                            try {
                                                $tipoPago = $get('../../tipo_pago') ?? '';
                                            } catch (Exception $e) {
                                                $tipoPago = '';
                                            }
                                            if ($tipoPago === 'pago_parcial') {
                                                return 'Ingrese el monto pagado por este integrante';
                                            }
                                            return null;
                                        })
                                        ->rules(['numeric', 'min:0'])
                                        ->extraAttributes(function ($record, callable $get) {
                                            $tipoPago = '';
                                            try {
                                                $tipoPago = $get('../../tipo_pago') ?? '';
                                            } catch (Exception $e) {
                                                $tipoPago = '';
                                            }

                                            $pagoRecord = null;
                                            if (method_exists($get, '__invoke')) {
                                                try {
                                                    $pagoRecord = $get('../../');
                                                } catch (Exception $e) {
                                                    $pagoRecord = $record;
                                                }
                                            }

                                            $esPendiente = false;
                                            if (is_array($pagoRecord)) {
                                                $esPendiente = strtolower($pagoRecord['estado_pago'] ?? '') === 'pendiente';
                                            } elseif (is_object($pagoRecord)) {
                                                $esPendiente = strtolower($pagoRecord->estado_pago ?? '') === 'pendiente';
                                            }

                                            if ($esPendiente && $tipoPago === 'pago_parcial') {
                                                return [
                                                    'oninput' => 'this.value = this.value.replace(/[^0-9.]/g, "")',
                                                    'onkeypress' => 'return (event.charCode >= 48 && event.charCode <= 57) || event.charCode == 46'
                                                ];
                                            }
                                            return [];
                                        })
                                ])
                                ->minItems(0)
                                ->maxItems(50)
                                ->grid(3)
                                ->addable(false)
                                ->deletable(false)
                                ->reorderable(false)
                                ->visible(function ($record, callable $get) {
                                    // Mostrar si hay detalles en la relación o en el array seteado manualmente
                                    $detalles = $get('detallesPago');
                                    if (is_array($detalles) && count($detalles) > 0) {
                                        return true;
                                    }
                                    return $record && $record->detallesPago && $record->detallesPago->count() > 0;
                                }),
                            ])
                            ->collapsible()
                            ->collapsed(false),
                    ])
                ->mutateRecordDataUsing(function (array $data, $record): array {
                    // Cargar todas las relaciones necesarias
                    $record->load([
                        'detallesPago.prestamoIndividual.cliente.persona',
                        'cuotaGrupal.prestamo.grupo',
                        'cuotaGrupal.mora'
                    ]);

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

                    // Mapear integrantes desde AplicacionPago (V2) → CuotaIndividual → Cliente
                    $aplicaciones = $record->aplicacionesPago;
                    $data['detallesPago'] = [];

                    if ($aplicaciones->isNotEmpty()) {
                        // Hay aplicaciones: mostrar lo que ya se distribuyó
                        $data['detallesPago'] = $aplicaciones->map(function ($ap) {
                            $persona = optional($ap->cuota?->cliente?->persona);
                            $nombre  = trim(($persona->nombre ?? '') . ' ' . ($persona->apellidos ?? '')) ?: 'Sin nombre';
                            return [
                                'cuota_individual_id' => $ap->cuota_id,
                                'nombre_integrante'   => $nombre,
                                'monto_capital'       => (float) $ap->monto_aplicado_capital,
                                'monto_interes'       => (float) $ap->monto_aplicado_interes,
                                'monto_mora'          => (float) $ap->monto_aplicado_mora,
                                'monto_pagado'        => round(
                                    (float) $ap->monto_aplicado_capital +
                                    (float) $ap->monto_aplicado_interes +
                                    (float) $ap->monto_aplicado_mora,
                                    2
                                ),
                            ];
                        })->toArray();
                    } else {
                        // Pago pendiente: mostrar integrantes del préstamo con monto 0
                        if ($record->cuotaGrupal && $record->cuotaGrupal->prestamo) {
                            $prestamoId = $record->cuotaGrupal->prestamo->id;
                            $pis = \App\Models\PrestamoIndividual::where('prestamo_id', $prestamoId)
                                ->with('cliente.persona')
                                ->get();

                            $data['detallesPago'] = $pis->map(function ($pi) {
                                $persona = optional($pi->cliente->persona);
                                $nombre  = trim(($persona->nombre ?? '') . ' ' . ($persona->apellidos ?? '')) ?: 'Sin nombre';
                                return [
                                    'cuota_individual_id' => null,
                                    'nombre_integrante'   => $nombre,
                                    'monto_capital'       => round($pi->monto_prestado_individual / ($pi->prestamo->cantidad_cuotas ?? 4), 2),
                                    'monto_interes'       => round($pi->interes / ($pi->prestamo->cantidad_cuotas ?? 4), 2),
                                    'monto_mora'          => 0,
                                    'monto_pagado'        => (float) $pi->monto_cuota_prestamo_individual,
                                ];
                            })->toArray();
                        }
                    }

                    return $data;
                })

                    ->visible(function ($record) {
                        $user = Auth::user();

                        // Super admin y jefes pueden ver todos los pagos
                        if ($user->hasAnyRole(['super_admin', 'Jefe de operaciones', 'Jefe de creditos'])) {
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
                            // Pago pendiente — el asesor edita los datos base del pago.
                            // La distribución en AplicacionPago la hace el PagoService al aprobar.
                            $nuevoMontoPagado = 0;
                            if (isset($data['detallesPago']) && is_array($data['detallesPago'])) {
                                foreach ($data['detallesPago'] as $detalle) {
                                    $nuevoMontoPagado += floatval($detalle['monto_pagado'] ?? 0);
                                }
                            }

                            $record->tipo_pago        = $data['tipo_pago'] ?? $record->tipo_pago;
                            $record->codigo_operacion = $data['codigo_operacion'] ?? $record->codigo_operacion;
                            $record->fecha_pago       = $data['fecha_pago'] ?? $record->fecha_pago;
                            $record->observaciones    = $data['observaciones'] ?? $record->observaciones;
                            $record->monto_pagado     = $nuevoMontoPagado > 0 ? $nuevoMontoPagado : ($data['monto_pagado'] ?? $record->monto_pagado);
                            $record->save();

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
                        try {
                            app(PagoService::class)->aprobarPago($record);
                            Notification::make()
                                ->title('Pago aprobado correctamente')
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Error al aprobar')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
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
                        try {
                            app(PagoService::class)->rechazarPago($record);
                            Notification::make()
                                ->title('Pago rechazado')
                                ->danger()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Error al rechazar')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
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
                                try {
                                    app(PagoService::class)->aprobarPago($record);
                                    $aprobados++;
                                } catch (\Exception $e) {
                                    // Continuar con el siguiente si falla uno
                                }
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
                ->url(fn() => PagoResource::getUrl('create', ['cuota_grupal_id' => $this->getCuotaGrupalIdVigente()]))
                ->visible(function () {
                    $user = Auth::user();
                    return $user->hasRole('Asesor');
                }),
        ];
    }

    /**
     * Devuelve el id de la cuota grupal vigente o próxima para el grupo/prestamo actual
     */
    protected function getCuotaGrupalIdVigente()
    {
        $cuota = $this->prestamo->cuotasGrupales()
            ->whereIn('estado_cuota_grupal', ['vigente', 'mora'])
            ->orderBy('numero_cuota')
            ->first();
        if (!$cuota) {
            $cuota = $this->prestamo->cuotasGrupales()
                ->where('estado_cuota_grupal', 'pendiente')
                ->orderBy('numero_cuota')
                ->first();
        }
        return $cuota ? $cuota->id : null;
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
            '' => "Grupo: {$this->grupo->nombre_grupo} ",
        ];
    }
}
