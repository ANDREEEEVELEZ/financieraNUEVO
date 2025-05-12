<?php

namespace App\Filament\Dashboard\Resources;

use App\Filament\Dashboard\Resources\PagoResource\Pages;
use App\Filament\Dashboard\Resources\PagoResource\RelationManagers;
use App\Models\Pago;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PagoResource extends Resource
{
    protected static ?string $model = Pago::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('cuota_grupal_id')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('tipo_pago')
                    ->maxLength(255),
                Forms\Components\TextInput::make('monto_pagado')
                    ->maxLength(255),
                Forms\Components\DateTimePicker::make('fecha_pago'),
                Forms\Components\TextInput::make('estado_pago')
                    ->maxLength(255),
                Forms\Components\TextInput::make('observaciones')
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('cuota_grupal_id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('tipo_pago')
                    ->searchable(),
                Tables\Columns\TextColumn::make('monto_pagado')
                    ->searchable(),
                Tables\Columns\TextColumn::make('fecha_pago')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('estado_pago')
                    ->searchable(),
                Tables\Columns\TextColumn::make('observaciones')
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
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
            'index' => Pages\ListPagos::route('/'),
            'create' => Pages\CreatePago::route('/create'),
            'edit' => Pages\EditPago::route('/{record}/edit'),
        ];
    }
}
