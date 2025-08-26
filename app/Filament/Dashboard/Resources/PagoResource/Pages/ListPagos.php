<?php

namespace App\Filament\Dashboard\Resources\PagoResource\Pages;

use App\Filament\Dashboard\Resources\PagoResource;
use App\Filament\Dashboard\Resources\PagoResource\Widgets\PagosStatsWidget;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Builder;
use App\Models\Grupo;
use App\Models\Pago;

class ListPagos extends ListRecords
{
    protected static string $resource = PagoResource::class;

    protected function getTableQuery(): Builder
    {
        $user = Auth::user();

        // Ahora la tabla será por préstamo, no por grupo
        $query = \App\Models\Prestamo::query()
            ->whereHas('cuotasGrupales.pagos')
            ->with(['grupo', 'cuotasGrupales.pagos' => function($q) {
                $q->orderBy('created_at', 'desc');
            }])
            ->addSelect(['ultimo_pago_reciente' => function($sub) {
                $sub->selectRaw('MAX(pagos.created_at)')
                    ->from('cuotas_grupales')
                    ->join('pagos', 'pagos.cuota_grupal_id', '=', 'cuotas_grupales.id')
                    ->whereColumn('cuotas_grupales.prestamo_id', 'prestamos.id');
            }]);

        // Filtrar por asesor si es necesario
        if ($user->hasRole('Asesor')) {
            $asesor = \App\Models\Asesor::where('user_id', $user->id)->first();
            if ($asesor) {
                $query->whereHas('grupo', function($q) use ($asesor) {
                    $q->where('asesor_id', $asesor->id);
                });
            } else {
                return $query->whereRaw('1 = 0'); // No mostrar nada si no tiene asesor
            }
        }

        return $query;
    }

