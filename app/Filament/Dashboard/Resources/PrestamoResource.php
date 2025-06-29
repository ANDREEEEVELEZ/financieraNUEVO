<?php

namespace App\Filament\Dashboard\Resources;

use App\Filament\Dashboard\Resources\PrestamoResource\Pages;
use App\Models\Prestamo;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\ActionGroup;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;

class PrestamoResource extends Resource
{
    protected static ?string $model = Prestamo::class;
    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    public static function form(Forms\Form $form): Forms\Form
    {
        $record = request()->route('record');
        $prestamo = $record ? \App\Models\Prestamo::with('prestamoIndividual.cliente.persona')->find($record) : null;
        $user = request()->user();
        
        // Si el préstamo existe y su estado NO es 'Pendiente', bloquear todo
        $prestamoNoPendiente = $prestamo && $prestamo->estado !== 'Pendiente';
        
        // Determinar si el usuario puede editar campos
        $puedeEditarCampos = false;
        
        if (!$prestamoNoPendiente) { // Solo si el préstamo está en estado Pendiente o es nuevo
            if ($user->hasRole('Asesor')) {
                // Asesor solo puede editar si es creador y está en estado Pendiente
                if ($prestamo) {
                    $asesor = \App\Models\Asesor::where('user_id', $user->id)->first();
                    $esCreador = $asesor && $prestamo->grupo && $prestamo->grupo->asesor_id == $asesor->id;
                    $puedeEditarCampos = $esCreador && $prestamo->estado === 'Pendiente';
                } else {
                    // Si es creación, sí puede editar
                    $puedeEditarCampos = true;
                }
            } elseif ($user->hasAnyRole(['super_admin', 'Jefe de operaciones', 'Jefe de creditos'])) {
                // Jefes NO pueden editar campos de solicitud cuando el préstamo existe, solo el estado
                $puedeEditarCampos = false;
            }
        }
        
        // Solo jefes pueden cambiar el estado Y solo si el préstamo está en estado Pendiente
        $puedeEditarEstado = $user->hasAnyRole(['super_admin', 'Jefe de operaciones', 'Jefe de creditos']) && !$prestamoNoPendiente;

        return $form->schema([
            // Mensaje informativo cuando el préstamo no está en estado Pendiente
            Forms\Components\Placeholder::make('mensaje_bloqueado')
                ->label('')
                ->content(function () use ($prestamo) {
                    if ($prestamo && $prestamo->estado !== 'Pendiente') {
                        return new \Illuminate\Support\HtmlString(
                            '<div style="background-color: #fef3c7; border: 1px solid #f59e0b; border-radius: 8px; padding: 12px; margin-bottom: 16px;">
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <svg style="width: 20px; height: 20px; color: #f59e0b;" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M8.485 3.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 3.495zM10 6a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 6zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"></path>
                                    </svg>
                                    <strong style="color: #92400e;">MODO SOLO LECTURA</strong>
                                </div>
                                <p style="margin: 8px 0 0 0; color: #92400e; font-size: 14px;">
                                    Este préstamo está en estado "<strong>' . $prestamo->estado . '</strong>" y no puede ser modificado. 
                                    Todos los campos están bloqueados para preservar la integridad de los datos.
                                </p>
                            </div>'
                        );
                    }
                    return '';
                })
                ->visible(fn() => $prestamo && $prestamo->estado !== 'Pendiente')
                ->columnSpanFull(),

            // Información sobre límites de frecuencias
            Forms\Components\Placeholder::make('info_frecuencias')
                ->label('📋 Información sobre Frecuencias de Pago')
                ->content(new \Illuminate\Support\HtmlString(
                    '<div style="background-color: #f0f9ff; border: 1px solid #0ea5e9; border-radius: 8px; padding: 16px; margin-bottom: 16px;">
                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px;">
                            <svg style="width: 20px; height: 20px; color: #0ea5e9;" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                            </svg>
                            <strong style="color: #0c4a6e;">Límites Máximos por Frecuencia (1 año)</strong>
                        </div>
                        <ul style="margin: 0; padding-left: 24px; color: #0c4a6e;">
                            <li><strong>📅 Semanal:</strong> Máximo 52 cuotas (52 semanas = 1 año)</li>
                            <li><strong>📅 Quincenal:</strong> Máximo 26 cuotas (26 quincenas = 1 año)</li>
                            <li><strong>📅 Mensual:</strong> Máximo 12 cuotas (12 meses = 1 año)</li>
                        </ul>
                        <p style="margin: 8px 0 0 0; color: #0c4a6e; font-size: 14px; font-style: italic;">
                            ⚠️ El sistema ajustará automáticamente las cuotas si exceden el límite de la frecuencia seleccionada.
                        </p>
                    </div>'
                ))
                ->visible(fn() => !$prestamo || $prestamo->estado === 'Pendiente')
                ->columnSpanFull(),

            Select::make('grupo_id')
                ->label('Grupo')
                ->prefixIcon('heroicon-o-rectangle-stack')
                ->relationship('grupo', 'nombre_grupo')
                ->options(function () {
                    $user = request()->user();
                    if ($user->hasRole('Asesor')) {
                        $asesor = \App\Models\Asesor::where('user_id', $user->id)->first();
                        $grupos = $asesor ? \App\Models\Grupo::where('asesor_id', $asesor->id)->where('estado_grupo', 'Activo')->get() : collect();
                    } elseif ($user->hasAnyRole(['super_admin', 'Jefe de operaciones', 'Jefe de creditos'])) {
                        $grupos = \App\Models\Grupo::where('estado_grupo', 'Activo')->get();
                    } else {
                        $grupos = collect();
                    }

                    return $grupos->filter(function ($grupo) {
                        return !$grupo->prestamos()->whereIn('estado', ['Pendiente', 'Aprobado'])
                            ->whereHas('cuotasGrupales', fn($q) => $q->where('estado_pago', '!=', 'Pagado'))
                            ->exists();
                    })->pluck('nombre_grupo', 'id');
                })
                ->searchable()
                ->required()
                ->reactive()
                ->afterStateUpdated(function ($state, callable $set) {
                    $grupo = \App\Models\Grupo::with('clientes.persona')->find($state);
                    $set('clientes_grupo', $grupo ? $grupo->clientes->map(function ($c) {
                        return [
                            'id' => $c->id,
                            'nombre' => $c->persona->nombre,
                            'apellidos' => $c->persona->apellidos,
                            'dni' => $c->persona->DNI,
                            'ciclo' => $c->ciclo ?? 1,
                            'monto' => null,
                        ];
                    })->toArray() : []);
                })
                ->disabled(fn() => !$puedeEditarCampos),

            Forms\Components\Hidden::make('clientes_grupo')->dehydrateStateUsing(fn($state) => $state)->reactive(),

            Forms\Components\Repeater::make('clientes_grupo')
                ->label('Integrantes del Grupo')
                ->schema([
                    TextInput::make('nombre')->disabled(),
                    TextInput::make('apellidos')->disabled(),
                    TextInput::make('dni')->disabled(),
                    TextInput::make('ciclo')->disabled(),
                    TextInput::make('monto')
                        ->label(function (callable $get) {
                            $c = (int)($get('ciclo') ?? 1);
                            $m = [1 => 400, 2 => 600, 3 => 800, 4 => 1000][$c > 4 ? 4 : ($c < 1 ? 1 : $c)];
                            return 'Monto a Prestar (MAX: S/ ' . $m . ')';
                        })
                        ->numeric()
                        ->required()
                        ->live(debounce: 1000)
                        ->minValue(100)
                        ->afterStateUpdated(function ($state, callable $set, callable $get) {
                            $c = (int)($get('ciclo') ?? 1);
                            $m = [1 => 400, 2 => 600, 3 => 800, 4 => 1000][$c > 4 ? 4 : ($c < 1 ? 1 : $c)];
                            if ($state < 100) {
                                \Filament\Notifications\Notification::make()->title('Mínimo S/ 100')->danger()->send();
                                $set('monto', 100);
                            }
                            if ($state > $m) {
                                \Filament\Notifications\Notification::make()->title('Máximo S/ ' . $m)->danger()->send();
                                $set('monto', $m);
                            }
                            $cs = $get('../../clientes_grupo') ?? [];
                            $t = array_sum(array_map(fn($c) => floatval($c['monto'] ?? 0), $cs));
                            $set('../../monto_prestado_total', $t);
                            $i = floatval($get('../../tasa_interes'));
                            $set('../../monto_devolver', $t > 0 ? number_format($t * (1 + $i / 100), 2, '.', '') : '');
                        })
                        ->disabled(fn() => !$puedeEditarCampos),
                ])
                ->visible(fn(callable $get) => !empty($get('clientes_grupo')))
                ->grid(2)
                ->columnSpanFull()
                ->columns(4),

            Forms\Components\Repeater::make('prestamo_individual')
                ->label('Detalle del préstamo por integrante')
                ->relationship('prestamoIndividual')
                ->live()
                ->reactive()
                ->schema([
                    Forms\Components\Placeholder::make('nombre')
                        ->label('Nombre')
                        ->content(fn($record) => $record->cliente->persona->nombre ?? '-'),

                    Forms\Components\Placeholder::make('apellidos')
                        ->label('Apellidos')
                        ->content(fn($record) => $record->cliente->persona->apellidos ?? '-'),

                    TextInput::make('monto_prestado_individual')
                        ->label('Monto prestado')
                        ->prefix('S/.')
                        ->numeric()
                        ->minValue(100)
                        ->live(debounce: 500)
                        ->disabled(fn() => !$puedeEditarCampos)
                        ->afterStateUpdated(function ($state, callable $set, callable $get, $record) {
                            if (!$state || !$record) return;
                            
                            $monto = floatval($state);
                            $tasaInteres = $record->prestamo->tasa_interes ?? 17;
                            $numCuotas = $record->prestamo->cantidad_cuotas ?? 1;
                            
                            // Calcular seguro según el monto
                            if ($monto <= 400) {
                                $seguro = 6;
                            } elseif ($monto <= 600) {
                                $seguro = 7;
                            } elseif ($monto <= 800) {
                                $seguro = 8;
                            } else {
                                $seguro = 9;
                            }
                            
                            // Calcular interés
                            $interes = $monto * ($tasaInteres / 100);
                            
                            // Calcular monto total a devolver individual
                            $montoDevolver = $monto + $interes + $seguro;
                            
                            // Calcular cuota individual
                            $cuotaIndividual = $montoDevolver / $numCuotas;
                            
                            // Actualizar campos individuales con valores numéricos exactos
                            $set('seguro', round($seguro, 2));
                            $set('interes', round($interes, 2));
                            $set('monto_devolver_individual', round($montoDevolver, 2));
                            $set('monto_cuota_prestamo_individual', round($cuotaIndividual, 2));
                            
                            // Recalcular totales inmediatamente
                            $allItems = $get('../../prestamo_individual') ?? [];
                            $montoTotalPrestado = 0;
                            $montoTotalDevolver = 0;
                            
                            foreach ($allItems as $index => $item) {
                                if (isset($item['id']) && $item['id'] == $record->id) {
                                    // Usar los valores actualizados para este item
                                    $montoTotalPrestado += $monto;
                                    $montoTotalDevolver += $montoDevolver;
                                } else {
                                    // Usar los valores existentes para otros items
                                    $montoTotalPrestado += floatval($item['monto_prestado_individual'] ?? 0);
                                    $montoTotalDevolver += floatval($item['monto_devolver_individual'] ?? 0);
                                }
                            }
                            
                            // Actualizar los campos totales con valores numéricos exactos
                            $set('../../monto_prestado_total', round($montoTotalPrestado, 2));
                            $set('../../monto_devolver', round($montoTotalDevolver, 2));
                        }),
                    TextInput::make('seguro')
                        ->label('Seguro')
                        ->prefix('S/.')
                        ->disabled()
                        ->formatStateUsing(fn ($state) => number_format((float)$state, 2)),
                    TextInput::make('interes')
                        ->label('Interés')
                        ->prefix('S/.')
                        ->disabled()
                        ->formatStateUsing(fn ($state) => number_format((float)$state, 2)),
                    TextInput::make('monto_devolver_individual')
                        ->label('Total a devolver')
                        ->prefix('S/.')
                        ->disabled()
                        ->formatStateUsing(fn ($state) => number_format((float)$state, 2)),
                    TextInput::make('monto_cuota_prestamo_individual')
                        ->label('Cuota individual')
                        ->prefix('S/.')
                        ->disabled()
                        ->formatStateUsing(fn ($state) => number_format((float)$state, 2)),
                ])
                ->afterStateUpdated(function ($state, callable $set, callable $get) {
                    // Recalcular totales cuando cambie cualquier cosa en el repeater
                    $montoTotalPrestado = 0;
                    $montoTotalDevolver = 0;
                    
                    if (is_array($state)) {
                        foreach ($state as $item) {
                            $montoTotalPrestado += floatval($item['monto_prestado_individual'] ?? 0);
                            $montoTotalDevolver += floatval($item['monto_devolver_individual'] ?? 0);
                        }
                    }
                    
                    // Actualizar ambos campos con valores numéricos exactos
                    $set('monto_prestado_total', round($montoTotalPrestado, 2));
                    $set('monto_devolver', round($montoTotalDevolver, 2));
                })
                ->visible(fn (callable $get) => $get('id') !== null)
                ->grid(2)
                ->columnSpanFull()
                ->columns(4),

            TextInput::make('tasa_interes')->label('Tasa interés ( % )')->default(17)->readOnly()->numeric()->disabled(fn() => !$puedeEditarCampos),

            TextInput::make('monto_prestado_total')
                ->label('Monto prestado total')
                ->prefix('S/.')
                ->required()
                ->numeric()
                ->readOnly()
                ->live()
                ->reactive()
                ->formatStateUsing(fn ($state) => $state ? number_format((float)$state, 2, '.', '') : '0.00')
                ->dehydrateStateUsing(fn ($state) => (float)str_replace(',', '', $state))
                ->disabled(fn() => !$puedeEditarCampos),

            TextInput::make('monto_devolver')
                ->label('Monto devolver')
                ->prefix('S/.')
                ->readOnly()
                ->live()
                ->reactive()
                ->formatStateUsing(fn ($state) => $state ? number_format((float)$state, 2, '.', '') : '0.00')
                ->dehydrateStateUsing(fn ($state) => (float)str_replace(',', '', $state))
                ->extraInputAttributes(['id' => 'monto_devolver_field'])
                ->disabled(fn() => !$puedeEditarCampos),

            Select::make('frecuencia')
                ->label('Frecuencia de Pago')
                ->options([
                    'semanal' => 'Semanal',
                    'quincenal' => 'Quincenal', 
                    'mensual' => 'Mensual'
                ])
                ->default('semanal')
                ->reactive()
                ->required()
                ->afterStateUpdated(function ($state, callable $set, callable $get) {
                    // Definir límites máximos según frecuencia
                    $limites = [
                        'semanal' => 52,
                        'quincenal' => 26,
                        'mensual' => 12
                    ];
                    
                    $maxCuotas = $limites[$state] ?? 52;
                    $cuotasActuales = $get('cantidad_cuotas');
                    
                    // Si las cuotas actuales exceden el límite, ajustar
                    if ($cuotasActuales && $cuotasActuales > $maxCuotas) {
                        $set('cantidad_cuotas', $maxCuotas);
                        
                        Notification::make()
                            ->warning()
                            ->title('Cantidad de cuotas ajustada')
                            ->body("Se ajustó a {$maxCuotas} cuotas (máximo para frecuencia {$state})")
                            ->send();
                    }
                })
                ->disabled(fn() => !$puedeEditarCampos),

            TextInput::make('cantidad_cuotas')
                ->label('Cantidad de Cuotas')
                ->numeric()
                ->required()
                ->minValue(1)
                ->reactive()
                ->rules(function (callable $get) {
                    $frecuencia = $get('frecuencia') ?? 'semanal';
                    $limites = [
                        'semanal' => 52,
                        'quincenal' => 26,
                        'mensual' => 12
                    ];
                    $maxCuotas = $limites[$frecuencia];
                    
                    return ['max:' . $maxCuotas];
                })
                ->validationMessages([
                    'max' => function (callable $get) {
                        $frecuencia = $get('frecuencia') ?? 'semanal';
                        $limites = [
                            'semanal' => 52,
                            'quincenal' => 26,
                            'mensual' => 12
                        ];
                        $maxCuotas = $limites[$frecuencia];
                        return "El máximo para frecuencia {$frecuencia} es {$maxCuotas} cuotas (1 año).";
                    }
                ])
                ->afterStateUpdated(function ($state, callable $get, callable $set) {
                    $frecuencia = $get('frecuencia') ?? 'semanal';
                    $limites = [
                        'semanal' => 52,
                        'quincenal' => 26,
                        'mensual' => 12
                    ];
                    $maxCuotas = $limites[$frecuencia];
                    
                    if ($state && $state > $maxCuotas) {
                        $set('cantidad_cuotas', $maxCuotas);
                        
                        Notification::make()
                            ->warning()
                            ->title('Límite de cuotas excedido')
                            ->body("Se ajustó a {$maxCuotas} cuotas (máximo para frecuencia {$frecuencia} - 1 año)")
                            ->send();
                    }
                })
                ->helperText(function (callable $get) {
                    $frecuencia = $get('frecuencia') ?? 'semanal';
                    $limites = [
                        'semanal' => 52,
                        'quincenal' => 26,
                        'mensual' => 12
                    ];
                    $maxCuotas = $limites[$frecuencia];
                    return "💡 Máximo {$maxCuotas} cuotas para frecuencia {$frecuencia} (equivale a 1 año)";
                })
                ->disabled(fn() => !$puedeEditarCampos),

            DatePicker::make('fecha_prestamo')->required()->disabled(fn() => !$puedeEditarCampos),

            Select::make('estado')
                ->prefixIcon('heroicon-o-check-circle')
                ->options([
                    'Pendiente' => 'Pendiente',
                    'Aprobado' => 'Aprobado',
                    'Rechazado' => 'Rechazado',
                ])
                ->default('Pendiente')
                ->required()
                ->disabled(fn() => !$puedeEditarEstado)
                ->dehydrated(true),

            TextInput::make('calificacion')
                ->prefixIcon('heroicon-o-star')
                ->numeric()
                ->required()
                ->disabled(fn() => !$puedeEditarCampos),
        ]);
    }

