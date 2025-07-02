<?php

namespace App\Filament\Dashboard\Resources\PagoResource\Pages;

use App\Filament\Dashboard\Resources\PagoResource;
use App\Filament\Dashboard\Resources\PagoResource\Widgets\PagosStatsWidget;
use Filament\Actions;
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

        // Crear query base para grupos que tienen pagos
        $query = Grupo::query()
            ->whereHas('prestamos.cuotasGrupales.pagos')
            ->with(['prestamos.cuotasGrupales.pagos' => function($q) {
                $q->orderBy('created_at', 'desc');
            }]);

        // Filtrar por asesor si es necesario
        if ($user->hasRole('Asesor')) {
            $asesor = \App\Models\Asesor::where('user_id', $user->id)->first();
            if ($asesor) {
                $query->where('asesor_id', $asesor->id);
            } else {
                return $query->whereRaw('1 = 0'); // No mostrar nada si no tiene asesor
            }
        }

        return $query;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                Tables\Columns\TextColumn::make('nombre_grupo')
                    ->label('Nombre del Grupo')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                Tables\Columns\TextColumn::make('total_cuotas')
                    ->label('Total Cuotas')
                    ->getStateUsing(function ($record) {
                        return $record->prestamos->sum(function ($prestamo) {
                            return $prestamo->cuotasGrupales->count();
                        });
                    })
                    ->alignCenter()
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('aprobadas')
                    ->label('Aprobadas')
                    ->getStateUsing(function ($record) {
                        return $record->prestamos->sum(function ($prestamo) {
                            return $prestamo->cuotasGrupales->filter(function ($cuota) {
                                return $cuota->pagos->where('estado_pago', 'aprobado')->count() > 0;
                            })->count();
                        });
                    })
                    ->alignCenter()
                    ->badge()
                    ->color('success'),

                Tables\Columns\TextColumn::make('pendientes')
                    ->label('Pendientes')
                    ->getStateUsing(function ($record) {
                        return $record->prestamos->sum(function ($prestamo) {
                            return $prestamo->cuotasGrupales->filter(function ($cuota) {
                                return $cuota->pagos->where('estado_pago', 'Pendiente')->count() > 0;
                            })->count();
                        });
                    })
                    ->alignCenter()
                    ->badge()
                    ->color('warning'),

                Tables\Columns\TextColumn::make('rechazadas')
                    ->label('Rechazadas')
                    ->getStateUsing(function ($record) {
                        return $record->prestamos->sum(function ($prestamo) {
                            return $prestamo->cuotasGrupales->filter(function ($cuota) {
                                return $cuota->pagos->where('estado_pago', 'Rechazado')->count() > 0;
                            })->count();
                        });
                    })
                    ->alignCenter()
                    ->badge()
                    ->color('danger'),

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

                Tables\Columns\TextColumn::make('estado')
                    ->label('Estado')
                    ->getStateUsing(function ($record) {
                        $totalCuotas = $record->prestamos->sum(function ($prestamo) {
                            return $prestamo->cuotasGrupales->count();
                        });

                        $cuotasAprobadas = $record->prestamos->sum(function ($prestamo) {
                            return $prestamo->cuotasGrupales->filter(function ($cuota) {
                                return $cuota->pagos->where('estado_pago', 'aprobado')->count() > 0;
                            })->count();
                        });

                        $cuotasPendientes = $record->prestamos->sum(function ($prestamo) {
                            return $prestamo->cuotasGrupales->filter(function ($cuota) {
                                return $cuota->pagos->where('estado_pago', 'Pendiente')->count() > 0;
                            })->count();
                        });

                        if ($totalCuotas == $cuotasAprobadas) {
                            return 'Completado';
                        } elseif ($cuotasPendientes > 0) {
                            return 'Con pendientes';
                        } else {
                            return 'En proceso';
                        }
                    })
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Completado' => 'success',
                        'Con pendientes' => 'warning',
                        'En proceso' => 'info',
                        default => 'gray',
                    }),
            ])
            ->filters([
                // Mostrar filtro solo a super_admin y Jefe de operaciones
                ...((Auth::user()->hasAnyRole(['super_admin', 'Jefe de operaciones'])) ? [
                    Tables\Filters\SelectFilter::make('asesor_id')
                        ->label('Filtrar por Asesor')
                        ->options(function () {
                            return \App\Models\Asesor::with('user')
                                ->get()
                                ->pluck('user.name', 'id')
                                ->prepend('Todos', '');
                        })
                        ->query(function (Builder $query, array $data) {
                            if (isset($data['value']) && $data['value'] !== '') {
                                return $query->where('asesor_id', $data['value']);
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
                        if (!empty($data['from'])) {
                            $query->whereHas('prestamos.cuotasGrupales.pagos', function ($q) use ($data) {
                                $q->whereDate('created_at', '>=', $data['from']);
                            });
                        }
                        if (!empty($data['until'])) {
                            $query->whereHas('prestamos.cuotasGrupales.pagos', function ($q) use ($data) {
                                $q->whereDate('created_at', '<=', $data['until']);
                            });
                        }
                        return $query;
                    }),
                Tables\Filters\SelectFilter::make('estado_pagos')
                    ->label('Filtrar por Estado de Pagos')
                    ->options([
                        '' => 'Todos',
                        'con_pendientes' => 'Con Pagos Pendientes',
                        'solo_aprobados' => 'Solo Aprobados',
                        'con_rechazados' => 'Con Rechazados',
                    ])
                    ->query(function (Builder $query, array $data) {
                        if (!isset($data['value']) || $data['value'] === '') {
                            return $query;
                        }

                        if ($data['value'] === 'con_pendientes') {
                            return $query->whereHas('prestamos.cuotasGrupales.pagos', function ($q) {
                                $q->where('estado_pago', 'Pendiente');
                            });
                        }

                        if ($data['value'] === 'solo_aprobados') {
                            return $query->whereHas('prestamos.cuotasGrupales.pagos', function ($q) {
                                $q->where('estado_pago', 'aprobado');
                            })->whereDoesntHave('prestamos.cuotasGrupales.pagos', function ($q) {
                                $q->whereIn('estado_pago', ['Pendiente', 'Rechazado']);
                            });
                        }

                        if ($data['value'] === 'con_rechazados') {
                            return $query->whereHas('prestamos.cuotasGrupales.pagos', function ($q) {
                                $q->where('estado_pago', 'Rechazado');
                            });
                        }
                    }),
            ])
            ->recordUrl(fn ($record) => PagoResource::getUrl('grupo-detalle', ['grupo' => $record->id]))
            ->actions([
                Tables\Actions\Action::make('ver_pagos')
                    ->label('Ver Pagos')
                    ->icon('heroicon-m-eye')
                    ->color('danger')
                    ->url(fn ($record) => PagoResource::getUrl('grupo-detalle', ['grupo' => $record->id]))
                    ->openUrlInNewTab(false),
            ])
            ->defaultSort('nombre_grupo')
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

            Actions\Action::make('exportar_pdf')
                ->label('Exportar PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('primary')
                ->form([
                    \Filament\Forms\Components\Select::make('grupo')
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
                    \Filament\Forms\Components\DatePicker::make('from')->label('Desde'),
                    \Filament\Forms\Components\DatePicker::make('until')->label('Hasta'),
                    \Filament\Forms\Components\Select::make('estado_pago')
                        ->label('Estado')
                        ->options([
                            '' => 'Todos',
                            'Pendiente' => 'Pendiente',
                            'Aprobado' => 'Aprobado',
                            'Rechazado' => 'Rechazado',
                        ]),
                ])
                ->action(function (array $data) {
                    $params = array_filter([
                        'grupo' => $data['grupo'] ?? null,
                        'from' => $data['from'] ?? null,
                        'until' => $data['until'] ?? null,
                        'estado_pago' => $data['estado_pago'] ?? null,
                    ]);
                    $url = route('pagos.exportar.pdf', $params);
                    return redirect($url);
                }),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [PagosStatsWidget::class];
    }
}
