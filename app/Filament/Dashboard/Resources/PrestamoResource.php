<?php

namespace App\Filament\Dashboard\Resources;

use App\Filament\Dashboard\Resources\PrestamoResource\Pages;
use App\Filament\Dashboard\Resources\PrestamoResource\RelationManagers;
use App\Models\Prestamo;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PrestamoResource extends Resource
{
    protected static ?string $model = Prestamo::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';


    public static function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            Select::make('grupo_id')
                ->label('Grupo')
                 ->prefixIcon('heroicon-o-rectangle-stack')
                ->relationship('grupo', 'nombre_grupo')
                ->options(function () {
                    $user = request()->user();
                    $gruposDisponibles = [];
                    if ($user->hasRole('Asesor')) {
                        $asesor = \App\Models\Asesor::where('user_id', $user->id)->first();
                        if ($asesor) {
                            $grupos = \App\Models\Grupo::where('asesor_id', $asesor->id)
                                ->orderBy('nombre_grupo', 'asc')
                                ->get();
                        }
                    } elseif ($user->hasAnyRole(['super_admin', 'Jefe de operaciones', 'Jefe de creditos'])) {
                        $grupos = \App\Models\Grupo::orderBy('nombre_grupo', 'asc')->get();
                    } else {
                        $grupos = collect();
                    }
                    // Filtrar grupos que NO tengan préstamos activos con deuda
                    $gruposDisponibles = $grupos->filter(function($grupo) {
                        $tienePrestamoActivo = $grupo->prestamos()->whereIn('estado', ['Pendiente', 'Aprobado'])
                            ->whereHas('cuotasGrupales', function($q) {
                                $q->where('estado_pago', '!=', 'Pagado');
                            })->exists();
                        return !$tienePrestamoActivo;
                    })->pluck('nombre_grupo', 'id');
                    return $gruposDisponibles;
                })
                ->searchable()
                ->required()
                ->reactive()
                ->afterStateUpdated(function ($state, callable $set) {
                    $grupo = \App\Models\Grupo::with('clientes.persona')->find($state);
                    if ($grupo) {
                        $clientes = $grupo->clientes->map(function ($cliente) {
                            return [
                                'id' => $cliente->id,
                                'nombre' => $cliente->persona->nombre,
                                'apellidos' => $cliente->persona->apellidos,
                                'dni' => $cliente->persona->DNI,
                                'ciclo' => $cliente->ciclo ?? 1,
                                'monto' => null,
                            ];
                        })->toArray();
                        $set('clientes_grupo', $clientes);
                    } else {
                        $set('clientes_grupo', []);
                    }
                }),
            \Filament\Forms\Components\Hidden::make('clientes_grupo')
                ->dehydrateStateUsing(fn($state) => $state)
                ->reactive(),
            \Filament\Forms\Components\Repeater::make('clientes_grupo')
                ->label('Integrantes del Grupo')
                ->schema([
                    \Filament\Forms\Components\TextInput::make('nombre')
                        ->label('Nombre')
                        ->disabled(),
                    \Filament\Forms\Components\TextInput::make('apellidos')
                        ->label('Apellidos')
                        ->disabled(),
                    \Filament\Forms\Components\TextInput::make('dni')
                        ->label('DNI')
                        ->disabled(),
                    \Filament\Forms\Components\TextInput::make('ciclo')
                        ->label('Ciclo')
                        ->disabled(),
                    \Filament\Forms\Components\TextInput::make('monto')
                        ->label(function(callable $get) {
                            $ciclo = (int)($get('ciclo') ?? 1);
                            $ciclos = [
                                1 => ['max' => 400],
                                2 => ['max' => 600],
                                3 => ['max' => 800],
                                4 => ['max' => 1000],
                            ];
                            $ciclo = $ciclo > 4 ? 4 : ($ciclo < 1 ? 1 : $ciclo);
                            $max = $ciclos[$ciclo]['max'];
                            return 'Monto a Prestar (MAX: S/ ' . $max . ')';
                        })
                        ->numeric()
                        ->required()
                        ->live()
                        ->afterStateUpdated(function ($state, callable $set, callable $get) {
                            $ciclo = (int)($get('ciclo') ?? 1);
                            $ciclos = [
                                1 => ['max' => 400],
                                2 => ['max' => 600],
                                3 => ['max' => 800],
                                4 => ['max' => 1000],
                            ];
                            $ciclo = $ciclo > 4 ? 4 : ($ciclo < 1 ? 1 : $ciclo);
                            $max = $ciclos[$ciclo]['max'];
                            if ($state > $max) {
                                \Filament\Notifications\Notification::make()
                                    ->title('El monto máximo permitido para este cliente es S/ ' . $max)
                                    ->danger()
                                    ->send();
                                $set('monto', $max);
                            }
                            $clientes = $get('../../clientes_grupo') ?? [];
                            $total = array_sum(array_map(fn($c) => floatval($c['monto'] ?? 0), $clientes));
                            $set('../../monto_prestado_total', $total);
                            $interes = floatval($get('../../tasa_interes'));
                            if ($total > 0 && $interes >= 0) {
                                $montoDevolver = $total * (1 + $interes / 100);
                                $set('../../monto_devolver', number_format($montoDevolver, 2, '.', ''));
                            } else {
                                $set('../../monto_devolver', '');
                            }
                        }),
                ])
                ->visible(fn(callable $get) => !empty($get('clientes_grupo')))
                ->columns(4),
            TextInput::make('tasa_interes')
                ->label('Tasa interés ( % )')
                ->prefixIcon('heroicon-o-chart-bar')
                ->default(17)
                ->readOnly()
                ->numeric(),
            TextInput::make('monto_prestado_total')
                ->label('Monto prestado total')
                ->prefix('S/.')
                ->required()
                ->numeric()
                ->readOnly()
                ->live()
                ->afterStateUpdated(function ($state, callable $set, callable $get) {
                    $monto = floatval($state);
                    $interes = floatval($get('tasa_interes'));
                    if ($monto > 0 && $interes >= 0) {
                        $montoDevolver = $monto * (1 + $interes / 100);
                        $set('monto_devolver', number_format($montoDevolver, 2, '.', ''));
                    }
                }),
            TextInput::make('monto_devolver')
                ->label('Monto devolver')
                ->prefix('S/.')
                ->readOnly(),
            TextInput::make('cantidad_cuotas')
                ->numeric()
                ->prefixIcon('heroicon-o-hashtag')
                ->required()
                ->minValue(1)
                ->rules(['integer', 'min:1'])
                ->extraAttributes(['inputmode' => 'numeric', 'pattern' => '[0-9]*'])
                ->mask('999'),
            DatePicker::make('fecha_prestamo')
            ->prefixIcon('heroicon-o-calendar')
                ->required(),
            Select::make('frecuencia')
            ->prefixIcon('heroicon-o-arrow-path')
                ->options([
                    'mensual' => 'Mensual',
                    'semanal' => 'Semanal',
                    'quincenal' => 'Quincenal',
                ])
                ->required()
                ->default('semanal')
                ->disabled(fn() => false)
                ->reactive()
                ->afterStateHydrated(function ($component, $state) {
                    // Solo permitir seleccionar semanal
                    $component->options([
                        'semanal' => 'Semanal',
                        'mensual' => 'Mensual (bloqueado)',
                        'quincenal' => 'Quincenal (bloqueado)'
                    ]);
                }),
            Select::make('estado')
             ->prefixIcon('heroicon-o-check-circle')
                ->options([
                    'Pendiente' => 'Pendiente',
                    'Aprobado' => 'Aprobado',
                    'Rechazado' => 'Rechazado',
                    // 'Finalizado' NO se muestra como opción
                ])
                ->default('Pendiente')
                ->required()
                ->disabled(fn() => !(
                    \Illuminate\Support\Facades\Auth::check() &&
                    \Illuminate\Support\Facades\Auth::user()->hasAnyRole(['super_admin','Jefe de operaciones', 'Jefe de creditos']) &&
                    request()->routeIs('filament.dashboard.resources.prestamos.edit')
                ))
                ->dehydrated(true),
            TextInput::make('calificacion')
            ->prefixIcon('heroicon-o-star')
                ->numeric()
                ->required(),

        ]);
    }

    // Proteger el backend para que solo los roles permitidos puedan modificar el estado
    public static function mutateFormDataBeforeSave(array $data): array
    {
        // Solo los roles permitidos pueden modificar el estado
        if (!\Illuminate\Support\Facades\Auth::user()->hasAnyRole(['Jefe de operaciones', 'Jefe de creditos', 'super_admin'])) {
            unset($data['estado']);
        }
        // Eliminar el campo nuevo_rol para que no intente guardarse en la tabla prestamos
        unset($data['nuevo_rol']);
        return $data;
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table->columns([
            TextColumn::make('grupo.nombre_grupo')
                ->label('Grupo')
                ->searchable()
                ->sortable(),
            TextColumn::make('tasa_interes')
                ->label('Tasa Interés')
                ->sortable(),
            TextColumn::make('monto_prestado_total')
                ->label('Monto Prestado')
                ->money('PEN')
                ->sortable(),
            TextColumn::make('monto_devolver')
                ->label('Monto a Devolver')
                ->money('PEN')
                ->sortable(),
            TextColumn::make('cantidad_cuotas')
                ->label('N° Cuotas')
                ->sortable(),
            TextColumn::make('fecha_prestamo')
                ->label('Fecha')
                ->date()
                ->sortable(),
            TextColumn::make('estado')
                ->formatStateUsing(fn (string $state): string => ucfirst($state))
                ->badge()
                ->color(fn (string $state): string => match (strtolower($state)) {
                    'aprobado' => 'success',
                    'rechazado' => 'danger',
                    'finalizado' => 'primary',
                    default => 'warning',
                })
                ->sortable(),
            TextColumn::make('calificacion')
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
                        $monto = number_format($detalle->monto_prestado_individual, 2);
                        $devolver = number_format($detalle->monto_devolver_individual, 2);
                        $html .= "<li><b>$nombre</b>: Prestado S/ $monto | A devolver S/ $devolver</li>";
                    }
                    $html .= '</ul>';
                    return $html;
                }),
        ])
            ->actions([
                Tables\Actions\EditAction::make()->icon('heroicon-o-pencil-square'),
                Tables\Actions\Action::make('imprimir_contrato')
                    ->label('Imprimir Contrato')
                    ->icon('heroicon-o-printer')
                    ->color('success')
                    ->url(fn($record) => route('contratos.grupo.imprimir', $record->grupo_id))
                    ->visible(fn($record) => $record->grupo_id !== null),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $user = request()->user();

        $query = parent::getEloquentQuery();

        if ($user->hasRole('Asesor')) {
            $asesor = \App\Models\Asesor::where('user_id', $user->id)->first();

            if ($asesor) {
                $query->whereHas('grupo', function ($subQuery) use ($asesor) {
                    $subQuery->where('asesor_id', $asesor->id);
                });
            }
        } elseif ($user->hasAnyRole(['super_admin', 'Jefe de operaciones', 'Jefe de creditos'])) {
            // No se aplica ningún filtro adicional para estos roles, ya que deben ver todos los grupos
        } else {
            // En caso de que el usuario no tenga un rol específico, se puede manejar según sea necesario
            $query->whereRaw('1 = 0'); // Esto asegura que no se devuelvan resultados
        }

        return $query;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPrestamo::route('/'),
            'create' => Pages\CreatePrestamo::route('/create'),
            'edit' => Pages\EditPrestamo::route('/{record}/edit'),
        ];
    }
}