    public static function mutateFormDataBeforeSave(array $data): array
    {
        $user = \Illuminate\Support\Facades\Auth::user();
        $record = request()->route('record');
        
        // Si es un préstamo existente, verificar su estado
        if ($record) {
            $prestamo = \App\Models\Prestamo::find($record);
            
            // Si el préstamo existe y NO está en estado Pendiente, no permitir ningún cambio
            if ($prestamo && $prestamo->estado !== 'Pendiente') {
                // Retornar los datos originales sin cambios
                return $prestamo->toArray();
            }
        }
        
        // Solo los jefes pueden modificar el estado (y solo si está en Pendiente)
        if (!$user->hasAnyRole(['Jefe de operaciones', 'Jefe de creditos', 'super_admin'])) {
            unset($data['estado']);
        }
        
        // Los asesores solo pueden editar si el préstamo está en estado Pendiente
        if ($user->hasRole('Asesor')) {
            if ($record) {
                $prestamo = \App\Models\Prestamo::find($record);
                if ($prestamo && $prestamo->estado !== 'Pendiente') {
                    // Si no está en Pendiente, preservar todos los campos
                    return $prestamo->toArray();
                }
            }
        }
        
        // Si hay cambios en prestamo_individual, recalcular totales
        if (isset($data['prestamo_individual']) && is_array($data['prestamo_individual'])) {
            $montoTotalPrestado = 0;
            $montoTotalDevolver = 0;
            
            foreach ($data['prestamo_individual'] as $pi) {
                $montoTotalPrestado += floatval($pi['monto_prestado_individual'] ?? 0);
                $montoTotalDevolver += floatval($pi['monto_devolver_individual'] ?? 0);
            }
            
            $data['monto_prestado_total'] = round($montoTotalPrestado, 2);
            $data['monto_devolver'] = round($montoTotalDevolver, 2);
        }
        
        unset($data['nuevo_rol']);
        return $data;
    }

