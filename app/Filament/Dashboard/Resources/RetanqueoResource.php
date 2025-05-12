<?php

namespace App\Filament\Resources;

use Filament\Forms;
use Filament\Tables;
use Filament\Resources\Resource;
use App\Models\Retanqueo;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DateTimePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\DateTimeColumn;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\EditRecord;

class RetanqueoResource extends Resource
{
    protected static ?string $model = Retanqueo::class;
    
    protected static ?string $navigationIcon = 'heroicon-o-cash';
    protected static ?string $label = 'Retanqueo';
    protected static ?string $pluralLabel = 'Retanqueos';

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
                TextInput::make('monto_retanqueado')->numeric(),
                TextInput::make('monto_devolver')->numeric(),
                TextInput::make('monto_desembolsar')->numeric(),
                TextInput::make('cantidad_cuotas_retanqueo')->numeric(),
                TextInput::make('aceptado')->boolean(),
                DateTimePicker::make('fecha_aceptacion'),
                TextInput::make('estado_retanqueo')->required(),
            ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                TextColumn::make('prestamo_id')->label('Préstamo'),
                TextColumn::make('grupo_id')->label('Grupo'),
                TextColumn::make('asesores_id')->label('Asesor'),
                TextColumn::make('monto_retanqueado')->sortable(),
                TextColumn::make('estado_retanqueo')->sortable(),
                TextColumn::make('fecha_aceptacion')->sortable(),
                TextColumn::make('created_at')->label('Fecha de creación')->dateTime(),
            ])
            ->filters([])
            ->actions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRecords::route('/'),
            'create' => CreateRecord::route('/create'),
            'edit' => EditRecord::route('/{record}/edit'),
        ];
    }
}