    public function table(Table $table): Table
    {
        return $table
            ->contentGrid(['md' => 1])
            ->query($this->getTableQuery())
            ->columns([

                Tables\Columns\TextColumn::make('grupo.nombre_grupo')
                    ->label('Grupo')
                    ->tooltip('Nombre completo del grupo')
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->size('sm'),

                Tables\Columns\TextColumn::make('tipo_prestamo')
                    ->label('Tipo')
                    ->tooltip('Tipo de préstamo: Préstamo o Retanqueo')
                    ->getStateUsing(function ($record) {
                        // $record es un modelo Prestamo, accedemos directamente al campo es_retanqueo
                        return $record->es_retanqueo ? 'Retanqueo' : 'Préstamo';
                    })
                    ->alignCenter()
                    ->badge()
                    ->size('sm')
                    ->color(fn ($state) => strtolower($state) === 'retanqueo' ? 'warning' : 'primary'),

                Tables\Columns\TextColumn::make('numero_prestamo')
                    ->label('N° Préstamo')
                    ->tooltip('Número de préstamo para el grupo')
                    ->size('sm')
                    ->alignCenter()
                    ->badge()
                    ->color('secondary')
                    ->getStateUsing(function ($record) {
                        // Obtener todos los préstamos del grupo ordenados por fecha de creación
                        $prestamos = $record->grupo->prestamos()->orderBy('created_at')->pluck('id')->toArray();
                        // Buscar la posición del préstamo actual en ese array (base 1)
                        $pos = array_search($record->id, $prestamos);
                        return $pos !== false ? ($pos + 1) : '-';
                    }),

                Tables\Columns\TextColumn::make('total_cuotas')
                    ->label('Cuotas')
                    ->tooltip('Total de cuotas del préstamo')
                    ->getStateUsing(function ($record) {
                        return $record->cuotasGrupales->count();
                    })
                    ->alignCenter()
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('aprobadas')
                    ->label('Aprob.')
                    ->tooltip('Cuotas aprobadas')
                    ->getStateUsing(function ($record) {
                        return $record->cuotasGrupales->filter(function ($cuota) {
                            return $cuota->pagos->where('estado_pago', 'aprobado')->count() > 0;
                        })->count();
                    })
                    ->alignCenter()
                    ->badge()
                    ->color('success'),

                Tables\Columns\TextColumn::make('pendientes')
                    ->label('Pend.')
                    ->tooltip('Cuotas pendientes')
                    ->getStateUsing(function ($record) {
                        return $record->cuotasGrupales->filter(function ($cuota) {
                            return $cuota->pagos->where('estado_pago', 'Pendiente')->count() > 0;
                        })->count();
                    })
                    ->alignCenter()
                    ->badge()
                    ->color('warning'),

                Tables\Columns\TextColumn::make('rechazadas')
                    ->label('Rech.')
                    ->tooltip('Cuotas rechazadas')
                    ->getStateUsing(function ($record) {
                        return $record->cuotasGrupales->filter(function ($cuota) {
                            return $cuota->pagos->where('estado_pago', 'Rechazado')->count() > 0;
                        })->count();
                    })
                    ->alignCenter()
                    ->badge()
                    ->color('danger'),

                Tables\Columns\TextColumn::make('monto_devolver_prestamo')
                    ->label('Monto a Devolver')
                    ->tooltip('Monto total a devolver del préstamo')
                    ->size('sm')
                    ->getStateUsing(function ($record) {
                        $montoDevolver = 0;
                        foreach ($record->cuotasGrupales as $cuota) {
                            if (strtolower($cuota->estado_cuota_grupal ?? '') !== 'anulada') {
                                $montoDevolver += floatval($cuota->monto_cuota_grupal);
                            }
                        }
                        return 'S/. ' . number_format($montoDevolver, 2);
                    })
                    ->alignCenter()
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('total_pagado_aprobado')
                    ->label('Total Pagado')
                    ->tooltip('Total pagado (incluye mora)')
                    ->size('sm')
                    ->getStateUsing(function ($record) {
                        $total = 0;
                        foreach ($record->cuotasGrupales as $cuota) {
                            $total += $cuota->pagos->where('estado_pago', 'aprobado')->sum('monto_pagado');
                        }
                        return 'S/. ' . number_format($total, 2);
                    })
                    ->alignCenter()
                    ->badge()
                    ->color('primary'),

                Tables\Columns\TextColumn::make('monto_mora_pendiente')
                    ->label('Mora pend.')
                    ->tooltip('Monto de mora pendiente')
                    ->size('sm')
                    ->getStateUsing(function ($record) {
                        $moraPendiente = 0;
                        foreach ($record->cuotasGrupales as $cuota) {
                            $estado = strtolower($cuota->estado_cuota_grupal ?? '');
                            if ($estado !== 'cancelada' && $estado !== 'anulada' && $cuota->mora) {
                                $montoMora = abs($cuota->mora->monto_mora_calculado);
                                $moraPagada = $cuota->pagos->where('estado_pago', 'aprobado')->sum('monto_mora_pagada');
                                $pendiente = $montoMora - $moraPagada;
                                if ($pendiente > 0) {
                                    $moraPendiente += $pendiente;
                                }
                            }
                        }
                        return 'S/. ' . number_format(max($moraPendiente, 0), 2);
                    })
                    ->alignCenter()
                    ->badge()
                    ->color('danger'),

                Tables\Columns\TextColumn::make('saldo_pendiente_real')
                    ->label('Saldo pend.')
                    ->tooltip('Saldo pendiente real (incluye mora)')
                    ->size('sm')
                    ->getStateUsing(function ($record) {
                        $montoDevolver = 0;
                        $moraAcumulada = 0;
                        $montoPagado = 0;
                        foreach ($record->cuotasGrupales as $cuota) {
                            if (strtolower($cuota->estado_cuota_grupal ?? '') !== 'anulada') {
                                $montoDevolver += floatval($cuota->monto_cuota_grupal);
                            }
                            if ($cuota->mora && strtolower($cuota->estado_cuota_grupal ?? '') !== 'cancelada') {
                                $moraAcumulada += abs($cuota->mora->monto_mora_calculado);
                            }
                            $montoPagado += $cuota->pagos->where('estado_pago', 'aprobado')->sum('monto_pagado');
                        }
                        $saldo = ($montoDevolver + $moraAcumulada) - $montoPagado;
                        return 'S/. ' . number_format(max($saldo, 0), 2);
                    })
                    ->alignCenter()
                    ->badge()
                    ->color('warning'),

                    /*
                Tables\Columns\TextColumn::make('ultimo_pago')
                    ->label('Último Pago')
                    ->getStateUsing(function ($record) {
                        $ultimoPago = null;
                        $fechaMasReciente = null;

                        foreach ($record->prestamos as $prestamo) {
                            foreach ($prestamo->cuotasGrupales as $cuota) {
                                foreach ($cuota->pagos as $pago) {
                                    if (!$fechaMasReciente || $pago->created_at > $fechaMasReciente) {
                                        $fechaMasReciente = $pago->created_at;
                                        $ultimoPago = $pago;
                                    }
                                }
                            }
                        }

                        return $ultimoPago ? $ultimoPago->created_at->format('d/m/Y H:i') : 'Sin pagos';
                    })
                    ->alignCenter(),
                    */
                Tables\Columns\TextColumn::make('estado')
                    ->label('Estado')
                    ->tooltip('Estado general del préstamo')
                    ->size('sm')
                    ->getStateUsing(function ($record) {
                        $totalCuotas = $record->cuotasGrupales->count();
                        $cuotasAprobadas = $record->cuotasGrupales->filter(function ($cuota) {
                            return $cuota->pagos->where('estado_pago', 'aprobado')->count() > 0;
                        })->count();
                        $cuotasPendientes = $record->cuotasGrupales->filter(function ($cuota) {
                            return $cuota->pagos->where('estado_pago', 'Pendiente')->count() > 0;
                        })->count();
                        if ($totalCuotas == $cuotasAprobadas && $totalCuotas > 0) {
                            return 'Completado';
                        } elseif ($cuotasPendientes > 0) {
                            return 'pendientes';
                        } else {
                            return 'En proceso';
                        }
                    })
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Completado' => 'success',
                        'pendientes' => 'warning',
                        'En proceso' => 'info',
                        default => 'gray',
                    }),
            ])
            ->filters([
                // Mostrar filtro solo a super_admin y Jefe de operaciones
                ...((Auth::user()->hasAnyRole(['super_admin', 'Jefe de operaciones', 'Jefe de creditos'])) ? [
                    Tables\Filters\SelectFilter::make('asesor_id')
                        ->label('Filtrar por Asesor')
                        ->options(function () {
                            return \App\Models\Asesor::with('persona')
                                ->get()
                                ->mapWithKeys(function ($asesor) {
                                    $nombreCompleto = $asesor->persona
                                        ? $asesor->persona->nombre . ' ' . $asesor->persona->apellidos
                                        : 'Asesor sin nombre';
                                    return [$asesor->id => $nombreCompleto];
                                })
                                ->prepend('Todos los asesores', '');
                        })
                        ->query(function (Builder $query, array $data) {
                            if (isset($data['value']) && $data['value'] !== '') {
                                return $query->whereHas('grupo', function ($q) use ($data) {
                                    $q->where('asesor_id', $data['value']);
                                });
                            }
                            return $query;
                        }),
                ] : []),
                // Filtro por rango de fecha de pagos
                Tables\Filters\Filter::make('fecha_pago')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('from')->label('Desde'),
                        \Filament\Forms\Components\DatePicker::make('until')->label('Hasta'),
                    ])
                    ->query(function (Builder $query, array $data) {
                        if (!empty($data['from']) || !empty($data['until'])) {
                            $query->whereHas('cuotasGrupales.pagos', function ($q) use ($data) {
                                if (!empty($data['from'])) {
                                    $q->whereDate('fecha_pago', '>=', $data['from']);
                                }
                                if (!empty($data['until'])) {
                                    $q->whereDate('fecha_pago', '<=', $data['until']);
                                }
                            });
                        }
                        return $query;
                    }),
                Tables\Filters\SelectFilter::make('estado_general')
                    ->label('Filtrar por Estado')
                    ->options([
                        'Completado' => 'Completado',
                        'pendientes' => 'Pendientes',
                        'En proceso' => 'En proceso',
                    ])
                    ->searchable(false)
                    ->query(function (Builder $query, array $data) {
                        if (!isset($data['value']) || $data['value'] === '') {
                            return $query;
                        }

                        // Filtrar por estado general del préstamo
                        $all = $query->get();
                        $ids = [];
                        foreach ($all as $prestamo) {
                            $totalCuotas = $prestamo->cuotasGrupales->count();
                            $cuotasAprobadas = $prestamo->cuotasGrupales->filter(function ($cuota) {
                                return $cuota->pagos->where('estado_pago', 'aprobado')->count() > 0;
                            })->count();
                            $cuotasPendientes = $prestamo->cuotasGrupales->filter(function ($cuota) {
                                return $cuota->pagos->where('estado_pago', 'Pendiente')->count() > 0;
                            })->count();
                            $estado = '';
                            if ($totalCuotas == $cuotasAprobadas && $totalCuotas > 0) {
                                $estado = 'Completado';
                            } elseif ($cuotasPendientes > 0) {
                                $estado = 'pendientes';
                            } else {
                                $estado = 'En proceso';
                            }
                            if ($estado === $data['value']) {
                                $ids[] = $prestamo->id;
                            }
                        }
                        return $query->whereIn('id', $ids);
                    }),
            ])
            ->recordUrl(fn ($record) => PagoResource::getUrl('grupo-detalle', ['grupo' => $record->grupo_id, 'prestamo' => $record->id]))
            ->actions([
                Tables\Actions\Action::make('ver_pagos')
                    ->label('Ver Pagos')
                    ->icon('heroicon-m-eye')
                    ->color('danger')
                    ->url(fn ($record) => PagoResource::getUrl('grupo-detalle', ['grupo' => $record->grupo_id, 'prestamo' => $record->id]))
                    ->openUrlInNewTab(false),
            ])
            ->defaultSort('ultimo_pago_reciente', 'desc')
            ->striped()
            ->paginated([10, 25, 50, 100]);
    }

    protected function getHeaderActions(): array
    {
        $user = Auth::user();

        return [
            Actions\CreateAction::make()
                ->icon('heroicon-o-plus-circle')
                ->label('Crear Pago'),

            Actions\Action::make('exportar')
                ->label('Exportar Pagos')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('primary')
                ->form([
                    Forms\Components\Section::make('Configuración de Exportación')
                        ->description('Seleccione el formato y filtros para la exportación')
                        ->schema([
                            Forms\Components\Grid::make(2)
                                ->schema([
                                    Forms\Components\Select::make('formato')
                                        ->label('Formato de Exportación')
                                        ->options([
                                            'pdf' => '📄 PDF',
                                            'excel' => '📊 Excel (.csv)'
                                        ])
                                        ->default('pdf')
                                        ->required()
                                        ->native(false),

                                    Forms\Components\Select::make('grupo')
                                        ->label('Nombre del grupo')
                                        ->options(function () use ($user) {
                                            $query = \App\Models\Grupo::query();

                                            if ($user->hasRole('Asesor')) {
                                                $asesor = \App\Models\Asesor::where('user_id', $user->id)->first();
                                                if ($asesor) {
                                                    $query->where('asesor_id', $asesor->id);
                                                } else {
                                                    return [];
                                                }
                                            } elseif (!$user->hasAnyRole(['super_admin', 'Jefe de operaciones', 'Jefe de creditos'])) {
                                                return [];
                                            }

                                            return $query->orderBy('nombre_grupo')->pluck('nombre_grupo', 'id')->toArray();
                                        })
                                        ->searchable()
                                        ->placeholder('Todos'),
                                ]),

                            Forms\Components\Grid::make(2)
                                ->schema([
                                    Forms\Components\DatePicker::make('from')
                                        ->label('Fecha Desde')
                                        ->helperText('Dejar vacío para incluir desde el principio')
                                        ->maxDate(now()),

                                    Forms\Components\DatePicker::make('until')
                                        ->label('Fecha Hasta')
                                        ->helperText('Dejar vacío para incluir hasta la fecha actual')
                                        ->maxDate(now()),
                                ]),

                            Forms\Components\Select::make('estado_pago')
                                ->label('Estado de Pago')
                                ->options([
                                    'Todos los estados' => '📋 Todos los estados',
                                    'Pendiente' => '⏳ Pendiente',
                                    'Aprobado' => '✅ Aprobado',
                                    'Rechazado' => '❌ Rechazado',
                                ])
                                ->default('Todos los estados')
                                ->placeholder(null)
                                ->native(false),

                            Forms\Components\Placeholder::make('info')
                                ->content('💡 **Información importante:**
• Si no selecciona fechas, se exportarán todos los registros
• El archivo se descargará automáticamente una vez generado')
                                ->columnSpanFull(),
                        ]),
                ])
                ->action(function (array $data) {
                    $params = [
                        'formato' => $data['formato'],
                    ];

                    // Agregar parámetros opcionales solo si tienen valor
                    if (!empty($data['grupo'])) {
                        $params['grupo'] = $data['grupo'];
                    }
                    if (!empty($data['from'])) {
                        $params['from'] = $data['from'];
                    }
                    if (!empty($data['until'])) {
                        $params['until'] = $data['until'];
                    }
                    // Para estado_pago, siempre incluir el valor
                    $params['estado_pago'] = $data['estado_pago'] ?? 'Todos los estados';

                    // Usar la ruta unificada que maneja tanto PDF como Excel
                    $url = route('pagos.exportar', $params);

                    return redirect($url);
                })
                ->modalHeading('📊 Exportar Pagos')
                ->modalSubmitActionLabel('Generar y Descargar')
                ->modalCancelActionLabel('Cancelar')
                ->modalWidth('2xl'),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [PagosStatsWidget::class];
    }
}