    public static function mutateFormDataBeforeFill(array $data): array
    {
        // Asegurar que los montos totales se cargan correctamente
        if (!empty($data['id'])) {
            $prestamo = \App\Models\Prestamo::with('prestamoIndividual')->find($data['id']);
            if ($prestamo && $prestamo->prestamoIndividual->count() > 0) {
                // Recalcular los montos totales basados en los préstamos individuales
                $montoTotal = $prestamo->prestamoIndividual->sum('monto_prestado_individual');
                $montoDevolver = $prestamo->prestamoIndividual->sum('monto_devolver_individual');
                
                if ($montoTotal > 0) {
                    $data['monto_prestado_total'] = $montoTotal;
                }
                if ($montoDevolver > 0) {
                    $data['monto_devolver'] = $montoDevolver;
                }
            }
        }
        
        return $data;
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('grupo.nombre_grupo')->label('Grupo')->searchable()->sortable(),
            TextColumn::make('monto_prestado_total')->label('Monto Prestado')->money('PEN')->sortable(),
            TextColumn::make('monto_devolver')->label('Monto a Devolver')->money('PEN')->sortable(),
            TextColumn::make('cantidad_cuotas')->label('N° Cuotas')->sortable(),
            TextColumn::make('fecha_prestamo')->label('Fecha')->date()->sortable(),
            TextColumn::make('estado')
                ->label('Estado')
                ->formatStateUsing(fn($state, $record) => $record->estado_visible)
                ->badge()
                ->color(fn(string $state) => match (strtolower($state)) {
                    'aprobado' => 'success',
                    'activo' => 'warning',
                    'rechazado' => 'danger',
                    'finalizado' => 'primary',
                    default => 'warning',
                })
                ->sortable(),
            TextColumn::make('detalle_individual')
                ->label('Detalle Individual')
                ->html()
                ->getStateUsing(function ($record) {
                    $detalles = \App\Models\PrestamoIndividual::where('prestamo_id', $record->id)
                        ->with('cliente.persona')
                        ->get();
                    if ($detalles->isEmpty()) {
                        return '<span style="color: #888">Sin datos</span>';
                    }
                    $html = '<ul style="padding-left: 1em;">';
                    foreach ($detalles as $detalle) {
                        $nombre = $detalle->cliente->persona->nombre . ' ' . $detalle->cliente->persona->apellidos;
                        $monto = number_format((float)$detalle->monto_prestado_individual, 2);
                        $devolver = number_format((float)$detalle->monto_devolver_individual, 2);
                        $html .= "<li><b>$nombre</b>: Prestado S/ $monto | A devolver S/ $devolver</li>";
                    }
                    $html .= '</ul>';
                    return $html;
                }),
        ])
            ->filters([
                // Filtro por Estado (visible para todos los roles)
                Tables\Filters\SelectFilter::make('estado')
                    ->label('Estado del Préstamo')
                    ->options([
                        'Pendiente' => 'Pendiente',
                        'Aprobado' => 'Aprobado',
                        'Activo' => 'Activo',
                        'Rechazado' => 'Rechazado',
                        'Finalizado' => 'Finalizado',
                    ])
                    ->query(function (Builder $query, array $data) {
                        if (!empty($data['value'])) {
                            $query->where('estado', $data['value']);
                        }
                        return $query;
                    }),
                
                // Filtro por Asesor (visible solo para roles administrativos, NO para Asesor)
                Tables\Filters\SelectFilter::make('asesor')
                    ->label('Asesor')
                    ->options(function () {
                        return \App\Models\Asesor::where('estado_asesor', 'Activo')
                            ->with('persona')
                            ->get()
                            ->mapWithKeys(function ($asesor) {
                                return [$asesor->id => $asesor->persona->nombre . ' ' . $asesor->persona->apellidos];
                            });
                    })
                    ->query(function (Builder $query, array $data) {
                        if (!empty($data['value'])) {
                            $query->whereHas('grupo', function ($q) use ($data) {
                                $q->where('asesor_id', $data['value']);
                            });
                        }
                        return $query;
                    })
                    ->visible(fn () => request()->user() && !request()->user()->hasRole('Asesor')),
            ])
            ->actions([
                ActionGroup::make([
                    Tables\Actions\EditAction::make()->icon('heroicon-o-pencil-square'),
                    Tables\Actions\Action::make('aprobar')
                        ->label('Aprobar')
                        ->icon('heroicon-m-check-circle')
                        ->color('success')
                        ->visible(fn ($record) => 
                            in_array(strtolower($record->estado), ['pendiente']) && 
                            \Illuminate\Support\Facades\Auth::user()?->hasAnyRole(['super_admin', 'Jefe de operaciones', 'Jefe de creditos'])
                        )
                        ->action(function ($record) {
                            $record->aprobar();
                            Notification::make()
                                ->title('Préstamo aprobado')
                                ->body('El préstamo ha sido aprobado exitosamente.')
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\Action::make('rechazar')
                        ->label('Rechazar')
                        ->icon('heroicon-m-x-circle')
                        ->color('danger')
                        ->visible(fn ($record) => 
                            in_array(strtolower($record->estado), ['pendiente']) && 
                            \Illuminate\Support\Facades\Auth::user()?->hasAnyRole(['super_admin', 'Jefe de operaciones', 'Jefe de creditos'])
                        )
                        ->action(function ($record) {
                            $record->rechazar();
                            Notification::make()
                                ->title('Préstamo rechazado')
                                ->body('El préstamo ha sido rechazado.')
                                ->danger()
                                ->send();
                        }),
                    Tables\Actions\Action::make('imprimir_contrato')
                        ->label('Imprimir Contrato')
                        ->icon('heroicon-o-printer')
                        ->color('success')
                        ->url(fn($record) => route('contratos.grupo.imprimir', $record->grupo_id))
                        ->visible(fn($record) => $record->grupo_id !== null && strtolower($record->estado) === 'aprobado'),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPrestamo::route('/'),
            'create' => Pages\CreatePrestamo::route('/create'),
            'edit' => Pages\EditPrestamo::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $user = request()->user();
        $query = parent::getEloquentQuery();

        if ($user->hasRole('Asesor')) {
            $asesor = \App\Models\Asesor::where('user_id', $user->id)->first();
            if ($asesor) {
                $query->whereHas('grupo', fn($q) => $q->where('asesor_id', $asesor->id));
            }
        } elseif (!$user->hasAnyRole(['super_admin', 'Jefe de operaciones', 'Jefe de creditos'])) {
            $query->whereRaw('1 = 0');
        }

        return $query;
    }
}
