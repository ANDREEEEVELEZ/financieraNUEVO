<?php

namespace App\Filament\Dashboard\Resources;

use App\Filament\Dashboard\Resources\PersonaResource\Pages;
use App\Filament\Dashboard\Resources\PersonaResource\RelationManagers;
use App\Models\Persona;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PersonaResource extends Resource
{
    protected static ?string $model = Persona::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Información Personal')
                    ->columns(2)
                    ->description('Asegurate de validar los datos antes del registro')
                    ->schema([
                        Forms\Components\TextInput::make('DNI')
                            ->label('DNI')
                            ->required()
                            ->maxLength(8)
                            ->numeric()
                            ->minLength(8),
                        Forms\Components\TextInput::make('nombre')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('apellidos')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Select::make('sexo')
                            ->options([
                                'Hombre' => 'Hombre',
                                'Mujer' => 'Mujer',
                            ])
                            ->native(false)
                            ->searchable()
                            ->label('Sexo')
                            ->required(),
                        Forms\Components\DatePicker::make('fecha_nacimiento')
                            ->label('Fecha de Nacimiento')
                            ->placeholder('Selecciona una fecha')
                            ->required(),
                        Forms\Components\TextInput::make('celular')
                            ->maxLength(9)
                            ->minLength(9)
                            ->numeric()
                            ->required(),
                        Forms\Components\TextInput::make('correo')
                            ->maxLength(255)
                            ->required(),
                        Forms\Components\TextInput::make('direccion')
                            ->maxLength(255)
                            ->required(),
                         Forms\Components\Select::make('Distrito')
                            ->options([
                                'Sullana' => 'Sullana',
                                'Bellavista ' => 'Bellavista',
                                'Ignacio Escudero' => 'Ignacio Escudero',
                                'Querecotillo' => 'Querecotillo',
                                'Marcavelica' => 'Marcavelica',
                                'Salitral' => 'Salitral',
                                'Lancones' => 'Lancones',
                                'Miguel Checa' => 'Miguel Checa',
                                // Agrega más distritos según sea necesario
                            ])
                            ->native(false)
                            ->searchable()
                            ->label('Distrito')
                            ->required(),
                        Forms\Components\Select::make('estado_civil')
                            ->options([
                                'Soltero' => 'Soltero',
                                'Casado' => 'Casado',
                                'Divorciado' => 'Divorciado',
                                'Viudo' => 'Viudo',
                            ])
                            ->native(false)
                            ->searchable()
                            ->label('Estado Civil')
                            ->required(),

                    ]),

            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('DNI')
                    ->searchable(),
                Tables\Columns\TextColumn::make('nombre')
                    ->searchable(),
                Tables\Columns\TextColumn::make('apellidos')
                    ->searchable(),
                Tables\Columns\TextColumn::make('sexo')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('fecha_nacimiento')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('celular')
                    ->searchable(),
                Tables\Columns\TextColumn::make('correo')
                    ->searchable(),
                Tables\Columns\TextColumn::make('direccion')
                    ->searchable(),
                Tables\Columns\TextColumn::make('distrito')
                    ->searchable(),
                Tables\Columns\TextColumn::make('estado_civil'),
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
                \Filament\Tables\Filters\SelectFilter::make('sexo')
                ->label('Sexo')
                ->options([
                    'Hombre' => 'Masculino',
                    'Mujer' => 'Femenino',
                ])
                ->native(false),
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
            'index' => Pages\ListPersonas::route('/'),
            'create' => Pages\CreatePersona::route('/create'),
            'edit' => Pages\EditPersona::route('/{record}/edit'),
        ];
    }
}
