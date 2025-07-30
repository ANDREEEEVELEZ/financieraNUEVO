<?php

namespace App\Filament\Dashboard\Resources;

use App\Filament\Dashboard\Resources\RetanqueoResource\Pages;
use App\Models\Retanqueo;
use App\Models\Grupo;
use App\Models\Prestamo;
use App\Services\RetanqueoService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\ActionGroup;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class RetanqueoResource extends Resource
{
    protected static ?string $model = Retanqueo::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-path';
    
    protected static ?string $navigationLabel = 'Retanqueos';
    
    protected static ?string $modelLabel = 'Retanqueo';
    
    protected static ?string $pluralModelLabel = 'Retanqueos';
    
    protected static ?int $navigationSort = 4; // Después de Préstamos

    public static function form(Form $form): Form
    {
        $user = request()->user();
        $retanqueoService = new RetanqueoService();

        return $form
            ->schema([
                Section::make('Información de la Solicitud')
                    ->description('Detalles generales del retanqueo')
                    ->icon('heroicon-o-information-circle')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('prestamo_id')
                                    ->label('Préstamo a Retanquear')
                                    ->prefixIcon('heroicon-o-banknotes')
                                    ->options(function () use ($user, $retanqueoService) {
                                        $asesorId = null;
                                        if ($user->hasRole('Asesor')) {
                                            $asesor = \App\Models\Asesor::where('user_id', $user->id)->first();
                                            $asesorId = $asesor ? $asesor->id : null;
                                        }

                                        $grupos = $retanqueoService->obtenerGruposElegibles($asesorId);
                                        $opciones = [];

                                        foreach ($grupos as $grupo) {
                                            $prestamoActivo = $grupo->prestamos()
                                                ->where('estado', 'Aprobado')
                                                ->whereHas('cuotasGrupales', function ($q) {
                                                    $q->where('estado_pago', '!=', 'pagado')
                                                      ->where('saldo_pendiente', '>', 0);
                                                })
                                                ->first();

                                            if ($prestamoActivo) {
                                                $estadoPrestamo = $retanqueoService->calcularEstadoPrestamo($prestamoActivo->id);
                                                $opciones[$prestamoActivo->id] = sprintf(
                                                    '%s - S/ %.2f prestado - S/ %.2f pendiente (%d/%d cuotas)',
                                                    $grupo->nombre_grupo,
                                                    $estadoPrestamo['monto_prestado_original'],
                                                    $estadoPrestamo['saldo_pendiente_total'],
                                                    $estadoPrestamo['cuotas_pagadas'],
                                                    $estadoPrestamo['cuotas_total']
                                                );
                                            }
                                        }

                                        return $opciones;
                                    })
                                    ->required()
                                    ->searchable()
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, callable $set) use ($retanqueoService) {
                                        if ($state) {
                                            try {
                                                $estadoPrestamo = $retanqueoService->calcularEstadoPrestamo($state);
                                                $prestamo = $estadoPrestamo['prestamo'];
                                                $grupo = $prestamo->grupo;

                                                // Configurar participantes por defecto
                                                $participantes = [];
                                                foreach ($grupo->clientes as $cliente) {
                                                    $participantes[] = [
                                                        'cliente_id' => $cliente->id,
                                                        'nombre_completo' => $cliente->persona->nombre . ' ' . $cliente->persona->apellidos,
                                                        'ciclo' => \App\Helpers\CicloHelper::normalize($cliente->ciclo ?? 'I'),
                                                        'monto_maximo' => \App\Helpers\CicloHelper::getMontoMaximo(\App\Helpers\CicloHelper::normalize($cliente->ciclo ?? 'I')),
                                                        'participacion_tipo' => 'retanquea', // Por defecto todos retanquean
                                                        'monto_solicitado' => 400, // Monto por defecto
                                                    ];
                                                }

                                                $set('participantes', $participantes);
                                                $set('cantidad_cuotas_nuevo', 20);
                                                $set('estado_prestamo_info', [
                                                    'monto_prestado' => $estadoPrestamo['monto_prestado_original'],
                                                    'saldo_pendiente' => $estadoPrestamo['saldo_pendiente_total'],
                                                    'cuotas_pagadas' => $estadoPrestamo['cuotas_pagadas'],
                                                    'cuotas_total' => $estadoPrestamo['cuotas_total'],
                                                    'porcentaje_pagado' => $estadoPrestamo['porcentaje_pagado']
                                                ]);
                                            } catch (\Exception $e) {
                                                $set('participantes', []);
                                                $set('estado_prestamo_info', null);
                                            }
                                        }
                                    })
                                    ->helperText('Seleccione el préstamo que desea retanquear'),

                                TextInput::make('cantidad_cuotas_nuevo')
                                    ->label('Cantidad de Cuotas (Nuevo Préstamo)')
                                    ->prefixIcon('heroicon-o-calendar-days')
                                    ->numeric()
                                    ->required()
                                    ->default(2)
                                    ->minValue(4)
                                    ->maxValue(52)
                                    ->helperText('Entre 4 y 52 cuotas')
                            ])
                    ]),

                Section::make('Estado del Préstamo Actual')
                    ->description('Información del préstamo que se va a retanquear')
                    ->icon('heroicon-o-chart-bar')
                    ->schema([
                        Placeholder::make('estado_prestamo_info')
                            ->label('')
                            ->content(function (callable $get) {
                                $info = $get('estado_prestamo_info');
                                if (!$info) {
                                    return 'Seleccione un préstamo para ver la información';
                                }

                                return new HtmlString(sprintf(
                                    '<div class="grid grid-cols-2 md:grid-cols-4 gap-4 p-4 bg-gray-50 rounded-lg">
                                        <div class="text-center">
                                            <div class="text-2xl font-bold text-blue-600">S/ %.2f</div>
                                            <div class="text-sm text-gray-600">Monto Prestado</div>
                                        </div>
                                        <div class="text-center">
                                            <div class="text-2xl font-bold text-red-600">S/ %.2f</div>
                                            <div class="text-sm text-gray-600">Saldo Pendiente</div>
                                        </div>
                                        <div class="text-center">
                                            <div class="text-2xl font-bold text-green-600">%d/%d</div>
                                            <div class="text-sm text-gray-600">Cuotas Pagadas</div>
                                        </div>
                                        <div class="text-center">
                                            <div class="text-2xl font-bold text-purple-600">%.1f%%</div>
                                            <div class="text-sm text-gray-600">Progreso</div>
                                        </div>
                                    </div>',
                                    $info['monto_prestado'],
                                    $info['saldo_pendiente'],
                                    $info['cuotas_pagadas'],
                                    $info['cuotas_total'],
                                    $info['porcentaje_pagado']
                                ));
                            })
                    ])
                    ->visible(fn (callable $get) => !empty($get('estado_prestamo_info')))
                    ->collapsible(),

                Section::make('Configuración de Participantes')
                    ->description('Configure quién participará en el retanqueo y con qué montos')
                    ->icon('heroicon-o-users')
                    ->schema([
                        Repeater::make('participantes')
                            ->label('')
                            ->schema([
                                Grid::make(6)
                                    ->schema([
                                        Placeholder::make('nombre_completo')
                                            ->label('Cliente')
                                            ->content(fn (callable $get) => $get('nombre_completo') ?? 'Sin nombre'),

                                        Placeholder::make('ciclo')
                                            ->label('Ciclo')
                                            ->content(fn (callable $get) => $get('ciclo') ?? 'I'),

                                        Placeholder::make('monto_maximo')
                                            ->label('Monto Máximo')
                                            ->content(fn (callable $get) => 'S/ ' . number_format($get('monto_maximo') ?? 400, 0)),

                                        Select::make('participacion_tipo')
                                            ->label('Participación')
                                            ->options([
                                                'retanquea' => 'Retanquea',
                                                'no_retanquea' => 'No Retanquea',
                                                'nueva' => 'Cliente Nuevo'
                                            ])
                                            ->required()
                                            ->reactive()
                                            ->afterStateUpdated(function ($state, callable $set) {
                                                if ($state === 'no_retanquea') {
                                                    $set('monto_solicitado', 0);
                                                } elseif ($state === 'retanquea' || $state === 'nueva') {
                                                    $set('monto_solicitado', 400);
                                                }
                                            }),

                                        TextInput::make('monto_solicitado')
                                            ->label('Monto Solicitado')
                                            ->prefix('S/.')
                                            ->numeric()
                                            ->minValue(0)
                                            ->reactive()
                                            ->disabled(fn (callable $get) => $get('participacion_tipo') === 'no_retanquea')
                                            ->afterStateUpdated(function ($state, callable $get, callable $set) {
                                                $montoMaximo = $get('monto_maximo') ?? 400;
                                                if ($state > $montoMaximo) {
                                                    $set('monto_solicitado', $montoMaximo);
                                                    Notification::make()
                                                        ->warning()
                                                        ->title('Monto ajustado')
                                                        ->body("El monto se ajustó al máximo permitido: S/ {$montoMaximo}")
                                                        ->send();
                                                }
                                            }),

                                        Forms\Components\Hidden::make('cliente_id'),
                                    ])
                            ])
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false)
                            ->collapsed(false)
                            ->cloneable(false)
                    ])
                    ->visible(fn (callable $get) => !empty($get('participantes')))
                    ->collapsible(),

                Section::make('Resumen de Cálculos')
                    ->description('Resumen automático de los montos del retanqueo')
                    ->icon('heroicon-o-calculator')
                    ->schema([
                        Placeholder::make('resumen_calculos')
                            ->label('')
                            ->content(function (callable $get) {
                                $participantes = $get('participantes') ?? [];
                                $estadoInfo = $get('estado_prestamo_info');
                                
                                if (empty($participantes) || !$estadoInfo) {
                                    return 'Configure los participantes para ver el resumen';
                                }

                                $totalNuevoPrestamo = 0;
                                $totalCobertura = 0;
                                $integrantesRetanquean = 0;

                                foreach ($participantes as $participante) {
                                    if (in_array($participante['participacion_tipo'], ['retanquea', 'nueva'])) {
                                        $totalNuevoPrestamo += $participante['monto_solicitado'] ?? 0;
                                    }
                                    if ($participante['participacion_tipo'] === 'retanquea') {
                                        $integrantesRetanquean++;
                                    }
                                }

                                if ($integrantesRetanquean > 0) {
                                    $totalCobertura = $estadoInfo['saldo_pendiente'] / $integrantesRetanquean * $integrantesRetanquean;
                                    $totalCobertura = min($totalCobertura, $estadoInfo['saldo_pendiente']);
                                }

                                $montoAEntregar = $totalNuevoPrestamo - $totalCobertura;
                                $saldoRestante = $estadoInfo['saldo_pendiente'] - $totalCobertura;

                                return new HtmlString(sprintf(
                                    '<div class="grid grid-cols-2 md:grid-cols-4 gap-4 p-4 bg-blue-50 rounded-lg border border-blue-200">
                                        <div class="text-center">
                                            <div class="text-xl font-bold text-blue-700">S/ %.2f</div>
                                            <div class="text-sm text-blue-600">Nuevo Préstamo</div>
                                        </div>
                                        <div class="text-center">
                                            <div class="text-xl font-bold text-green-700">S/ %.2f</div>
                                            <div class="text-sm text-green-600">Cobertura Antiguo</div>
                                        </div>
                                        <div class="text-center">
                                            <div class="text-xl font-bold text-purple-700">S/ %.2f</div>
                                            <div class="text-sm text-purple-600">A Entregar</div>
                                        </div>
                                        <div class="text-center">
                                            <div class="text-xl font-bold text-orange-700">S/ %.2f</div>
                                            <div class="text-sm text-orange-600">Saldo Restante</div>
                                        </div>
                                    </div>',
                                    $totalNuevoPrestamo,
                                    $totalCobertura,
                                    $montoAEntregar,
                                    $saldoRestante
                                ));
                            })
                    ])
                    ->visible(fn (callable $get) => !empty($get('participantes')))
                    ->collapsible(),

                Forms\Components\Hidden::make('estado_prestamo_info'),
                Forms\Components\Hidden::make('estado_retanqueo')->default('solicitud_pendiente'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('prestamoAntiguo.grupo.nombre_grupo')
                    ->label('Grupo')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                TextColumn::make('prestamoAntiguo.grupo.asesor.persona.nombre')
                    ->label('Asesor')
                    ->getStateUsing(function ($record) {
                        $asesor = $record->prestamoAntiguo?->grupo?->asesor;
                        if ($asesor && $asesor->persona) {
                            return $asesor->persona->nombre . ' ' . $asesor->persona->apellidos;
                        }
                        return 'Sin asesor';
                    })
                    ->searchable()
                    ->sortable()
                    ->visible(fn () => !request()->user()->hasRole('Asesor')),

                TextColumn::make('monto_retanqueo')
                    ->label('Nuevo Préstamo')
                    ->money('PEN')
                    ->sortable(),

                TextColumn::make('monto_usado_para_cubrir_antiguo')
                    ->label('Cobertura')
                    ->money('PEN')
                    ->sortable(),

                TextColumn::make('monto_desembolsar')
                    ->label('A Entregar')
                    ->money('PEN')
                    ->sortable(),

                TextColumn::make('cantidad_cuotas_nuevo')
                    ->label('Cuotas')
                    ->sortable(),

                BadgeColumn::make('estado_retanqueo')
                    ->label('Estado')
                    ->colors([
                        'warning' => 'solicitud_pendiente',
                        'success' => 'aprobado',
                        'primary' => 'ejecutado',
                        'danger' => 'rechazado',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'solicitud_pendiente' => 'Pendiente',
                        'aprobado' => 'Aprobado',
                        'ejecutado' => 'Ejecutado',
                        'rechazado' => 'Rechazado',
                        default => $state,
                    }),

                TextColumn::make('created_at')
                    ->label('Fecha Solicitud')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('fecha_aceptacion')
                    ->label('Fecha Aprobación')
                    ->date('d/m/Y')
                    ->sortable()
                    ->placeholder('No aprobado'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('estado_retanqueo')
                    ->label('Estado')
                    ->options([
                        'solicitud_pendiente' => 'Solicitudes Pendientes',
                        'aprobado' => 'Aprobados',
                        'ejecutado' => 'Ejecutados',
                        'rechazado' => 'Rechazados',
                    ]),

                Tables\Filters\Filter::make('created_at')
                    ->label('Fecha de Solicitud')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')
                            ->label('Desde'),
                        Forms\Components\DatePicker::make('created_until')
                            ->label('Hasta'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                ActionGroup::make([
                    Tables\Actions\ViewAction::make()
                        ->icon('heroicon-m-eye'),

                    Tables\Actions\EditAction::make()
                        ->icon('heroicon-m-pencil-square')
                        ->visible(fn ($record) => $record->esSolicitudPendiente()),

                    Action::make('aprobar')
                        ->label('Aprobar')
                        ->icon('heroicon-m-check-circle')
                        ->color('success')
                        ->visible(fn ($record) => $record->esSolicitudPendiente() && 
                                  request()->user()->hasAnyRole(['super_admin', 'Jefe de operaciones', 'Jefe de creditos']))
                        ->requiresConfirmation()
                        ->modalHeading('Aprobar Retanqueo')
                        ->modalDescription('¿Está seguro de que desea aprobar este retanqueo?')
                        ->action(function ($record) {
                            try {
                                $retanqueoService = new RetanqueoService();
                                $retanqueoService->aprobarRetanqueo($record->id);
                                
                                Notification::make()
                                    ->title('Retanqueo Aprobado')
                                    ->body('El retanqueo ha sido aprobado exitosamente.')
                                    ->success()
                                    ->send();
                            } catch (\Exception $e) {
                                Notification::make()
                                    ->title('Error')
                                    ->body('Error al aprobar el retanqueo: ' . $e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        }),

                    Action::make('rechazar')
                        ->label('Rechazar')
                        ->icon('heroicon-m-x-circle')
                        ->color('danger')
                        ->visible(fn ($record) => $record->esSolicitudPendiente() && 
                                  request()->user()->hasAnyRole(['super_admin', 'Jefe de operaciones', 'Jefe de creditos']))
                        ->requiresConfirmation()
                        ->modalHeading('Rechazar Retanqueo')
                        ->modalDescription('¿Está seguro de que desea rechazar este retanqueo?')
                        ->action(function ($record) {
                            try {
                                $retanqueoService = new RetanqueoService();
                                $retanqueoService->rechazarRetanqueo($record->id);
                                
                                Notification::make()
                                    ->title('Retanqueo Rechazado')
                                    ->body('El retanqueo ha sido rechazado.')
                                    ->success()
                                    ->send();
                            } catch (\Exception $e) {
                                Notification::make()
                                    ->title('Error')
                                    ->body('Error al rechazar el retanqueo: ' . $e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        }),

                    Action::make('ejecutar')
                        ->label('Ejecutar')
                        ->icon('heroicon-m-play')
                        ->color('primary')
                        ->visible(fn ($record) => $record->estaAprobado() && 
                                  request()->user()->hasAnyRole(['super_admin', 'Jefe de operaciones', 'Jefe de creditos']))
                        ->requiresConfirmation()
                        ->modalHeading('Ejecutar Retanqueo')
                        ->modalDescription('¿Está seguro de que desea ejecutar este retanqueo? Esta acción creará el nuevo préstamo y actualizará el anterior.')
                        ->action(function ($record) {
                            try {
                                $retanqueoService = new RetanqueoService();
                                $retanqueoService->ejecutarRetanqueo($record->id);
                                
                                Notification::make()
                                    ->title('Retanqueo Ejecutado')
                                    ->body('El retanqueo ha sido ejecutado exitosamente. Se ha creado el nuevo préstamo.')
                                    ->success()
                                    ->send();
                            } catch (\Exception $e) {
                                Notification::make()
                                    ->title('Error')
                                    ->body('Error al ejecutar el retanqueo: ' . $e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        }),
                ])
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn () => request()->user()->hasAnyRole(['super_admin']))
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getEloquentQuery(): Builder
    {
        $user = request()->user();
        return parent::getEloquentQuery()->visiblePorUsuario($user);
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
            'index' => Pages\ListRetanqueos::route('/'),
            'create' => Pages\CreateRetanqueo::route('/create'),
            'view' => Pages\ViewRetanqueo::route('/{record}'),
            'edit' => Pages\EditRetanqueo::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        $user = request()->user();
        return $user && $user->hasAnyRole(['super_admin', 'Jefe de operaciones', 'Jefe de creditos', 'Asesor']);
    }

    public static function canCreate(): bool
    {
        $user = request()->user();
        return $user && $user->hasAnyRole(['super_admin', 'Jefe de operaciones', 'Jefe de creditos', 'Asesor']);
    }

    public static function canEdit($record): bool
    {
        $user = request()->user();
        if (!$user) return false;

        // Solo se pueden editar solicitudes pendientes
        if (!$record->esSolicitudPendiente()) {
            return false;
        }

        // Los asesores solo pueden editar sus propias solicitudes
        if ($user->hasRole('Asesor')) {
            $asesor = \App\Models\Asesor::where('user_id', $user->id)->first();
            if ($asesor) {
                $grupoAsesorId = $record->prestamoAntiguo?->grupo?->asesor_id;
                return $grupoAsesorId === $asesor->id;
            }
            return false;
        }

        return $user->hasAnyRole(['super_admin', 'Jefe de operaciones', 'Jefe de creditos']);
    }

    public static function canDelete($record): bool
    {
        $user = request()->user();
        return $user && $user->hasRole('super_admin') && $record->esSolicitudPendiente();
    }
}
