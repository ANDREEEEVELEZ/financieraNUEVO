<?php

namespace App\Filament\Dashboard\Resources;

use App\Filament\Dashboard\Resources\MoraResource\Pages;
use App\Filament\Dashboard\Resources\MoraResource\RelationManagers;
use App\Models\Mora;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class MoraResource extends Resource
{
    protected static ?string $model = Mora::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('dias_atraso')
                    ->numeric(),
                Forms\Components\TextInput::make('monto_mora')
                    ->maxLength(255),
                Forms\Components\TextInput::make('estado_mora')
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('dias_atraso')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('monto_mora')
                    ->searchable(),
                Tables\Columns\TextColumn::make('estado_mora')
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
            'index' => Pages\ListMoras::route('/'),
            'create' => Pages\CreateMora::route('/create'),
            'edit' => Pages\EditMora::route('/{record}/edit'),
        ];
    }
}
