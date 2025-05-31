<?php

namespace App\Filament\Dashboard\Resources;

use App\Filament\Dashboard\Resources\RetanqueoResource\Pages;
use App\Filament\Dashboard\Resources\RetanqueoResource\RelationManagers;
use App\Models\Retanqueo;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class RetanqueoResource extends Resource
{
    protected static ?string $model = Retanqueo::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('prestamos_id')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('grupo_id')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('asesor_id')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('monto_retanqueado')
                    ->maxLength(255),
                Forms\Components\TextInput::make('monto_devolver')
                    ->maxLength(255),
                Forms\Components\TextInput::make('monto_desembolsar')
                    ->maxLength(255),
                Forms\Components\TextInput::make('cantidad_cuotas_retanqueo')
                    ->maxLength(255),
                Forms\Components\TextInput::make('aceptado')
                    ->maxLength(255),
                Forms\Components\DateTimePicker::make('fecha_aceptacion'),
                Forms\Components\TextInput::make('estado_retanqueo')
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('prestamos_id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('grupo_id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('asesor_id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('monto_retanqueado')
                    ->searchable(),
                Tables\Columns\TextColumn::make('monto_devolver')
                    ->searchable(),
                Tables\Columns\TextColumn::make('monto_desembolsar')
                    ->searchable(),
                Tables\Columns\TextColumn::make('cantidad_cuotas_retanqueo')
                    ->searchable(),
                Tables\Columns\TextColumn::make('aceptado')
                    ->searchable(),
                Tables\Columns\TextColumn::make('fecha_aceptacion')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('estado_retanqueo')
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
        }

        return $query;
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
            'index' => Pages\ListRetanqueos::route('/'),
            'create' => Pages\CreateRetanqueo::route('/create'),
            'edit' => Pages\EditRetanqueo::route('/{record}/edit'),
        ];
    }
}
