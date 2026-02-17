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
use Filament\Forms\Components\Section;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Builder;
use Filament\Notifications\Notification;
use Filament\Forms\Components\Repeater;


class PagoResource extends Resource
{
    protected static ?string $model = Pago::class;
    protected static ?string $navigationIcon = 'heroicon-o-credit-card';
    protected static ?string $navigationLabel = 'Pagos';
    protected static ?string $modelLabel = 'Pago';
    protected static ?string $pluralModelLabel = 'Pagos';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Información del Pago')
                ->schema([
                    Select::make('grupo_id')
                        ->label('Grupo')
                        ->prefixIcon('heroicon-o-rectangle-stack')
                        ->options(function () {
                            $user = request()->user();

                            // Si viene desde moras con cuota_grupal_id, incluir esa opción específica
                            $cuotaGrupalId = request()->get('cuota_grupal_id');
                            $opciones = [];

                            if ($cuotaGrupalId) {
                                $cuota = \App\Models\CuotasGrupales::with('prestamo.grupo')->find($cuotaGrupalId);
                                if ($cuota && $cuota->prestamo && $cuota->prestamo->grupo) {
                                    $grupo = $cuota->prestamo->grupo;
                                    $prestamo = $cuota->prestamo;
                                    $key = $grupo->id . '_' . $prestamo->id;

                                    if ($prestamo->es_retanqueo) {
                                        $opciones[$key] = $prestamo->descripcion;
                                    } else {
                                        $opciones[$key] = $grupo->nombre_grupo;
                                    }
                                }
                            }

                            $query = \App\Models\Grupo::whereHas('prestamos', function ($q) {
                                $q->whereIn('estado', ['Activo', 'Ejecutado']);
                            })->orderBy('nombre_grupo', 'asc');

                            if ($user->hasRole('Asesor')) {
                                $asesor = \App\Models\Asesor::where('user_id', $user->id)->first();
                                if ($asesor) {
                                    $query->where('asesor_id', $asesor->id);
                                } else {
                                    return $opciones; // Retornar solo la opción específica si es asesor sin permisos
                                }
                            } elseif (!$user->hasAnyRole(['super_admin', 'Jefe de operaciones', 'Jefe de creditos'])) {
                                return $opciones; // Retornar solo la opción específica si no tiene permisos
                            }

                            // Modificar para mostrar nombres diferenciados
                            $grupos = $query->with([
                                'prestamos' => function ($q) {
                                $q->whereIn('estado', ['Activo', 'Ejecutado']);
                            }
                            ])->get();

                            foreach ($grupos as $grupo) {
                                foreach ($grupo->prestamos as $prestamo) {
                                    $key = $grupo->id . '_' . $prestamo->id;
                                    if (!isset($opciones[$key])) { // No sobrescribir si ya existe
                                        if ($prestamo->es_retanqueo) {
                                            // Para retanqueos, mostrar la descripción completa que ya incluye "RETANQUEO #X"
                                            $opciones[$key] = $prestamo->descripcion;
                                        } else {
                                            // Para originales, mostrar solo el nombre del grupo
                                            $opciones[$key] = $grupo->nombre_grupo;
                                        }
                                    }
                                }
                            }

                            return $opciones;
                        })
                        ->afterStateHydrated(function ($component, $state, $record) {
                            if ($record && $record->cuotaGrupal && $record->cuotaGrupal->prestamo && $record->cuotaGrupal->prestamo->grupo) {
                                // Construir el valor correcto para el estado
                                $grupoId = $record->cuotaGrupal->prestamo->grupo->id;
                                $prestamoId = $record->cuotaGrupal->prestamo->id;
                                $component->state($grupoId . '_' . $prestamoId);

                                $user = request()->user();
                                if (
                                    $record->estado_pago !== 'pendiente' ||
                                    $user->hasAnyRole(['super_admin', 'Jefe de operaciones', 'Jefe de creditos'])
                                ) {
                                    $component->disabled(true);
                                } else {
                                    $component->disabled(false);
                                }
                            }
                        })
                        ->afterStateUpdated(function ($state, callable $set, callable $get) {
                            // Extraer grupo_id y prestamo_id del estado
                            if (!$state || !str_contains($state, '_')) {
                                return;
                            }

                            [$grupoId, $prestamoId] = explode('_', $state, 2);

                            $cuotas = CuotasGrupales::whereHas('prestamo', function ($query) use ($grupoId, $prestamoId) {
                                $query->where('grupo_id', $grupoId)->where('id', $prestamoId);
                            })
                                ->pluck('id');

                            $pagoPendiente = Pago::whereIn('cuota_grupal_id', $cuotas)
                                ->where('estado_pago', 'pendiente')
                                ->exists();

                            if ($pagoPendiente) {
                                Notification::make()
                                    ->title('Este grupo ya tiene un pago pendiente')
                                    ->body('No puedes registrar un nuevo pago hasta que se apruebe o rechace el anterior.')
                                    ->danger()
                                    ->persistent()
                                    ->send();

                                $set('grupo_id', null);
                                $set('cuota_grupal_id', null);
                                $set('numero_cuota', null);
                                $set('monto_cuota', null);
                                $set('monto_mora_pagada', 0.00);
                                $set('saldo_pendiente_actual', 0.00);
                                $set('monto_pagado', 0.00);
                                $set('tipo_pago', null);
                                return;
                            }

                            // Obtener el préstamo para verificar si es un retanqueo parcial
                            $prestamo = \App\Models\Prestamo::find($prestamoId);

                            // Para préstamos parcialmente retanqueados, usar lógica especial
                            if ($prestamo && $prestamo->estado === 'Ejecutado') {
                                // Buscar todas las cuotas que no estén completamente pagadas
                                $todasLasCuotas = CuotasGrupales::whereHas('prestamo', function ($query) use ($grupoId, $prestamoId) {
                                    $query->where('grupo_id', $grupoId)->where('id', $prestamoId);
                                })
                                    ->where('estado_pago', '!=', 'pagado')
                                    ->orderBy('numero_cuota', 'asc')
                                    ->get();

                                // Filtrar manualmente las que tienen saldo pendiente
                                $cuotas = collect();
                                foreach ($todasLasCuotas as $cuota) {
                                    if ($cuota->saldoPendiente() > 0) {
                                        $cuotas->push($cuota);
                                    }
                                }
                            } else {
                                // Para préstamos normales, usar la lógica original
                                $cuotas = CuotasGrupales::whereHas('prestamo', function ($query) use ($grupoId, $prestamoId) {
                                    $query->where('grupo_id', $grupoId)->where('id', $prestamoId);
                                })
                                    ->whereIn('estado_cuota_grupal', ['vigente', 'mora'])
                                    ->orderBy('numero_cuota', 'asc')
                                    ->get();
                            }

                            // Buscar la primera cuota con saldo pendiente
                            $primeraPendiente = $cuotas->first(function ($c) {
                                return $c->saldoPendiente() > 0;
                            });

                            // Si se está llegando desde el módulo de moras y ya hay una cuota seleccionada (por ejemplo, la 4), validar si hay anteriores pendientes
                            $cuotaSeleccionadaId = $get('cuota_grupal_id');
                            if ($cuotaSeleccionadaId) {
                                $cuotaSeleccionada = $cuotas->firstWhere('id', $cuotaSeleccionadaId);
                                if ($cuotaSeleccionada && $primeraPendiente && $cuotaSeleccionada->id != $primeraPendiente->id) {
                                    Notification::make()
                                        ->title('No puedes registrar el pago de esta cuota')
                                        ->body('Debes pagar primero la cuota anterior en mora o con saldo pendiente.')
                                        ->danger()
                                        ->persistent()
                                        ->send();
                                    $set('grupo_id', null);
                                    $set('cuota_grupal_id', null);
                                    $set('numero_cuota', null);
                                    $set('monto_cuota', null);
                                    $set('monto_mora_pagada', 0.00);
                                    $set('saldo_pendiente_actual', 0.00);
                                    $set('monto_pagado', 0.00);
                                    $set('tipo_pago', null);
                                    return;
                                }
                            }

                            // Si no hay cuota seleccionada, o es la primera pendiente, setear normalmente
                            if ($cuotas->count() > 0) {
                                foreach ($cuotas as $cuota) {
                                    $saldoPendiente = $cuota->saldoPendiente();
                                    if ($saldoPendiente > 0) {
                                        $set('cuota_grupal_id', $cuota->id);
                                        $set('numero_cuota', $cuota->numero_cuota);
                                        $set('monto_cuota', $cuota->monto_cuota_grupal);
                                        $set('monto_mora_pagada', $cuota->getSaldoMoraPendiente());
                                        $set('saldo_pendiente_actual', $saldoPendiente);
                                        $tipoPago = $get('tipo_pago');
                                        if ($tipoPago === 'pago_completo') {
                                            $set('monto_pagado', $saldoPendiente);
                                        } else {
                                            $set('monto_pagado', null);
                                        }
                                        return;
                                    }
                                }
                            }
                            // Si no hay cuotas pendientes
                            $set('cuota_grupal_id', null);
                            $set('numero_cuota', null);
                            $set('monto_cuota', null);
                            $set('monto_mora_pagada', 0.00);
                            $set('saldo_pendiente_actual', 0.00);
                            $set('monto_pagado', 0.00);
                            $set('tipo_pago', null);

                            // Auto-llenar observaciones si hay retanqueo
                            [$grupoIdReal, $prestamoId] = explode('_', $state, 2);
                            $prestamo = \App\Models\Prestamo::find($prestamoId);
                            if ($prestamo && $prestamo->estado === 'Ejecutado') {
                                $mensajeRetanqueo = $prestamo->generarMensajeRetanqueoPago();
                                $observacionesActuales = $get('observaciones') ?? '';

                                // Solo añadir si no existe ya el mensaje
                                if (!str_contains($observacionesActuales, 'Cobertura automática por retanqueo')) {
                                    $nuevasObservaciones = $observacionesActuales ?
                                        $observacionesActuales . "\n\n" . $mensajeRetanqueo :
                                        $mensajeRetanqueo;
                                    $set('observaciones', $nuevasObservaciones);
                                }
                            }
                        })
                        ->searchable()
                        ->required()
                        ->live(onBlur: true)  // Optimizado: solo actualiza al confirmar selección
                        ->disabled(function ($record) {
                            $user = request()->user();
                            return $record !== null && (
                                strtolower($record->estado_pago) !== 'pendiente' ||
                                $user->hasAnyRole(['super_admin', 'Jefe de operaciones', 'Jefe de creditos'])
                            );
                        }),

                    Hidden::make('cuota_grupal_id')->required(),

                    TextInput::make('numero_cuota')
                        ->label('Número de Cuota')
                        ->disabled(true)
                        ->prefixIcon('heroicon-o-hashtag')
                        ->numeric()
                        ->required()
                        ->dehydrated()
                        ->afterStateHydrated(function ($component, $state, $record) {
                            if ($record && $record->cuotaGrupal) {
                                $component->state($record->cuotaGrupal->numero_cuota);
                            }
                        }),

                    TextInput::make('monto_cuota')
                        ->label('Monto de la Cuota')
                        ->prefix('S/.')
                        ->numeric()
                        ->disabled(true)
                        ->required()
                        ->dehydrated()
                        ->afterStateHydrated(function ($component, $state, $record) {
                            if ($record && $record->cuotaGrupal) {
                                $component->state($record->cuotaGrupal->monto_cuota_grupal);
                            }
                        }),

                    TextInput::make('monto_mora_pagada')
                        ->label('Monto de Mora Pendiente')
                        ->prefix('S/.')
                        ->numeric()
                        ->disabled(true)
                        ->dehydrated(true)
                        ->default(function (callable $get) {
                            $cuotaId = $get('cuota_grupal_id');
                            if ($cuotaId && $cuota = CuotasGrupales::with('mora')->find($cuotaId)) {
                                return $cuota->getSaldoMoraPendiente();
                            }
                            return 0.00;
                        })
                        ->afterStateHydrated(function ($component, $state, $record, callable $get) {
                            if ($record && $record->cuotaGrupal) {
                                // Para registros existentes, mostrar el monto que se pagó en ese registro específico
                                $component->state($record->monto_mora_pagada ?? 0);
                            } else {
                                $cuotaId = $get('cuota_grupal_id');
                                if ($cuotaId && $cuota = CuotasGrupales::with('mora')->find($cuotaId)) {
                                    $component->state($cuota->getSaldoMoraPendiente());
                                } else {
                                    $component->state(0.00);
                                }
                            }
                        }),

                    TextInput::make('saldo_pendiente_actual')
                        ->label('Saldo Pendiente')
                        ->numeric()
                        ->disabled(true)
                        ->dehydrated(false)
                        ->prefix('S/.')
                        ->afterStateHydrated(function ($component, $state, $record, callable $get) {
                            if ($record && $record->cuotaGrupal) {
                                $component->state($record->cuotaGrupal->saldoPendiente());
                            } else {
                                $cuotaId = $get('cuota_grupal_id');
                                if ($cuotaId && $cuota = CuotasGrupales::with('mora')->find($cuotaId)) {
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
                        ->live(onBlur: true)  // Optimizado: solo actualiza al confirmar selección
                        ->dehydrated(true)
                        ->afterStateUpdated(function ($state, callable $set, callable $get) {
                            // Autollenar detalles_pago al cambiar tipo de pago
                            $grupoEstado = $get('grupo_id');
                            if ($grupoEstado && str_contains($grupoEstado, '_')) {
                                [$grupoId, $prestamoId] = explode('_', $grupoEstado, 2);
                                $integrantes = \App\Models\PrestamoIndividual::where('prestamo_id', $prestamoId)->with('cliente.persona')->get();
                                $set('detalles_pago', $integrantes->map(function ($pi) use ($state) {
                                    $nombre = 'Sin nombre';
                                    if ($pi->cliente && $pi->cliente->persona) {
                                        $nombre = trim(($pi->cliente->persona->nombre ?? '') . ' ' . ($pi->cliente->persona->apellidos ?? '')) ?: 'Sin nombre';
                                    }
                                    return [
                                        'prestamo_individual_id' => $pi->id,
                                        'nombre_integrante' => $nombre,
                                        // ✅ VERIFICAR: Este debe ser el monto individual de cada integrante
                                        'monto_pagado' => $state === 'pago_completo' ? $pi->monto_cuota_prestamo_individual : 0,
                                    ];
                                })->toArray());
                            }
                            $cuotaId = $get('cuota_grupal_id');
                            if (!$cuotaId) {
                                // Si no hay cuota seleccionada, intentar recargar desde el grupo
                                $grupoEstado = $get('grupo_id');
                                if ($grupoEstado && str_contains($grupoEstado, '_')) {
                                    [$grupoId, $prestamoId] = explode('_', $grupoEstado, 2);

                                    // Obtener el préstamo para verificar si es un retanqueo parcial
                                    $prestamo = \App\Models\Prestamo::find($prestamoId);

                                    // Para préstamos parcialmente retanqueados, usar lógica especial
                                    if ($prestamo && $prestamo->estado === 'Ejecutado') {
                                        // Buscar todas las cuotas que no estén completamente pagadas
                                        $todasLasCuotas = CuotasGrupales::whereHas('prestamo', function ($query) use ($grupoId, $prestamoId) {
                                            $query->where('grupo_id', $grupoId)->where('id', $prestamoId);
                                        })
                                            ->where('estado_pago', '!=', 'pagado')
                                            ->orderBy('numero_cuota', 'asc')
                                            ->get();

                                        // Filtrar manualmente las que tienen saldo pendiente
                                        $cuotas = collect();
                                        foreach ($todasLasCuotas as $cuota) {
                                            if ($cuota->saldoPendiente() > 0) {
                                                $cuotas->push($cuota);
                                            }
                                        }
                                    } else {
                                        // Para préstamos normales, usar la lógica original
                                        $cuotas = CuotasGrupales::whereHas('prestamo', function ($query) use ($grupoId, $prestamoId) {
                                            $query->where('grupo_id', $grupoId)->where('id', $prestamoId);
                                        })
                                            ->whereIn('estado_cuota_grupal', ['vigente', 'mora'])
                                            ->orderBy('numero_cuota', 'asc')
                                            ->get();
                                    }

                                    // Buscar la primera cuota con saldo pendiente
                                    if ($cuotas->count() > 0) {
                                        foreach ($cuotas as $cuota) {
                                            $saldoPendiente = $cuota->saldoPendiente();
                                            if ($saldoPendiente > 0) {
                                                $set('cuota_grupal_id', $cuota->id);
                                                $set('numero_cuota', $cuota->numero_cuota);
                                                $set('monto_cuota', $cuota->monto_cuota_grupal);
                                                $set('monto_mora_pagada', $cuota->getSaldoMoraPendiente());
                                                $set('saldo_pendiente_actual', $saldoPendiente);
                                                $cuotaId = $cuota->id;
                                                break;
                                            }
                                        }
                                    }
                                }
                            }

                            if (!$cuotaId)
                                return;

                            $cuota = CuotasGrupales::with('mora')->find($cuotaId);
                            if (!$cuota)
                                return;

                            $montoCuota = floatval($cuota->monto_cuota_grupal);
                            $saldoMoraPendiente = $cuota->getSaldoMoraPendiente();
                            $saldoCuotaPendiente = $cuota->getSaldoCuotaPendiente();
                            $saldoPendiente = $saldoMoraPendiente + $saldoCuotaPendiente;

                            if ($state === 'pago_completo') {
                                $set('monto_pagado', $saldoPendiente);
                                $set('monto_mora_pagada', $saldoMoraPendiente);
                            } elseif ($state === 'pago_parcial') {
                                $set('monto_mora_pagada', $saldoMoraPendiente);
                                $set('monto_pagado', null);
                            }

                            // Auto-llenar observaciones si es retanqueo parcial
                            $grupoEstado = $get('grupo_id');
                            if ($grupoEstado && str_contains($grupoEstado, '_')) {
                                [$grupoIdReal, $prestamoId] = explode('_', $grupoEstado, 2);
                                $prestamo = \App\Models\Prestamo::find($prestamoId);
                                if ($prestamo && $prestamo->estado === 'Ejecutado') {
                                    $mensajeRetanqueo = $prestamo->generarMensajeRetanqueoPago();
                                    $observacionesActuales = $get('observaciones') ?? '';

                                    // Solo añadir si no existe ya el mensaje
                                    if ($mensajeRetanqueo && !str_contains($observacionesActuales, 'Cobertura automática por retanqueo')) {
                                        $nuevasObservaciones = $observacionesActuales ?
                                            $observacionesActuales . "\n\n" . $mensajeRetanqueo :
                                            $mensajeRetanqueo;
                                        $set('observaciones', $nuevasObservaciones);
                                    }
                                }
                            }
                        })
                        ->disabled(function ($record) {
                            $user = request()->user();
                            return $record !== null && (
                                strtolower($record->estado_pago) !== 'pendiente' ||
                                $user->hasAnyRole(['super_admin', 'Jefe de operaciones', 'Jefe de creditos'])
                            );
                        }),

                    TextInput::make('monto_pagado')
                        ->label('Monto Pagado Total')
                        ->prefix('S/.')
                        ->numeric()
                        ->minValue(0)
                        ->rules(['numeric', 'min:0.01'])
                        ->extraAttributes([
                            'onkeydown' => "if (event.key === '-' || event.key === 'e') event.preventDefault();",
                            'inputmode' => 'decimal',
                        ])
                        ->required()
                        ->disabled(function (callable $get, $record) {
                            $user = request()->user();

                            // Si es super_admin o jefe, siempre deshabilitar
                            if ($user->hasAnyRole(['Jefe de creditos'])) {
                                return true;
                            }

                            if ($record !== null && strtolower($record->estado_pago) !== 'pendiente') {
                                return true;
                            }

                            // CAMBIO PRINCIPAL: Deshabilitar tanto para pago_completo como para pago_parcial
                            return in_array($get('tipo_pago'), ['pago_completo', 'pago_parcial']);
                        })
                        ->dehydrated()
                        ->live(onBlur: true)
                        ->afterStateUpdated(function ($state, callable $set, callable $get) {
                            $saldoPendiente = floatval($get('saldo_pendiente_actual') ?? 0);
                            $montoPagado = floatval($state ?? 0);

                            if ($montoPagado > $saldoPendiente && $saldoPendiente > 0) {
                                $set('monto_pagado', $saldoPendiente);
                            }
                        })
                        ->helperText(function (callable $get) {
                            $saldoPendiente = $get('saldo_pendiente_actual');
                            $tipoPago = $get('tipo_pago');

                            if ($tipoPago === 'pago_parcial') {
                                return 'Este campo se calculará automáticamente sumando los montos individuales';
                            }

                            if ($saldoPendiente > 0) {
                                return 'Máximo a pagar: S/. ' . number_format($saldoPendiente, 2);
                            }
                            return null;
                        }),
                    TextInput::make('codigo_operacion')
                        ->label('Código de Operación')
                        ->prefixIcon('heroicon-o-finger-print')
                        ->required()
                        ->maxLength(255)
                        ->afterStateHydrated(function ($component, $state, $record) {
                            if ($record && $record->codigo_operacion) {
                                $component->state($record->codigo_operacion);
                            }
                        })
                        ->disabled(function ($record) {
                            $user = request()->user();
                            return $record !== null && (
                                strtolower($record->estado_pago) !== 'pendiente' ||
                                $user->hasAnyRole(['super_admin', 'Jefe de operaciones', 'Jefe de creditos'])
                            );
                        }),

                    DateTimePicker::make('fecha_pago')
                        ->label('Fecha de Pago')
                        ->prefixIcon('heroicon-o-calendar-days')
                        ->required()
                        ->dehydrated(true)
                        ->maxDate(now())
                        ->disabled(function ($record) {
                            $user = request()->user();
                            return $record !== null && (
                                strtolower($record->estado_pago) !== 'pendiente' ||
                                $user->hasAnyRole(['super_admin', 'Jefe de operaciones', 'Jefe de creditos'])
                            );
                        })
                        ->default(function () {
                            return now()->format('Y-m-d H:i:s');
                        }),

                    TextInput::make('observaciones')
                        ->label('Observaciones')
                        ->prefixIcon('heroicon-o-pencil-square')
                        ->maxLength(255)
                        ->default(function (callable $get) {
                            // Auto-llenar observaciones cuando hay retanqueo parcial
                            $grupoId = $get('grupo_id');
                            if (!$grupoId || !str_contains($grupoId, '_')) {
                                return '';
                            }

                            [$grupoIdReal, $prestamoId] = explode('_', $grupoId, 2);
                            $prestamo = \App\Models\Prestamo::find($prestamoId);

                            if ($prestamo && $prestamo->estado === 'Ejecutado') {
                                return $prestamo->generarMensajeRetanqueoPago();
                            }

                            return '';
                        })
                        ->disabled(function ($record) {
                            $user = request()->user();
                            return $record !== null && (
                                strtolower($record->estado_pago) !== 'pendiente' ||
                                $user->hasAnyRole(['super_admin', 'Jefe de operaciones', 'Jefe de creditos'])
                            );
                        }),

                    Select::make('estado_pago')
                        ->label('Estado del Pago')
                        ->prefixIcon('heroicon-o-check-badge')
                        ->options([
                            'pendiente' => 'Pendiente',
                            'aprobado' => 'Aprobado',
                            'rechazado' => 'Rechazado',
                        ])
                        ->default('pendiente')
                        ->disabled(true)
                        ->dehydrated(),
                ])
                ->columns(2), // Usar dos columnas para los campos principales

            Section::make('Detalle de Pago por Integrante')
                ->description('Distribución del pago entre los integrantes del grupo')
                ->schema([
                    Repeater::make('detalles_pago')
                        ->label('Detalle de pago por integrante')
                        ->relationship('detallesPago')
                        ->schema([
                            Hidden::make('prestamo_individual_id'),


                            \Filament\Forms\Components\Placeholder::make('nombre_integrante')
                                ->label('Integrante')
                                ->content(function (callable $get) {
                                    $prestamoIndId = $get('prestamo_individual_id');
                                    if (!$prestamoIndId)
                                        return 'Sin nombre';
                                    $pi = \App\Models\PrestamoIndividual::with('cliente.persona')->find($prestamoIndId);
                                    if (!$pi || !$pi->cliente || !$pi->cliente->persona)
                                        return 'Sin nombre';
                                    return trim(($pi->cliente->persona->nombre ?? '') . ' ' . ($pi->cliente->persona->apellidos ?? '')) ?: 'Sin nombre';
                                }),


                            TextInput::make('monto_pagado')
                                ->label('Monto Pagado')
                                ->prefix('S/.')
                                ->numeric()
                                ->required()
                                ->minValue(0)
                                ->rules(['numeric', 'min:0'])
                                ->extraAttributes([
                                    'onkeydown' => "if (event.key === '-' || event.key === 'e' || event.key === 'E' || event.key === '+') event.preventDefault();",
                                    'inputmode' => 'decimal',
                                    'pattern' => '[0-9]*\.?[0-9]*'
                                ])
                                ->disabled(function (callable $get) {
                                    // CAMBIO PRINCIPAL: Deshabilitar solo cuando es pago_completo
                                    return $get('../../tipo_pago') === 'pago_completo';
                                })
                                ->dehydrated(true)
                                ->default(function (callable $get) {
                                    if ($get('../../tipo_pago') === 'pago_completo') {
                                        $prestamoIndId = $get('prestamo_individual_id');
                                        $pi = $prestamoIndId ? \App\Models\PrestamoIndividual::find($prestamoIndId) : null;
                                        // ✅ VERIFICAR: Debe retornar el monto individual de cada integrante
                                        return $pi ? $pi->monto_cuota_prestamo_individual : 0;
                                    }
                                    return 0;
                                })
                                ->live(onBlur: true)
                                // NUEVO: Agregar afterStateUpdated para calcular la suma automática
                                ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                    // Solo calcular suma si es pago parcial
                                    if ($get('../../tipo_pago') !== 'pago_parcial') {
                                        return;
                                    }

                                    // Obtener todos los detalles de pago
                                    $detallesPago = $get('../../detalles_pago') ?? [];
                                    $sumaTotal = 0;

                                    // Sumar todos los montos individuales
                                    foreach ($detallesPago as $detalle) {
                                        if (isset($detalle['monto_pagado']) && is_numeric($detalle['monto_pagado'])) {
                                            $sumaTotal += floatval($detalle['monto_pagado']);
                                        }
                                    }

                                    // Actualizar el monto total pagado
                                    $set('../../monto_pagado', $sumaTotal);

                                    // Validar que no exceda el saldo pendiente
                                    $saldoPendiente = floatval($get('../../saldo_pendiente_actual') ?? 0);
                                    if ($sumaTotal > $saldoPendiente && $saldoPendiente > 0) {
                                        // Mostrar notificación de advertencia
                                        \Filament\Notifications\Notification::make()
                                            ->title('Monto excedido')
                                            ->body('La suma de los pagos individuales no puede exceder el saldo pendiente de S/. ' . number_format($saldoPendiente, 2))
                                            ->warning()
                                            ->send();
                                    }
                                })
                                ->placeholder(function (callable $get) {
                                    return $get('../../tipo_pago') === 'pago_parcial' ? '' : '';
                                })
                                ->helperText(function (callable $get) {
                                    if ($get('../../tipo_pago') === 'pago_parcial') {
                                        return 'Ingrese el monto ';
                                    }
                                    return null;
                                }),
                        ])
                        ->minItems(function (callable $get) {
                            $grupoPrestamo = $get('grupo_id');
                            if (!$grupoPrestamo || !str_contains($grupoPrestamo, '_'))
                                return 0;
                            [$grupoId, $prestamoId] = explode('_', $grupoPrestamo, 2);
                            return \App\Models\PrestamoIndividual::where('prestamo_id', $prestamoId)->count();
                        })
                        ->maxItems(function (callable $get) {
                            $grupoPrestamo = $get('grupo_id');
                            if (!$grupoPrestamo || !str_contains($grupoPrestamo, '_'))
                                return 0;
                            [$grupoId, $prestamoId] = explode('_', $grupoPrestamo, 2);
                            return \App\Models\PrestamoIndividual::where('prestamo_id', $prestamoId)->count();
                        })
                        ->grid(4)
                        ->defaultItems(0)
                        ->addable(false)
                        ->deletable(false)
                        ->reorderable(false)
                        ->hidden(function (callable $get) {
                            $grupoPrestamo = $get('grupo_id');
                            return !$grupoPrestamo || !str_contains($grupoPrestamo, '_');
                        })
                        ->afterStateHydrated(function ($component, $state, $record, callable $get) {
                            if (!$state && $get('grupo_id') && str_contains($get('grupo_id'), '_')) {
                                [$grupoId, $prestamoId] = explode('_', $get('grupo_id'), 2);
                                $integrantes = \App\Models\PrestamoIndividual::where('prestamo_id', $prestamoId)->get();
                                $tipoPago = $get('tipo_pago');
                                $component->state($integrantes->map(function ($pi) use ($tipoPago) {
                                    return [
                                        'prestamo_individual_id' => $pi->id,
                                        'monto_pagado' => $tipoPago === 'pago_completo' ? $pi->monto_cuota_prestamo_individual : 0,
                                        'estado_pago_individual' => $tipoPago === 'pago_completo' ? 'Pagada' : null,
                                    ];
                                })->toArray());
                            }
                        })
                        // NUEVO: Agregar validación personalizada para asegurar que la suma no exceda el saldo
                        ->rules([
                            function (callable $get) {
                                return function (string $attribute, $value, \Closure $fail) use ($get) {
                                    if ($get('tipo_pago') !== 'pago_parcial') {
                                        return;
                                    }

                                    $saldoPendiente = floatval($get('saldo_pendiente_actual') ?? 0);
                                    $sumaTotal = 0;

                                    if (is_array($value)) {
                                        foreach ($value as $detalle) {
                                            if (isset($detalle['monto_pagado']) && is_numeric($detalle['monto_pagado'])) {
                                                $sumaTotal += floatval($detalle['monto_pagado']);
                                            }
                                        }
                                    }

                                    if ($sumaTotal > $saldoPendiente) {
                                        $fail('La suma de los pagos individuales (S/. ' . number_format($sumaTotal, 2) . ') no puede exceder el saldo pendiente (S/. ' . number_format($saldoPendiente, 2) . ')');
                                    }

                                    if ($sumaTotal <= 0) {
                                        $fail('Debe ingresar al menos un monto mayor a 0 para algún integrante');
                                    }
                                };
                            }
                        ]),
                ]), // Cierre de la segunda sección

            TextInput::make('saldo_pendiente')
                ->label('Saldo Pendiente (Total a Pagar)')
                ->numeric()
                ->disabled(function ($record) {
                    $user = request()->user();
                    return $record !== null && (
                        strtolower($record->estado_pago) !== 'pendiente' ||
                        $user->hasAnyRole(['super_admin', 'Jefe de operaciones', 'Jefe de creditos'])
                    );
                })
                ->dehydrated(false)
                ->visible(fn($record) => $record !== null && false)
                ->afterStateHydrated(function ($component, $state, $record) {
                    if ($record && $record->cuotaGrupal) {
                        $cuota = $record->cuotaGrupal->fresh();
                        $saldo = floatval($cuota->saldo_pendiente);
                        $mora = $cuota->mora ? abs($cuota->mora->monto_mora_calculado) : 0;

                        if (strtolower($record->estado_pago) === 'pendiente') {
                            $component->state($saldo + $mora);
                        } else {
                            $pagosAprobados = $cuota->pagos()->where('estado_pago', 'Aprobado')->sum('monto_pagado');
                            $saldoReal = round(max(($saldo + $mora) - $pagosAprobados, 0), 2);
                            $component->state($saldoReal);
                        }
                    } else {
                        $component->state(null);
                    }
                })
        ]); // Cierre del schema principal del formulario
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
                    ->width('65px')
                    ->badge()
                    ->color(function (string $state): string {
                        $normalizedState = strtolower(trim($state));
                        return match ($normalizedState) {
                            'pago_completo' => 'success',
                            'pago_parcial' => 'warning',
                            default => 'danger',
                        };
                    })
                    ->formatStateUsing(function (string $state): string {
                        $normalizedState = strtolower(trim($state));
                        return match ($normalizedState) {
                            'pago_completo' => 'Completo',
                            'pago_parcial' => 'Parcial',
                            default => 'ERROR: ' . $state,
                        };
                    }),
                Tables\Columns\TextColumn::make('codigo_operacion')
                    ->label('Cód. Oper')
                    ->alignLeft()
                    ->searchable()
                    ->width('100px'),
                Tables\Columns\TextColumn::make('fecha_pago')
                    ->label('F. Pago')
                    ->dateTime('d/m/Y')

