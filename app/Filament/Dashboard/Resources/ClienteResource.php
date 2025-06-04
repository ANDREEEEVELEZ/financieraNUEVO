<?php

namespace App\Filament\Dashboard\Resources;

use App\Filament\Dashboard\Resources\ClienteResource\Pages;
use App\Models\Cliente;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use App\Filament\Widgets\ClienteStatsWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
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
                                Select::make('persona.distrito')->label('Distrito')->options([

                                    'Sullana' => 'Sullana',
                                    'Bellavista ' => 'Bellavista',
                                    'Ignacio Escudero' => 'Ignacio Escudero',
                                    'Querecotillo' => 'Querecotillo',
                                    'Marcavelica' => 'Marcavelica',
                                    'Salitral' => 'Salitral',
                                    'Lancones' => 'Lancones',
                                    'Miguel Checa' => 'Miguel Checa',
                                   
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
                                Forms\Components\Select::make('condicion_vivienda')
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
                                Forms\Components\Select::make('condicion_personal')
                                    ->options([
                                        'Capacitado' => 'Capacitado',
                                        'Iletrado' => 'Iletrado',
                                        'PEP ' => 'PEP',
                                    ])
                                    ->native(false)
                                    ->searchable()
                                    ->label('Condición Personal')
                                    ->required(),
                                Forms\Components\Select::make('estado_cliente')
                                    ->options([
                                        'Activo' => 'Activo',
                                        'Inactivo' => 'Inactivo',
                                    ])
                                    ->default('Activo')
                                    ->disabled()
                                    ->label('Estado Cliente')
                                    ->required()
                            ])->columns(2),
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        $columns = [
            Tables\Columns\TextColumn::make('persona.DNI')->label('DNI')->sortable()->searchable(),
            Tables\Columns\TextColumn::make('persona.nombre')->label('Nombre')->sortable()->searchable(),
            Tables\Columns\TextColumn::make('persona.apellidos')->label('Apellidos')->sortable()->searchable(),
            Tables\Columns\TextColumn::make('persona.celular')->label('Celular'),
            Tables\Columns\TextColumn::make('infocorp')->label('Infocorp'),
            Tables\Columns\TextColumn::make('ciclo')->label('Ciclo'),
            Tables\Columns\TextColumn::make('condicion_vivienda')->label('Condición de Vivienda'),
            Tables\Columns\TextColumn::make('actividad')->label('Actividad'),
            Tables\Columns\TextColumn::make('condicion_personal')->label('Condición Personal'),
            Tables\Columns\TextColumn::make('estado_cliente')
                ->label('Estado')
                ->badge()
                ->color(fn (string $state): string => match ($state) {
                    'Activo' => 'success',
                    'Inactivo' => 'danger',
                    default => 'warning',
                })
        ];

        // Agregar columna de asesor solo para roles administrativos al final
        if (auth()->user()->hasAnyRole(['super_admin', 'Jefe de operaciones', 'Jefe de creditos'])) {
            $columns[] = Tables\Columns\TextColumn::make('asesor.persona.nombre')
                ->label('Asesor')
                ->formatStateUsing(fn ($record) => 
                    $record->asesor ? ($record->asesor->persona->nombre . ' ' . $record->asesor->persona->apellidos) : '-'
                )
                ->sortable()
                ->searchable();
        }

        return $table->columns($columns)
            ->filters([
                Tables\Filters\SelectFilter::make('estado_cliente')
                    ->options([
                        'Activo' => 'Activos',
                        'Inactivo' => 'Inactivos',
                    ])
                    ->label('Estado')
                    ->default('Activo')
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when($data['value'], function (Builder $query, string $value): Builder {
                            return $query->where('estado_cliente', $value);
                        });
                    })
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('activar')
                    ->label('Activar')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn ($record) => $record->estado_cliente === 'Inactivo')
                    ->action(function ($record) {
                        $record->estado_cliente = 'Activo';
                        $record->save();
                    })
                    ->requiresConfirmation()
                    ->modalHeading('¿Activar cliente?')
                    ->modalDescription('¿Estás seguro de que quieres activar este cliente?')
                    ->modalSubmitActionLabel('Sí, activar')
                    ->modalCancelActionLabel('No, cancelar'),
            ]);
    }


    public static function getEloquentQuery(): Builder
    {
        $user = request()->user();

        $query = parent::getEloquentQuery();

        if ($user->hasRole('Asesor')) {
            $asesor = \App\Models\Asesor::where('user_id', $user->id)->first();

            if ($asesor) {
                $query->where('asesor_id', $asesor->id); // Filtrar registros por el ID del asesor correspondiente
            }
        }

        return $query;
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        return $record->persona->nombre . ' ' . $record->persona->apellidos;
    }

    public static function getWidgets(): array
    {
        return [
            ClienteStatsWidget::class,
        ];
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