<?php

namespace App\Filament\Dashboard\Resources;

use App\Filament\Dashboard\Resources\RetanqueoResource\Pages;
use App\Models\Retanqueo;
use App\Models\Grupo;
use App\Models\Prestamo;
use App\Contracts\RetanqueoQueryInterface;
use App\Contracts\RetanqueoWorkflowInterface;
use App\Contracts\RetanqueoEjecucionInterface;
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

    protected static ?string $navigationGroup = 'Operaciones';
    protected static ?string $navigationIcon = 'heroicon-o-arrow-path';

    protected static ?string $navigationLabel = 'Retanqueos';

    protected static ?string $modelLabel = 'Retanqueo';

    protected static ?string $pluralModelLabel = 'Retanqueos';

    protected static ?int $navigationSort = 4; // Después de Préstamos

    public static function form(Form $form): Form
    {
        $user = request()->user();
        $retanqueoService = app(RetanqueoQueryInterface::class);

        return $form
            ->schema([
                // Información crítica sobre restricciones de retanqueo
                Forms\Components\Placeholder::make('restriccion_retanqueo')
                    ->label('⚠️ RESTRICCIÓN IMPORTANTE DE RETANQUEOS')
                    ->content(new \Illuminate\Support\HtmlString(
                        '<div style="background-color: #fef3c7; border: 2px solid #f59e0b; border-radius: 8px; padding: 16px; margin-bottom: 20px;">
                            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px;">
                                <svg style="width: 24px; height: 24px; color: #f59e0b;" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M8.485 3.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 3.495zM10 6a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 6zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"></path>
                                </svg>
                                <strong style="color: #92400e; font-size: 16px;">POLÍTICA FINANCIERA DE RETANQUEOS</strong>
                            </div>
                            <ul style="margin: 0; padding-left: 24px; color: #92400e; font-size: 14px;">
                                <li><strong>✅ REQUISITO OBLIGATORIO:</strong> Solo se pueden retanquear préstamos que tengan <strong>EXACTAMENTE 1 cuota pendiente por pagar</strong></li>
                                <li><strong>❌ NO ELEGIBLES:</strong> Préstamos con 2 o más cuotas pendientes</li>
                                <li><strong>🔒 SEGURIDAD:</strong> Esta restricción protege la estabilidad financiera del sistema</li>
                            </ul>
                            <p style="margin: 12px 0 0 0; color: #92400e; font-size: 13px; font-style: italic;">
                                📋 Si no ve ningún préstamo disponible, significa que ningún grupo cumple con este requisito.
                            </p>
                        </div>'
                    ))
                    ->columnSpanFull(),

                Section::make('Información de la Solicitud')
                    ->description('Detalles generales del retanqueo')
                    ->icon('heroicon-o-information-circle')
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 2])
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
                                                // Solo mostrar el nombre del grupo, la información detallada se ve más abajo
                                                $opciones[$prestamoActivo->id] = $grupo->nombre_grupo;
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
                                                $set('cantidad_cuotas_nuevo', 4);
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
                                    ->helperText('🔍 Solo aparecen préstamos con EXACTAMENTE 1 cuota pendiente (requisito obligatorio para retanqueo)'),

                                TextInput::make('cantidad_cuotas_nuevo')
                                    ->label('Cantidad de Cuotas (Nuevo Préstamo)')
                                    ->prefixIcon('heroicon-o-calendar-days')
                                    ->numeric()
                                    ->required()
                                    ->default(4)
                                    ->disabled()
                                    ->helperText('Fijo en 4 cuotas semanales (consistente con préstamos regulares)')
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
                    ->visible(fn(callable $get) => !empty($get('estado_prestamo_info')))
                    ->collapsible(),

                Section::make('Configuración de Participantes')
                    ->description('Configure quién participará en el retanqueo y con qué montos')
                    ->icon('heroicon-o-users')
                    ->schema([
                        Repeater::make('participantes')
                            ->label('')
                            ->schema([
                                Grid::make(['default' => 2, 'sm' => 3, 'lg' => 6])
                                    ->schema([
                                        // Selector de cliente (solo visible para nuevos elementos)
                                        Select::make('cliente_id')
                                            ->label('Seleccionar Cliente')
                                            ->options(function (callable $get) {
                                                $user = request()->user();

                                                // Obtener el asesor actual
                                                $asesorId = null;
                                                if ($user->hasRole('Asesor')) {
                                                    $asesor = \App\Models\Asesor::where('user_id', $user->id)->first();
                                                    $asesorId = $asesor ? $asesor->id : null;
                                                }

                                                if (!$asesorId && !$user->hasAnyRole(['super_admin', 'Jefe de operaciones', 'Jefe de creditos'])) {
                                                    return []; // Solo asesores o admins pueden ver clientes
                                                }

                                                // Obtener clientes disponibles (no en grupos activos)
                                                $query = \App\Models\Cliente::with('persona')
                                                    ->whereHas('persona')
                                                    ->whereDoesntHave('grupos', function ($q) {
                                                    $q->whereNull('grupo_cliente.fecha_salida') // Cliente activo en el grupo
                                                        ->where('estado_grupo', 'Activo'); // Y el grupo está activo
                                                });

                                                // Filtrar por asesor si es necesario
                                                if ($asesorId) {
                                                    $query->where('asesor_id', $asesorId);
                                                }

                                                $clientesDisponibles = $query->get()
                                                    ->mapWithKeys(function ($cliente) {
                                                        return [
                                                            $cliente->id => $cliente->persona->nombre . ' ' . $cliente->persona->apellidos
                                                        ];
                                                    });

                                                return $clientesDisponibles;
                                            })
                                            ->searchable()
                                            ->required()
                                            ->reactive()
                                            ->rules([
                                                function (callable $get) {
                                                    return function (string $attribute, $value, \Closure $fail) use ($get) {
                                                        // Validar que no se duplique el cliente
                                                        $todosLosParticipantes = $get('../../participantes') ?? [];
                                                        $clientesSeleccionados = array_filter(array_column($todosLosParticipantes, 'cliente_id'));

                                                        if (count(array_keys($clientesSeleccionados, $value)) > 1) {
                                                            $cliente = \App\Models\Cliente::with('persona')->find($value);
                                                            $nombre = $cliente ? $cliente->persona->nombre . ' ' . $cliente->persona->apellidos : 'Cliente';
                                                            $fail("El cliente {$nombre} ya está seleccionado en otro participante.");
                                                        }

                                                        // Validar que el cliente no esté en un grupo activo
                                                        $enGrupoActivo = \App\Models\Cliente::find($value)
                                                            ->grupos()
                                                            ->whereNull('grupo_cliente.fecha_salida') // Cliente activo en el grupo
                                                            ->where('estado_grupo', 'Activo') // Y el grupo está activo
                                                            ->exists();

                                                        if ($enGrupoActivo) {
                                                            $cliente = \App\Models\Cliente::with('persona')->find($value);
                                                            $nombre = $cliente ? $cliente->persona->nombre . ' ' . $cliente->persona->apellidos : 'Cliente';
                                                            $fail("El cliente {$nombre} ya pertenece a otro grupo activo.");
                                                        }
                                                    };
                                                },
                                            ])
                                            ->afterStateUpdated(function ($state, callable $set) {
                                                if ($state) {
                                                    $cliente = \App\Models\Cliente::with('persona')->find($state);
                                                    if ($cliente && $cliente->persona) {
                                                        $set('nombre_completo', $cliente->persona->nombre . ' ' . $cliente->persona->apellidos);
                                                        $cicloNormalizado = \App\Helpers\CicloHelper::normalize($cliente->ciclo ?? 'I');
                                                        $set('ciclo', $cicloNormalizado);
                                                        $set('monto_maximo', \App\Helpers\CicloHelper::getMontoMaximo($cicloNormalizado));
                                                        $set('participacion_tipo', 'nueva');
                                                        $set('monto_solicitado', 400);
                                                    }
                                                }
                                            })
                                            ->visible(fn(callable $get) => empty($get('nombre_completo'))), // Solo visible si no hay nombre (nuevo elemento)

                                        Placeholder::make('nombre_completo')
                                            ->label('Cliente')
                                            ->content(fn(callable $get) => $get('nombre_completo') ?? 'Seleccione un cliente')
                                            ->visible(fn(callable $get) => !empty($get('nombre_completo'))), // Solo visible si hay nombre (elemento existente)

                                        Placeholder::make('ciclo')
                                            ->label('Ciclo')
                                            ->content(fn(callable $get) => $get('ciclo') ?? 'I'),

                                        Placeholder::make('monto_maximo')
                                            ->label('Monto Máximo')
                                            ->content(fn(callable $get) => 'S/ ' . number_format($get('monto_maximo') ?? 400, 0)),

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

                                        Select::make('monto_solicitado')
                                            ->label('Monto Solicitado')
                                            ->options(function (callable $get) {
                                                $ciclo = $get('ciclo');
                                                if (!$ciclo) {
                                                    return [];
                                                }
                                                $cicloNormalizado = \App\Helpers\CicloHelper::normalize($ciclo);
                                                return \App\Helpers\CicloHelper::getMontosPermitidosParaSelect($cicloNormalizado);
                                            })
                                            ->reactive()
                                            ->disabled(fn(callable $get) => $get('participacion_tipo') === 'no_retanquea')
                                            ->required(fn(callable $get) => $get('participacion_tipo') !== 'no_retanquea') // Solo requerido si participa
                                            ->placeholder('Selecciona un monto')
                                            ->rules([
                                                function (callable $get) {
                                                    return function (string $attribute, $value, \Closure $fail) use ($get) {
                                                        $participacionTipo = $get('participacion_tipo');

                                                        // Si no retanquea, no validar el monto (puede ser 0 o vacío)
                                                        if ($participacionTipo === 'no_retanquea') {
                                                            return;
                                                        }

                                                        $ciclo = $get('ciclo');
                                                        if (!$ciclo) {
                                                            return;
                                                        }

                                                        $cicloNormalizado = \App\Helpers\CicloHelper::normalize($ciclo);

                                                        // Validar que el monto sea válido solo si participa
                                                        if (!\App\Helpers\CicloHelper::validarMontoExacto($value, $cicloNormalizado)) {
                                                            $fail('El monto seleccionado no es válido para el ciclo ' . $cicloNormalizado);
                                                            return;
                                                        }

                                                        // Validación adicional: verificar acceso por ciclo
                                                        if (!\App\Helpers\CicloHelper::puedeAccederAMonto($value, $cicloNormalizado)) {
                                                            $cicloMinimo = \App\Helpers\CicloHelper::getCicloPorMonto($value);
                                                            $fail("El monto S/ {$value} está disponible desde el Ciclo {$cicloMinimo}. El cliente actual es Ciclo {$cicloNormalizado}.");
                                                        }
                                                    };
                                                },
                                            ]),

                                        Forms\Components\Hidden::make('cliente_id')
                                            ->afterStateHydrated(function (callable $set, callable $get, $state) {
                                                // Si no hay cliente_id pero hay nombre_completo, es un elemento existente
                                                if (!$state && $get('nombre_completo')) {
                                                    // Buscar el cliente_id basado en el nombre (para elementos existentes)
                                                    // Este campo se llenará automáticamente en el afterStateUpdated del selector
                                                }
                                            }),
                                    ])
                            ])
                            ->addable(true)
                            ->addActionLabel('Agregar Nuevo Cliente')
                            ->deletable(true)
                            ->reorderable(false)
                            ->collapsed(false)
                            ->cloneable(false)
                    ])
                    ->visible(fn(callable $get) => !empty($get('participantes')))
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
                                $prestamoId = $get('prestamo_id');

                                if (empty($participantes) || (!$estadoInfo && !$prestamoId)) {
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
                                    // Usar prestamo_id del formulario directamente 
                                    $prestamoAntiguo = \App\Models\Prestamo::find($prestamoId);
                                    if ($prestamoAntiguo) {
                                        $cuotasPendientes = $prestamoAntiguo->cuotasGrupales()->where('saldo_pendiente', '>', 0)->count();
                                        $totalCobertura = 0;

                                        foreach ($participantes as $participante) {
                                            if ($participante['participacion_tipo'] === 'retanquea') {
                                                // Buscar el préstamo individual del participante
                                                $prestamoIndividual = $prestamoAntiguo->prestamoIndividual()
                                                    ->where('cliente_id', $participante['cliente_id'])->first();
                                                if ($prestamoIndividual) {
                                                    $coberturaIndividual = $prestamoIndividual->monto_cuota_prestamo_individual * $cuotasPendientes;
                                                    $totalCobertura += $coberturaIndividual;
                                                }
                                            }
                                        }
                                    }
                                }

                                $saldoPendiente = $estadoInfo['saldo_pendiente'] ?? 0;
                                $montoAEntregar = $totalNuevoPrestamo - $totalCobertura;
                                $saldoRestante = $saldoPendiente - $totalCobertura;

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
                    ->visible(fn(callable $get) => !empty($get('participantes')))
                    ->collapsible(),

                // Campos de cuenta de desembolso
                Section::make('Información de Desembolso')
                    ->description('Datos bancarios para el desembolso del nuevo préstamo')
                    ->icon('heroicon-o-credit-card')
                    ->schema([
                        TextInput::make('titular_cuenta_desembolso')
                            ->label('Titular de la Cuenta a Desembolsar')
                            ->prefixIcon('heroicon-o-user')
                            ->placeholder('Ingrese el nombre del titular de la cuenta')
                            ->maxLength(255)
                            ->helperText('💳 Nombre completo del titular')
                            ->rule('regex:/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]*$/')
                            ->rule('min:3')
                            ->extraInputAttributes([
                                'onkeypress' => 'return /[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]/.test(event.key)',
                                'oninput' => 'this.value = this.value.replace(/[^a-zA-ZáéíóúÁÉÍÓÚñÑ\s]/g, "")'
                            ]),

                        TextInput::make('numero_cuenta_desembolso')
                            ->label('Número de la Cuenta a Desembolsar')
                            ->prefixIcon('heroicon-o-credit-card')
                            ->placeholder('Ingrese el número de cuenta (14 dígitos)')
                            ->maxLength(14)
                            ->minLength(14)
                            ->helperText('🏦 Número de cuenta bancaria')
                            ->rule('regex:/^[0-9]{14}$/')
                            ->numeric()
                            ->extraInputAttributes([
                                'onkeypress' => 'return /[0-9]/.test(event.key) && this.value.length < 14',
                                'oninput' => 'this.value = this.value.replace(/[^0-9]/g, "").substring(0, 14)'
                            ]),
                    ])
                    ->collapsible()
                    ->collapsed(false),

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
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

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
                    ->visible(fn() => !request()->user()->hasRole('Asesor')),

                TextColumn::make('monto_retanqueo')
                    ->label('Nuevo Préstamo')
                    ->money('PEN')
                    ->sortable(),

                TextColumn::make('monto_usado_para_cubrir_antiguo')
                    ->label('Cobertura')
                    ->money('PEN')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('monto_desembolsar')
                    ->label('A Entregar')
                    ->money('PEN')
                    ->sortable(),

                TextColumn::make('cantidad_cuotas_nuevo')
                    ->label('Cuotas')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                BadgeColumn::make('estado_retanqueo')
                    ->label('Estado')
                    ->colors([
                        'warning' => 'solicitud_pendiente',
                        'success' => 'aprobado',
                        'primary' => 'ejecutado',
                        'danger' => 'rechazado',
                    ])
                    ->formatStateUsing(fn(string $state): string => match ($state) {
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
                    ->placeholder('No aprobado')
                    ->toggleable(isToggledHiddenByDefault: true),
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
                                fn(Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn(Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                ActionGroup::make([
                    Tables\Actions\ViewAction::make()
                        ->icon('heroicon-m-eye'),

                    Tables\Actions\EditAction::make()
                        ->icon('heroicon-m-pencil-square')
                        ->visible(fn($record) => $record->esSolicitudPendiente()),

                    Action::make('aprobar')
                        ->label('Aprobar')
                        ->icon('heroicon-m-check-circle')
                        ->color('success')
                        ->visible(fn($record) => $record->esSolicitudPendiente() &&
                            request()->user()->hasAnyRole(['super_admin', 'Jefe de operaciones', 'Jefe de creditos']))
                        ->requiresConfirmation()
                        ->modalHeading('Aprobar Retanqueo')
                        ->modalDescription('¿Está seguro de que desea aprobar este retanqueo?')
                        ->action(function ($record) {
                            try {
                                app(RetanqueoWorkflowInterface::class)->aprobarRetanqueo($record->id);

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
                        ->visible(fn($record) => $record->esSolicitudPendiente() &&
                            request()->user()->hasAnyRole(['super_admin', 'Jefe de operaciones', 'Jefe de creditos']))
                        ->requiresConfirmation()
                        ->modalHeading('Rechazar Retanqueo')
                        ->modalDescription('¿Está seguro de que desea rechazar este retanqueo?')
                        ->action(function ($record) {
                            try {
                                app(RetanqueoWorkflowInterface::class)->rechazarRetanqueo($record->id);

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
                        ->visible(fn($record) => $record->estaAprobado() &&
                            request()->user()->hasAnyRole(['super_admin', 'Jefe de operaciones', 'Jefe de creditos']))
                        ->form(function ($record) {
                            // Verificar si ya existe un préstamo pendiente con datos
                            $tienePrestamoPendiente = $record->prestamo_nuevo_id &&
                                \App\Models\Prestamo::where('id', $record->prestamo_nuevo_id)
                                    ->where('estado', 'Pendiente')
                                    ->exists();

                            if ($tienePrestamoPendiente) {
                                $prestamoPendiente = \App\Models\Prestamo::find($record->prestamo_nuevo_id);
                                $tieneDatosCuenta = $prestamoPendiente &&
                                    !empty($prestamoPendiente->titular_cuenta_desembolso) &&
                                    !empty($prestamoPendiente->numero_cuenta_desembolso);

                                if ($tieneDatosCuenta) {
                                    // No pedir datos, ya están guardados
                                    return [];
                                }
                            }

                            // Pedir datos de cuenta
                            return [
                                Forms\Components\Section::make('Información de Desembolso')
                                    ->description('Datos bancarios para el desembolso del nuevo préstamo')
                                    ->schema([
                                        TextInput::make('titular_cuenta_desembolso')
                                            ->label('Titular de la Cuenta a Desembolsar')
                                            ->prefixIcon('heroicon-o-user')
                                            ->placeholder('Ingrese el nombre del titular de la cuenta')
                                            ->maxLength(255)
                                            ->helperText('💳 Nombre completo del titular (solo letras y espacios)')
                                            ->rule('regex:/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]*$/')
                                            ->rule('min:3')
                                            ->extraInputAttributes([
                                                'onkeypress' => 'return /[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]/.test(event.key)',
                                                'oninput' => 'this.value = this.value.replace(/[^a-zA-ZáéíóúÁÉÍÓÚñÑ\s]/g, "")'
                                            ]),

                                        TextInput::make('numero_cuenta_desembolso')
                                            ->label('Número de la Cuenta a Desembolsar')
                                            ->prefixIcon('heroicon-o-credit-card')
                                            ->placeholder('Ingrese el número de cuenta (14 dígitos)')
                                            ->maxLength(14)
                                            ->minLength(14)
                                            ->helperText('🏦 Número de cuenta bancaria (exactamente 14 números)')
                                            ->rule('regex:/^[0-9]{14}$/')
                                            ->numeric()
                                            ->extraInputAttributes([
                                                'onkeypress' => 'return /[0-9]/.test(event.key) && this.value.length < 14',
                                                'oninput' => 'this.value = this.value.replace(/[^0-9]/g, "").substring(0, 14)'
                                            ]),
                                    ])
                            ];
                        })
                        ->requiresConfirmation()
                        ->modalHeading('Ejecutar Retanqueo')
                        ->modalDescription('Complete la información de desembolso y confirme la ejecución del retanqueo.')
                        ->modalSubmitActionLabel('Ejecutar Retanqueo')
                        ->action(function ($record, array $data) {
                            try {
                                // SEGURO: Preparar datos de cuenta con validación
                                $datosCuenta = [];
                                if (!empty($data['titular_cuenta_desembolso'])) {
                                    $datosCuenta['titular_cuenta_desembolso'] = trim($data['titular_cuenta_desembolso']);
                                }
                                if (!empty($data['numero_cuenta_desembolso'])) {
                                    $datosCuenta['numero_cuenta_desembolso'] = trim($data['numero_cuenta_desembolso']);
                                }

                                app(RetanqueoEjecucionInterface::class)->ejecutarRetanqueo($record->id, $datosCuenta);

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
                        ->visible(fn() => request()->user()->hasAnyRole(['super_admin']))
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getEloquentQuery(): Builder
    {
        $user = request()->user();
        return parent::getEloquentQuery()
            ->with([
                'prestamoAntiguo',
                'prestamoAntiguo.grupo',
                'prestamoAntiguo.grupo.asesor',
                'prestamoAntiguo.grupo.asesor.persona',
                'nuevoPrestamo',
                'retanqueosIndividuales',
                'retanqueosIndividuales.cliente',
                'retanqueosIndividuales.cliente.persona',
            ])
            ->visiblePorUsuario($user)
            ->orderBy('created_at', 'desc');
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
        if (!$user)
            return false;

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
