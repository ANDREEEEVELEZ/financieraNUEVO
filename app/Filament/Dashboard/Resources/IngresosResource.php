<?php

namespace App\Filament\Dashboard\Resources;

use App\Filament\Dashboard\Resources\IngresosResource\Pages;
use App\Filament\Dashboard\Resources\IngresosResource\RelationManagers;
use App\Models\Ingreso;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class IngresosResource extends Resource
{
    protected static ?string $model = Ingreso::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                //
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                //
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
            'index' => Pages\ListIngresos::route('/'),
            'create' => Pages\CreateIngresos::route('/create'),
            'edit' => Pages\EditIngresos::route('/{record}/edit'),
        ];
    }
}
