<?php

namespace App\Filament\Dashboard\Resources;

use App\Filament\Dashboard\Resources\EgresosResource\Pages;
use App\Filament\Dashboard\Resources\EgresosResource\RelationManagers;
use App\Models\Egreso;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class EgresosResource extends Resource
{
    protected static ?string $model = Egreso::class;

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
            'index' => Pages\ListEgresos::route('/'),
            'create' => Pages\CreateEgresos::route('/create'),
            'edit' => Pages\EditEgresos::route('/{record}/edit'),
        ];
    }
}