                    ->sortable()
                    ->alignLeft()
                    ->default(now())
                    ->width('75px'),

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
                    ->formatStateUsing(fn($state) => 'S/. ' . number_format($state, 2))
                    ->width('70px'),

                Tables\Columns\TextColumn::make('cuotaGrupal.mora.monto_mora_calculado')
                    ->label('Mora')
                    ->alignLeft()
                    ->formatStateUsing(function ($state, $record) {
                        $mora = $record->cuotaGrupal && $record->cuotaGrupal->mora ? $record->cuotaGrupal->mora : null;
                        // Siempre mostrar el monto de mora calculado, aunque esté pagada
                        if (!$mora || !isset($mora->monto_mora_calculado)) {
                            return 'S/. ' . number_format(0, 2);
                        }
                        return 'S/. ' . number_format(abs($mora->monto_mora_calculado), 2);
                    })
                    ->width('65px'),

                Tables\Columns\TextColumn::make('cuotaGrupal.monto_total_a_pagar')
                    ->label('Total')
                    ->alignLeft()
                    ->formatStateUsing(function ($state, $record) {
                        $cuota = $record->cuotaGrupal;
                        $saldo = $cuota ? floatval($cuota->monto_cuota_grupal) : 0;
                        $mora = $cuota && $cuota->mora ? abs($cuota->mora->monto_mora_calculado) : 0;
                        // Siempre mostrar la suma cuota + mora, aunque ya esté pagada
                        return 'S/. ' . number_format(round($saldo + $mora, 2), 2);
                    })
                    ->width('75px'),

