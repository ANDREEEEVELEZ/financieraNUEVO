<?php

namespace App\Filament\Dashboard\Resources;

use App\Filament\Dashboard\Resources\PrestamoResource\Pages;
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

class PrestamoResource extends Resource
{
    protected static ?string $model = Prestamo::class;
    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    public static function form(Forms\Form $form): Forms\Form
    {
        $record = request()->route('record');
        $prestamo = $record ? \App\Models\Prestamo::find($record) : null;
        $estado = $prestamo ? strtolower($prestamo->estado) : null;
        $isBloqueado = in_array($estado, ['aprobado', 'activo']);

        return $form->schema([
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
                ->disabled(fn() => $isBloqueado),

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
                        ->live()
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
                        ->disabled(fn() => $isBloqueado),
                ])
                ->visible(fn(callable $get) => !empty($get('clientes_grupo')))
                ->columns(4),

            TextInput::make('tasa_interes')->label('Tasa interés ( % )')->default(17)->readOnly()->numeric()->disabled(fn() => $isBloqueado),

            TextInput::make('monto_prestado_total')
                ->label('Monto prestado total')
                ->prefix('S/.')
                ->required()
                ->numeric()
                ->readOnly()
                ->live()
                ->afterStateUpdated(function ($state, callable $set, callable $get) {
                    $m = floatval($state);
                    $i = floatval($get('tasa_interes'));
                    $set('monto_devolver', $m > 0 ? number_format($m * (1 + $i / 100), 2, '.', '') : '');
                })
                ->disabled(fn() => $isBloqueado),

            TextInput::make('monto_devolver')->label('Monto devolver')->prefix('S/.')->readOnly()->disabled(fn() => $isBloqueado),

            TextInput::make('cantidad_cuotas')->numeric()->required()->minValue(1)->mask('999')->disabled(fn() => $isBloqueado),

            DatePicker::make('fecha_prestamo')->required()->disabled(fn() => $isBloqueado),

            Select::make('frecuencia')
                ->options(['semanal' => 'Semanal', 'mensual' => 'Mensual (bloqueado)', 'quincenal' => 'Quincenal (bloqueado)'])
                ->default('semanal')
                ->disabled(fn() => $isBloqueado),

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

    public static function mutateFormDataBeforeSave(array $data): array
    {
        if (!\Illuminate\Support\Facades\Auth::user()->hasAnyRole(['Jefe de operaciones', 'Jefe de creditos', 'super_admin'])) {
            unset($data['estado']);
        }
        unset($data['nuevo_rol']);
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
