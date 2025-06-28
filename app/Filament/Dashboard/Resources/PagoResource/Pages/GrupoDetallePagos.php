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

    // Cambiar el tipo de parámetro para aceptar tanto int como Grupo
    public function mount(int|Grupo $grupo): void
    {
        // Si recibimos un ID (int), buscamos el grupo
        if (is_int($grupo)) {
            $this->grupo = Grupo::findOrFail($grupo);
        } else {
            // Si recibimos el objeto Grupo directamente
            $this->grupo = $grupo;
        }

        // Verificar permisos
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
            ->columns([
                Tables\Columns\TextColumn::make('cuotaGrupal.numero_cuota')
                    ->label('Cuota')
                    ->sortable()
                    ->alignCenter()
                    ->badge()
                    ->color('primary'),

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
                Tables\Actions\EditAction::make()
                    ->form([
                        \Filament\Forms\Components\Select::make('grupo_id')
                            ->label('Grupo')
                            ->options(function ($record) {
                                if ($record && $record->cuotaGrupal && $record->cuotaGrupal->prestamo && $record->cuotaGrupal->prestamo->grupo) {
                                    return [$record->cuotaGrupal->prestamo->grupo->id => $record->cuotaGrupal->prestamo->grupo->nombre_grupo];
                                }
                                return [];
                            })
                            ->disabled()
                            ->dehydrated(false),

                        \Filament\Forms\Components\TextInput::make('numero_cuota')
                            ->label('Número de Cuota')
                            ->disabled()
                            ->dehydrated(false),

                        \Filament\Forms\Components\TextInput::make('monto_cuota')
                            ->label('Monto de la Cuota')
                            ->prefix('S/.')
                            ->disabled()
                            ->dehydrated(false),

                        \Filament\Forms\Components\TextInput::make('monto_mora_pagada')
                            ->label('Monto de Mora')
                            ->prefix('S/.')
                            ->disabled()
                            ->dehydrated(false),

                        \Filament\Forms\Components\TextInput::make('saldo_pendiente_actual')
                            ->label('Saldo Pendiente')
                            ->prefix('S/.')
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText('Este es el saldo que queda por pagar de esta cuota'),

                        \Filament\Forms\Components\Select::make('tipo_pago')
                            ->label('Tipo de Pago')
                            ->options([
                                'pago_completo' => 'Pago Completo',
                                'pago_parcial' => 'Pago Parcial',
                            ])
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $set, callable $get, $record) {
                                if (!$record || !$record->cuotaGrupal) return;
                                
                                $cuota = $record->cuotaGrupal;
                                $montoCuota = floatval($cuota->monto_cuota_grupal);
                                $montoMora = $cuota->mora ? abs($cuota->mora->monto_mora_calculado) : 0;
                                
                                // Calcular pagos aprobados existentes (excluyendo el pago actual que se está editando)
                                $pagosAprobados = $cuota->pagos()
                                    ->where('estado_pago', 'aprobado')
                                    ->where('id', '!=', $record->id)
                                    ->sum('monto_pagado');
                                
                                $saldoPendiente = max(($montoCuota + $montoMora) - $pagosAprobados, 0);
                                
                                if ($state === 'pago_completo') {
                                    $set('monto_pagado', $saldoPendiente);
                                } elseif ($state === 'pago_parcial') {
                                    // En pago parcial, limpiar el monto para que el usuario lo ingrese
                                    $set('monto_pagado', null);
                                }
                            }),

                        \Filament\Forms\Components\TextInput::make('monto_pagado')
                            ->label('Monto Pagado')
                            ->prefix('S/.')
                            ->numeric()
                            ->required()
                            ->minValue(0.01)
                            ->disabled(function (callable $get) {
                                return $get('tipo_pago') === 'pago_completo';
                            })
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, callable $set, callable $get, $record) {
                                if (!$record || !$record->cuotaGrupal || $get('tipo_pago') === 'pago_completo') return;
                                
                                $cuota = $record->cuotaGrupal;
                                $montoCuota = floatval($cuota->monto_cuota_grupal);
                                $montoMora = $cuota->mora ? abs($cuota->mora->monto_mora_calculado) : 0;
                                
                                // Calcular pagos aprobados existentes (excluyendo el pago actual)
                                $pagosAprobados = $cuota->pagos()
                                    ->where('estado_pago', 'aprobado')
                                    ->where('id', '!=', $record->id)
                                    ->sum('monto_pagado');
                                
                                $saldoPendiente = max(($montoCuota + $montoMora) - $pagosAprobados, 0);
                                $montoPagado = floatval($state ?? 0);

                                // Validar que no exceda el saldo pendiente
                                if ($montoPagado > $saldoPendiente && $saldoPendiente > 0) {
                                    $set('monto_pagado', $saldoPendiente);
                                }
                            })
                            ->helperText(function (callable $get, $record) {
                                if (!$record || !$record->cuotaGrupal) return null;
                                
                                $cuota = $record->cuotaGrupal;
                                $montoCuota = floatval($cuota->monto_cuota_grupal);
                                $montoMora = $cuota->mora ? abs($cuota->mora->monto_mora_calculado) : 0;
                                
                                // Calcular pagos aprobados existentes (excluyendo el pago actual)
                                $pagosAprobados = $cuota->pagos()
                                    ->where('estado_pago', 'aprobado')
                                    ->where('id', '!=', $record->id)
                                    ->sum('monto_pagado');
                                
                                $saldoPendiente = max(($montoCuota + $montoMora) - $pagosAprobados, 0);
                                
                                if ($saldoPendiente > 0) {
                                    return 'Máximo a pagar: S/. ' . number_format($saldoPendiente, 2);
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
                                        
                                        // Calcular pagos aprobados existentes (excluyendo el pago actual)
                                        $pagosAprobados = $cuota->pagos()
                                            ->where('estado_pago', 'aprobado')
                                            ->where('id', '!=', $record->id)
                                            ->sum('monto_pagado');
                                        
                                        $saldoPendiente = max(($montoCuota + $montoMora) - $pagosAprobados, 0);
                                        
                                        if (floatval($value) > $saldoPendiente) {
                                            $fail("El monto pagado no puede ser mayor al saldo pendiente (S/. " . number_format($saldoPendiente, 2) . ")");
                                        }
                                        
                                        if (floatval($value) <= 0) {
                                            $fail("El monto pagado debe ser mayor a 0");
                                        }
                                    };
                                },
                            ]),

                        \Filament\Forms\Components\TextInput::make('codigo_operacion')
                            ->label('Código de Operación')
                            ->required()
                            ->maxLength(255),

                        \Filament\Forms\Components\DateTimePicker::make('fecha_pago')
                            ->label('Fecha del Pago')
                            ->required()
                            ->default(now()),

                        \Filament\Forms\Components\Textarea::make('observaciones')
                            ->label('Observaciones')
                            ->maxLength(500)
                            ->rows(3),
                    ])
                    ->mutateRecordDataUsing(function (array $data, $record): array {
                        $data['grupo_id'] = $record->cuotaGrupal?->prestamo?->grupo?->id;
                        $data['numero_cuota'] = $record->cuotaGrupal?->numero_cuota;
                        $data['monto_cuota'] = $record->cuotaGrupal?->monto_cuota_grupal;
                        $data['monto_mora_pagada'] = $record->cuotaGrupal && $record->cuotaGrupal->mora 
                            ? abs($record->cuotaGrupal->mora->monto_mora_calculado) 
                            : 0;
                        
                        // Calcular saldo pendiente (excluyendo el pago actual que se está editando)
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

                        // Solo mostrar si el pago está pendiente
                        if (strtolower($record->estado_pago) !== 'pendiente') {
                            return false;
                        }

                        // Super admin y jefes no pueden editar
                        if ($user->hasAnyRole(['super_admin', 'Jefe de operaciones', 'Jefe de creditos'])) {
                            return false;
                        }

                        // Asesores solo pueden editar sus propios pagos
                        if ($user->hasRole('Asesor')) {
                            $asesor = \App\Models\Asesor::where('user_id', $user->id)->first();
                            $grupo = $record->cuotaGrupal?->prestamo?->grupo;
                            return $asesor && $grupo && $grupo->asesor_id === $asesor->id;
                        }

                        return false;
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
