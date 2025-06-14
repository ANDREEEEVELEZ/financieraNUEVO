<?php

namespace App\Filament\Dashboard\Resources;

use App\Filament\Dashboard\Resources\UserResource\Pages;
use App\Filament\Dashboard\Resources\UserResource\RelationManagers;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class UserResource extends Resource
{
    protected static ?string $model = User::class;
    protected static ?string $navigationLabel = 'Usuarios';

   protected static ?string $navigationIcon = 'heroicon-o-identification';




    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')->label('Nombre')->required()->prefixIcon('heroicon-o-user'),
                Forms\Components\TextInput::make('email')->label('Correo')->email()->required()->prefixIcon('heroicon-o-envelope'),
                Forms\Components\TextInput::make('password')
                    ->label('Contraseña')
                    ->prefixIcon('heroicon-o-lock-closed')
                    ->password()
                    ->dehydrateStateUsing(fn($state) => !empty($state) ? bcrypt($state) : null)
                    ->dehydrated(fn($state) => filled($state))
                    ->required(fn($livewire) => $livewire instanceof \Filament\Resources\Pages\CreateRecord),
                Forms\Components\Select::make('roles')
                    ->relationship('roles', 'name')
                    ->prefixIcon('heroicon-o-shield-check')
                    ->multiple()
                    ->preload()
                    ->searchable(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('ID')->sortable(),
                Tables\Columns\TextColumn::make('name')->label('Nombre')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('email')->label('Correo')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('created_at')->label('Fecha de Registro')->dateTime()->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make()->icon('heroicon-o-pencil-square'),
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
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $user = request()->user();

        $query = parent::getEloquentQuery();

        if ($user && $user->hasRole('Asesor')) {
            $query->where('id', $user->id);
        }

        return $query;
    }
}
