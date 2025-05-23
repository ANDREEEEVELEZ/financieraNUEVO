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

class PrestamoResource extends Resource
{
    protected static ?string $model = Prestamo::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';


    public static function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            Select::make('grupo_id')
                ->relationship('grupo', 'nombre_grupo')
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
                    \Filament\Forms\Components\TextInput::make('monto')
                        ->label('Monto a Prestar')
                        ->numeric()
                        ->required()
                        ->live()
                        ->afterStateUpdated(function ($state, callable $set, callable $get) {
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
                ->default(17)
                ->readOnly()
                ->numeric(),
            TextInput::make('monto_prestado_total')
                ->label('Monto prestado total')
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
                ->readOnly(),
            TextInput::make('cantidad_cuotas')
                ->numeric()
                ->required(),
            DatePicker::make('fecha_prestamo')
                ->required(),
            Select::make('frecuencia')
                ->options([
                    'mensual' => 'Mensual',
                    'semanal' => 'Semanal',
                    'quincenal' => 'Quincenal',
                ])
                ->required(),
            Select::make('estado')
                ->options([
                    'pendiente' => 'Pendiente',
                    'aprobado' => 'Aprobado',
                    'rechazado' => 'Rechazado',
                ])
                ->default('pendiente')
                ->required()
                ->disabled(fn() => !(
                    \Illuminate\Support\Facades\Auth::check() &&
                    \Illuminate\Support\Facades\Auth::user()->hasRole(['Jefe de Operaciones', 'Jefe de Créditos']) &&
                    request()->routeIs('filament.dashboard.resources.prestamos.edit')
                )),
            TextInput::make('calificacion')
                ->numeric()
                ->required(),
        ]);
    }

    // Proteger el backend para que solo los roles permitidos puedan modificar el estado
    public static function mutateFormDataBeforeSave(array $data): array
    {
        if (!\Illuminate\Support\Facades\Auth::user()->hasRole(['Jefe de Operaciones', 'Jefe de Créditos'])) {
            unset($data['estado']);
        }
        return $data;
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table->columns([
            TextColumn::make('grupo.nombre_grupo')->label('Grupo'),
            TextColumn::make('tasa_interes')->sortable(),
            TextColumn::make('monto_prestado_total')->sortable(),
            TextColumn::make('monto_devolver')->label('Monto a Devolver (Grupal)')->sortable(),
            TextColumn::make('cantidad_cuotas')->sortable(),
            TextColumn::make('fecha_prestamo')->dateTime(),
            TextColumn::make('estado')->sortable(),
            TextColumn::make('calificacion')->sortable(),
            TextColumn::make('detalle_individual')
                ->label('Detalle Individual')
                ->html()
                ->getStateUsing(function ($record) {
                    $detalles = \App\Models\Prestamo_Individual::where('prestamo_id', $record->id)
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
            Tables\Actions\EditAction::make(),
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
}