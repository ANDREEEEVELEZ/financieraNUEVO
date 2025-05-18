<?php

namespace App\Filament\Dashboard\Resources;

use App\Filament\Dashboard\Resources\AsesorResource\Pages;
use App\Filament\Dashboard\Resources\AsesorResource\RelationManagers;
use App\Models\Asesor;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;

class AsesorResource extends Resource
{
    protected static ?string $model = Asesor::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    // Aquí defines el nombre singular que aparece en la interfaz
    public static function getModelLabel(): string
    {
        return 'Asesor';
    }

    // Aquí defines el nombre plural que aparece en la interfaz
    public static function getPluralModelLabel(): string
    {
        return 'Asesores';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Tabs::make('DatosAsesor')
                    ->tabs([
                        Tabs\Tab::make('Información Personal')
                            ->schema([
                                TextInput::make('DNI')->label('DNI')->required()->maxLength(8)->minLength(8)->numeric(),
                                TextInput::make('nombre')->label('Nombre')->required(),
                                TextInput::make('apellidos')->label('Apellidos')->required(),
                                Select::make('sexo')->label('Sexo')->required()->options([
                                    'Hombre' => 'Hombre',
                                    'Mujer' => 'Mujer',
                                ])->native(false),
                                DatePicker::make('fecha_nacimiento')->label('Fecha de Nacimiento')->required(),
                                TextInput::make('celular')->label('Celular')->maxLength(9)->minLength(9)->numeric()->required(),
                                TextInput::make('correo')->label('Correo Electrónico')->email()->required(),
                                TextInput::make('direccion')->label('Dirección')->required(),
                                Select::make('distrito')->label('Distrito')->options([
                                    'Sullana' => 'Sullana',
                                    'Bellavista' => 'Bellavista',
                                    'Ignacio Escudero' => 'Ignacio Escudero',
                                    'Querecotillo' => 'Querecotillo',
                                    'Marcavelica' => 'Marcavelica',
                                    'Salitral' => 'Salitral',
                                    'Lancones' => 'Lancones',
                                    'Miguel Checa' => 'Miguel Checa',
                                ])->native(false)->required(),
                                Select::make('estado_civil')->label('Estado Civil')->options([
                                    'Soltero' => 'Soltero',
                                    'Casado' => 'Casado',
                                    'Divorciado' => 'Divorciado',
                                    'Viudo' => 'Viudo',
                                ])->native(false)->required(),
                            ])->columns(2),

                        Tabs\Tab::make('Datos de Usuario')
                            ->schema([
                                TextInput::make('name')->required(),
                                TextInput::make('email')->email()->required(),
                                TextInput::make('password')->password()->required(),
                            ]),

                        Tabs\Tab::make('Datos del Asesor')
                            ->schema([
                                TextInput::make('codigo_asesor')->nullable(),
                                DatePicker::make('fecha_ingreso')->nullable(),
                                Select::make('estado_asesor')->options([
                                    'activo' => 'Activo',
                                    'inactivo' => 'Inactivo'
                                ])->required(),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('persona.nombre')->label('Nombre'),
                Tables\Columns\TextColumn::make('persona.apellidos')->label('Apellidos'),
                Tables\Columns\TextColumn::make('persona.DNI')->label('DNI'),
                Tables\Columns\TextColumn::make('persona.correo')->label('Correo'),
                Tables\Columns\TextColumn::make('codigo_asesor')->label('Código Asesor'),
                Tables\Columns\TextColumn::make('estado_asesor')->label('Estado'),
                Tables\Columns\TextColumn::make('fecha_ingreso')->label('Fecha de Ingreso'),
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
            'index' => Pages\ListAsesores::route('/'),
            'create' => Pages\CreateAsesor::route('/create'),
            'edit' => Pages\EditAsesor::route('/{record}/edit'),
        ];
    }
}