                Tables\Columns\TextColumn::make('monto_pagado')
                    ->label('Pagado')
                    ->alignLeft()
                    ->searchable()
                    ->formatStateUsing(fn($state) => 'S/. ' . number_format($state, 2))
                    ->width('65px'),



                Tables\Columns\TextColumn::make('cuotaGrupal.saldo_pendiente')
                    ->label('Saldo')
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

                        $saldo = round(max(($montoCuota + $montoMora) - $pagosAprobados, 0), 2);

                        return 'S/. ' . number_format($saldo, 2);
                    })
                    ->width('70px'),

                Tables\Columns\TextColumn::make('estado_pago')
                    ->label('Estado')
                    ->alignLeft()
                    ->searchable()
                    ->width('60px')
                    ->badge()
                    ->color(function (string $state): string {
                        $normalizedState = strtolower(trim($state));
                        return match ($normalizedState) {
                            'pendiente' => 'warning',
                            'aprobado' => 'success',
                            'rechazado' => 'danger',
                            default => 'danger',
                        };
                    })
                    ->formatStateUsing(function (string $state): string {
                        $normalizedState = strtolower(trim($state));
                        return match ($normalizedState) {
                            'pendiente' => 'Pendiente',
                            'aprobado' => 'Aprobado',
                            'rechazado' => 'Rechazado',
                            default => $state, // Mostrar el valor tal como está
                        };
                    }),
            ])
            ->filters([
                Tables\Filters\Filter::make('fecha_pago')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('Desde'),
                        Forms\Components\DatePicker::make('until')->label('Hasta'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'], fn($q, $date) => $q->whereDate('fecha_pago', '>=', $date))
                            ->when($data['until'], fn($q, $date) => $q->whereDate('fecha_pago', '<=', $date));
                    }),
            ])
            ->actions([
                ActionGroup::make([
                    Tables\Actions\EditAction::make(),
                    Action::make('aprobar')
                        ->label('Aprobar')
                        ->icon('heroicon-m-check-circle')
                        ->color('success')
                        ->visible(fn($record) => in_array(strtolower($record->estado_pago), ['pendiente']) && request()->user()?->hasAnyRole(['super_admin', 'Jefe de operaciones']))
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
                        ->visible(fn($record) => in_array(strtolower($record->estado_pago), ['pendiente']) && request()->user()?->hasAnyRole(['super_admin', 'Jefe de operaciones']))
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

        // Eager loading de relaciones para evitar N+1 queries
        $query = parent::getEloquentQuery()
            ->with([
                'cuotaGrupal',
                'cuotaGrupal.prestamo',
                'cuotaGrupal.prestamo.grupo',
                'cuotaGrupal.mora',
                'detallesPago',
                'detallesPago.prestamoIndividual',
                'detallesPago.prestamoIndividual.cliente',
                'detallesPago.prestamoIndividual.cliente.persona',
            ]);

        if ($user->hasRole('Asesor')) {
            $asesor = \App\Models\Asesor::where('user_id', $user->id)->first();

            if ($asesor) {
                $query->whereHas('cuotaGrupal.prestamo.grupo', function ($subQuery) use ($asesor) {
                    $subQuery->where('asesor_id', $asesor->id);
                });
            }
        }

        return $query->orderBy('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPagos::route('/'),
            'create' => Pages\CreatePago::route('/crear'),
            'edit' => Pages\EditPago::route('/{record}/editar'),
            'grupo-detalle' => Pages\GrupoDetallePagos::route('/grupo/{grupo}/{prestamo}'),
        ];
    }
}
