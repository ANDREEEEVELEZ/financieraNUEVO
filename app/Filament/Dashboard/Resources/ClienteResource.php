<?php

namespace App\Filament\Dashboard\Resources;

use App\Filament\Dashboard\Resources\ClienteResource\Pages;
use App\Models\Cliente;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;

class ClienteResource extends Resource
{
    protected static ?string $model = Cliente::class;
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Tabs::make('Cliente')
                    ->tabs([
                        Tabs\Tab::make('Información Personal')
                            ->schema([
                                TextInput::make('persona.DNI')->label('DNI')->required()->maxLength(8)->minLength(8)->numeric(),
                                TextInput::make('persona.nombre')->label('Nombre')->required(),
                                TextInput::make('persona.apellidos')->label('Apellidos')->required(),
                                Select::make('persona.sexo')->label('Sexo')->required()->options([
                                    'Hombre' => 'Hombre',
                                    'Mujer' => 'Mujer',
                                ])->native(false),
                                DatePicker::make('persona.fecha_nacimiento')->label('Fecha de Nacimiento')->required(),
                                TextInput::make('persona.celular')->label('Celular')->maxLength(9)->minLength(9)->numeric()->required(),
                                TextInput::make('persona.correo')->label('Correo Electrónico')->email()->required(),
                                TextInput::make('persona.direccion')->label('Dirección')->required(),
                                Select::make('persona.Distrito')->label('Distrito')->options([

                                    'Sullana' => 'Sullana',
                                    'Bellavista ' => 'Bellavista',
                                    'Ignacio Escudero' => 'Ignacio Escudero',
                                    'Querecotillo' => 'Querecotillo',
                                    'Marcavelica' => 'Marcavelica',
                                    'Salitral' => 'Salitral',
                                    'Lancones' => 'Lancones',
                                    'Miguel Checa' => 'Miguel Checa',
                                    // Agrega más distritos según sea necesario
                                ])->native(false)->required(),
                                Select::make('persona.estado_civil')->label('Estado Civil')->options([
                                    'Soltero' => 'Soltero',
                                    'Casado' => 'Casado',
                                    'Divorciado' => 'Divorciado',
                                    'Viudo' => 'Viudo',
                                ])->native(false)->required(),
                            ])->columns(2),

                        Tabs\Tab::make('Información Cliente')
                            ->schema([
                                TextInput::make('infocorp')->label('Infocorp')->required(),
                                TextInput::make('ciclo')->label('Ciclo')->required(),
                                Forms\Components\Select::make('condicionVivienda')
                                    ->options([
                                        'Propia' => 'Propia',
                                        'Alquilada' => 'Alquilada',
                                        'Familiar' => 'Familiar',
                                    ])
                                    ->native(false)
                                    ->searchable()
                                    ->label('Condición de Vivienda')
                                    ->required(),
                                TextInput::make('actividad')->label('Actividad')->required(),
                                Forms\Components\Select::make('condicionPersonal')
                                    ->options([
                                        'Capacitado' => 'Capacitado',
                                        'Iletrado' => 'Iletrado',
                                        'PEP ' => 'PEP',
                                    ])
                                    ->native(false)
                                    ->searchable()
                                    ->label('Condición Personal')
                                    ->required(),
                                Forms\Components\Select::make('estadoCliente')
                                    ->options([
                                        'Activo' => 'Activo',
                                        'Inactivo' => 'Inactivo',
                                    ])
                                    ->native(false)
                                    ->searchable()
                                    ->label('Estado Cliente')
                                    ->required()
                            ])->columns(2),
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                // Sección: Información Personal
                Tables\Columns\TextColumn::make('persona.DNI')->label('DNI'),
                Tables\Columns\TextColumn::make('persona.nombre')->label('Nombre'),
                Tables\Columns\TextColumn::make('persona.apellidos')->label('Apellidos'),
                Tables\Columns\TextColumn::make('persona.sexo')->label('Sexo'),
                Tables\Columns\TextColumn::make('persona.fecha_nacimiento')->label('Fecha de Nacimiento')->date(),
                Tables\Columns\TextColumn::make('persona.celular')->label('Celular'),
                Tables\Columns\TextColumn::make('persona.correo')->label('Correo Electrónico'),
                Tables\Columns\TextColumn::make('persona.direccion')->label('Dirección'),
                Tables\Columns\TextColumn::make('persona.distrito')->label('Distrito'),
                Tables\Columns\TextColumn::make('persona.estado_civil')->label('Estado Civil'),

                // Sección: Información Cliente
                Tables\Columns\TextColumn::make('infocorp')->label('Infocorp'),
                Tables\Columns\TextColumn::make('ciclo')->label('Ciclo'),
                Tables\Columns\TextColumn::make('condicion_vivienda')->label('Condición de Vivienda'),
                Tables\Columns\TextColumn::make('actividad')->label('Actividad'),
                Tables\Columns\TextColumn::make('condicion_personal')->label('Condición Personal'),
                Tables\Columns\TextColumn::make('estado_cliente')->label('Estado Cliente'),
            ])
            ->filters([])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }


    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->with('persona');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClientes::route('/'),
            'create' => Pages\CreateCliente::route('/create'),
            'edit' => Pages\EditCliente::route('/{record}/edit'),
        ];
    }
}