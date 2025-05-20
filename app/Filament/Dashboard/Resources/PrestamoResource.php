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
                ->required(),
            TextInput::make('tasa_interes')
                ->numeric()
                ->required(),
            TextInput::make('monto_prestado_total')
                ->numeric()
                ->required(),
            TextInput::make('monto_devolver')
                ->numeric()
                ->required(),
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
                ->required(),
            TextInput::make('calificacion')
                ->numeric()
                ->required(),
        ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table->columns([
            TextColumn::make('grupo.nombre_grupo')->label('Grupo'),
            TextColumn::make('tasa_interes')->sortable(),
            TextColumn::make('monto_prestado_total')->sortable(),
            TextColumn::make('cantidad_cuotas')->sortable(),
            TextColumn::make('fecha_prestamo')->dateTime(),
            TextColumn::make('estado')->sortable(),
            TextColumn::make('calificacion')->sortable(),
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