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
                // Jefes pueden crear préstamos y editar solo cuando está en estado Pendiente
                if ($prestamo) {
                    // Si el préstamo existe, solo puede editar si está en estado Pendiente
                    $puedeEditarCampos = $prestamo->estado === 'Pendiente';
                } else {
                    // Si es creación, sí puede editar
                    $puedeEditarCampos = true;
                }
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

            // Información sobre configuración fija
            Forms\Components\Placeholder::make('info_configuracion')
                ->label('📋 Configuración de Préstamos')
                ->content(new \Illuminate\Support\HtmlString(
                    '<div style="background-color: #f0f9ff; border: 1px solid #0ea5e9; border-radius: 8px; padding: 16px; margin-bottom: 16px;">
                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px;">
                            <svg style="width: 20px; height: 20px; color: #0ea5e9;" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                            </svg>
                            <strong style="color: #0c4a6e;">Configuración Estándar</strong>
                        </div>
                        <ul style="margin: 0; padding-left: 24px; color: #0c4a6e;">
                            <li><strong>📅 Frecuencia:</strong> Semanal (fijo)</li>
                            <li><strong>� Cuotas:</strong> 4 cuotas (fijo)</li>
                            <li><strong>� Montos:</strong> Según ciclo del cliente</li>
                        </ul>
                        <p style="margin: 8px 0 0 0; color: #0c4a6e; font-size: 14px; font-style: italic;">
                            ℹ️ Todos los préstamos se procesan con estas configuraciones estándar.
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
                            ->whereHas('cuotasGrupales', fn($q) => $q->where('estado_pago', '!=', 'pagado'))
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
                            'ciclo' => \App\Helpers\CicloHelper::normalize($c->ciclo),
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
                    Select::make('monto')
                        ->label(function (callable $get) {
                            $ciclo = \App\Helpers\CicloHelper::normalize($get('ciclo') ?? 'I');
                            return 'Monto a Prestar (Ciclo ' . $ciclo . ')';
                        })
                        ->options(function (callable $get) {
                            $ciclo = \App\Helpers\CicloHelper::normalize($get('ciclo') ?? 'I');
                            return \App\Helpers\CicloHelper::getMontosPermitidosParaSelect($ciclo);
                        })
                        ->required()
                        ->reactive()
                        ->placeholder('Selecciona un monto')
                        ->rules([
                            function (callable $get) {
                                return function (string $attribute, $value, \Closure $fail) use ($get) {
                                    $ciclo = \App\Helpers\CicloHelper::normalize($get('ciclo') ?? 'I');
                                    
                                    // Validar que el monto sea válido
                                    if (!\App\Helpers\CicloHelper::validarMontoExacto($value, $ciclo)) {
                                        $fail('El monto seleccionado no es válido para el ciclo ' . $ciclo);
                                        return;
                                    }
                                    
                                    // Validación adicional: verificar acceso por ciclo
                                    if (!\App\Helpers\CicloHelper::puedeAccederAMonto($value, $ciclo)) {
                                        $cicloMinimo = \App\Helpers\CicloHelper::getCicloPorMonto($value);
                                        $fail("El monto S/ {$value} está disponible desde el Ciclo {$cicloMinimo}. El cliente actual es Ciclo {$ciclo}.");
                                    }
                                };
                            },
                        ])
                        ->afterStateUpdated(function ($state, callable $set, callable $get) {
                            // Validar que el monto seleccionado sea válido para el ciclo
                            $ciclo = \App\Helpers\CicloHelper::normalize($get('ciclo') ?? 'I');
                            if (!\App\Helpers\CicloHelper::validarMontoExacto($state, $ciclo)) {
                                \Filament\Notifications\Notification::make()
                                    ->title('Monto no válido para el ciclo ' . $ciclo)
                                    ->danger()
                                    ->send();
                                return;
                            }
                            
                            // Actualizar totales del préstamo
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

                    Select::make('monto_prestado_individual')
                        ->label(function ($record) {
                            // Validación robusta de existencia de datos
                            if (!$record || !$record->cliente || !$record->cliente->ciclo) {
                                return 'Monto prestado';
                            }
                            
                            try {
                                $ciclo = \App\Helpers\CicloHelper::normalize($record->cliente->ciclo);
                                return 'Monto prestado (Ciclo ' . $ciclo . ')';
                            } catch (\Exception $e) {
                                \Illuminate\Support\Facades\Log::error('Error en label de monto_prestado_individual', [
                                    'error' => $e->getMessage(),
                                    'record_id' => $record->id ?? 'N/A'
                                ]);
                                return 'Monto prestado';
                            }
                        })
                        ->options(function ($record) {
                            // Validación robusta de existencia de datos
                            if (!$record || !$record->cliente || !$record->cliente->ciclo) {
                                return [];
                            }
                            
                            try {
                                $ciclo = \App\Helpers\CicloHelper::normalize($record->cliente->ciclo);
                                $opciones = \App\Helpers\CicloHelper::getMontosPermitidosParaSelect($ciclo);
                                
                                // Validar que opciones sea un array
                                if (!is_array($opciones)) {
                                    $opciones = [];
                                }
                                
                                return $opciones;
                            } catch (\Exception $e) {
                                \Illuminate\Support\Facades\Log::error('Error en options de monto_prestado_individual', [
                                    'error' => $e->getMessage(),
                                    'record_id' => $record->id ?? 'N/A'
                                ]);
                                return [];
                            }
                        })
                        ->required()
                        ->reactive()
                        ->searchable()
                        ->placeholder('Selecciona un monto')
                        ->formatStateUsing(function ($state) {
                            // FORZAR que siempre use el formato entero
                            return $state ? (int)$state : null;
                        })
                        ->default(function ($record) {
                            // Validación robusta del valor por defecto
                            if (!$record || !isset($record->monto_prestado_individual)) {
                                return null;
                            }
                            
                            // Validar que sea un número válido
                            $monto = $record->monto_prestado_individual;
                            if (!is_numeric($monto) || $monto <= 0) {
                                return null;
                            }
                            
                            // Convertir a entero para consistencia con las opciones
                            return (int)$monto;
                        })
                        ->afterStateUpdated(function ($state, $set) {
                            // Asegurar que el valor se guarde como entero
                            if (is_numeric($state)) {
                                $set('monto_prestado_individual', (int)$state);
                            }
                        })
                        ->rules([
                            function ($record) {
                                return function (string $attribute, $value, \Closure $fail) use ($record) {
                                    // Validaciones de seguridad
                                    if (!$record || !$record->cliente || !$record->cliente->ciclo) {
                                        $fail('No se puede validar el monto: datos del cliente incompletos.');
                                        return;
                                    }
                                    
                                    if (!is_numeric($value) || $value <= 0) {
                                        $fail('El monto debe ser un número válido mayor a 0.');
                                        return;
                                    }
                                    
                                    try {
                                        $ciclo = \App\Helpers\CicloHelper::normalize($record->cliente->ciclo);
                                        
                                        // Validar que el monto sea válido para el ciclo
                                        if (!\App\Helpers\CicloHelper::validarMontoExacto($value, $ciclo)) {
                                            $fail('El monto seleccionado no es válido para el ciclo ' . $ciclo);
                                            return;
                                        }
                                        
                                        // Validación adicional: verificar acceso por ciclo
                                        if (!\App\Helpers\CicloHelper::puedeAccederAMonto($value, $ciclo)) {
                                            $cicloMinimo = \App\Helpers\CicloHelper::getCicloPorMonto($value);
                                            $fail("El monto S/ {$value} está disponible desde el Ciclo {$cicloMinimo}. El cliente actual es Ciclo {$ciclo}.");
                                        }
                                    } catch (\Exception $e) {
                                        \Illuminate\Support\Facades\Log::error('Error en validación de monto_prestado_individual', [
                                            'error' => $e->getMessage(),
                                            'value' => $value,
                                            'record_id' => $record->id ?? 'N/A'
                                        ]);
                                        $fail('Error al validar el monto. Contacte al administrador.');
                                    }
                                };
                            },
                        ])
                        ->disabled(fn() => !$puedeEditarCampos)
                        ->afterStateUpdated(function ($state, callable $set, callable $get, $record) {
                            if (!$state || !$record) return;
                            
                            $monto = floatval($state);
                            $tasaInteres = $record->prestamo->tasa_interes ?? 17;
                            $numCuotas = $record->prestamo->cantidad_cuotas ?? 1;
                            
                            // Calcular seguro según el monto exacto y tabla oficial
                            $montoInt = (int) $monto;
                            
                            if ($montoInt === 400) {
                                $seguro = 7;  // Ciclo I
                            } elseif ($montoInt === 500 || $montoInt === 600) {
                                $seguro = 8;  // Ciclo II
                            } elseif ($montoInt === 700 || $montoInt === 800) {
                                $seguro = 9;  // Ciclo III
                            } elseif ($montoInt === 900 || $montoInt === 1000) {
                                $seguro = 10; // Ciclo IV
                            } else {
                                // Fallback para montos no estándar
                                $seguro = 7;
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
                ])
                ->default('semanal')
                ->disabled()
                ->dehydrateStateUsing(fn () => 'semanal')
                ->helperText('⚠️ La frecuencia está fija en semanal para todos los préstamos'),

            TextInput::make('cantidad_cuotas')
                ->label('Cantidad de Cuotas')
                ->default(4)
                ->disabled()
                ->dehydrateStateUsing(fn () => 4)
                ->helperText('⚠️ Fijo en 4 cuotas semanales para todos los préstamos'),

            DatePicker::make('fecha_prestamo')->required()->disabled(fn() => !$puedeEditarCampos),

            // Campo Estado oculto - siempre se crea como Pendiente
            Forms\Components\Hidden::make('estado')->default('Pendiente'),

            // Campos de cuenta de desembolso
            Forms\Components\Section::make('Información de Desembolso')
                ->description('Datos bancarios para el desembolso del préstamo')
                ->schema([
                    TextInput::make('titular_cuenta_desembolso')
                        ->label('Titular de la Cuenta a Desembolsar')
                        ->prefixIcon('heroicon-o-user')
                        ->placeholder('Ingrese el nombre del titular de la cuenta')
                        ->maxLength(255)
                        ->disabled(fn() => !$puedeEditarCampos)
                        ->helperText('💳 Nombre completo del titular ')
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
                        ->disabled(fn() => !$puedeEditarCampos)
                        ->helperText('🏦 Número de cuenta bancaria ')
                        ->rule('regex:/^[0-9]{14}$/')
                        ->numeric()
                        ->extraInputAttributes([
                            'onkeypress' => 'return /[0-9]/.test(event.key) && this.value.length < 14',
                            'oninput' => 'this.value = this.value.replace(/[^0-9]/g, "").substring(0, 14)'
                        ]),
                ])
                ->collapsible()
                ->collapsed(false),

            // Select::make('calificacion')
            //     ->prefixIcon('heroicon-o-star')
            //     ->options([
            //         '1' => '1',
            //         '2' => '2',
            //         '3' => '3',
            //         '4' => '4',
            //         '5' => '5',
            //         '6' => '6',
            //         '7' => '7',
            //         '8' => '8',
            //         '9' => '9',
            //         '10' => '10',
            //     ])
            //     ->native(false)
            //     ->required()
            //     ->rules(['numeric', 'between:1,10'])
            //     ->disabled(fn() => !$puedeEditarCampos),
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
            TextColumn::make('grupo.nombre_grupo')
                ->label('Grupo')
                ->getStateUsing(function ($record) {
                    // Si es un retanqueo, mostrar el nombre del retanqueo en lugar del grupo
                    if ($record->es_retanqueo && $record->descripcion) {
                        return $record->descripcion;
                    }
                    return $record->grupo->nombre_grupo ?? 'Sin grupo';
                })
                ->searchable()
                ->sortable()
                ->wrap(),
                
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
                    'parcialmente_retanqueado' => 'info',
                    'parcialmente retanqueado' => 'info',
                    'rechazado' => 'danger',
                    'finalizado' => 'primary',
                    default => 'warning',
                })
                ->sortable(),
                
            TextColumn::make('titular_cuenta_desembolso')
                ->label('Titular de Cuenta')
                ->searchable()
                ->wrap()
                ->placeholder('No especificado')
                ->toggleable(),
                
            TextColumn::make('numero_cuenta_desembolso')
                ->label('N° de Cuenta')
                ->searchable()
                ->placeholder('No especificado')
                ->toggleable(),
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
                // Filtro por Tipo de Préstamo
                Tables\Filters\SelectFilter::make('tipo_prestamo')
                    ->label('Tipo de Préstamo')
                    ->options([
                        'original' => 'Original',
                        'retanqueo' => 'Retanqueo',
                    ])
                    ->query(function (Builder $query, array $data) {
                        if (!empty($data['value'])) {
                            if ($data['value'] === 'original') {
                                $query->where('es_retanqueo', false);
                            } elseif ($data['value'] === 'retanqueo') {
                                $query->where('es_retanqueo', true);
                            }
                        }
                        return $query;
                    }),
                    
                // Filtro por Estado (visible para todos los roles)
                Tables\Filters\SelectFilter::make('estado')
                    ->label('Estado del Préstamo')
                    ->options([
                        'Pendiente' => 'Pendiente',
                        'Aprobado' => 'Aprobado',
                        'Activo' => 'Activo',
                        'Parcialmente_Retanqueado' => 'Parcialmente Retanqueado',
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
                    Tables\Actions\Action::make('imprimir_contrato')
                        ->label('Imprimir Contrato')
                        ->icon('heroicon-o-printer')
                        ->color('success')
                        ->url(fn($record) => route('contratos.grupo.imprimir', $record->grupo_id))
                        ->visible(fn($record) => $record->grupo_id !== null && strtolower($record->estado) === 'aprobado'),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
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

        // Ordenamiento simple: siempre los más recientes arriba
        return $query->orderBy('created_at', 'desc');
    }
}
