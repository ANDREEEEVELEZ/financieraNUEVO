<?php

namespace App\Filament\Dashboard\Resources;

use App\Filament\Dashboard\Resources\GrupoResource\Pages;
use App\Filament\Dashboard\Resources\GrupoResource\RelationManagers;
use App\Models\Grupo;
use App\Models\Cliente;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class GrupoResource extends Resource
{
    protected static ?string $model = Grupo::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('nombre_grupo')
                    ->maxLength(255),
                Forms\Components\DatePicker::make('fecha_registro'),
                Forms\Components\TextInput::make('calificacion_grupo')
                    ->maxLength(255),
                Forms\Components\TextInput::make('estado_grupo')
                    ->default('Activo')
                    ->maxLength(255),
                Forms\Components\Select::make('clientes')
                    ->label('Integrantes')
                    ->multiple()
                    ->relationship('clientes', 'id')
                    ->options(Cliente::with('persona')->get()->mapWithKeys(function($cliente) {
                        return [$cliente->id => $cliente->persona->nombre . ' ' . $cliente->persona->apellidos . ' (DNI: ' . $cliente->persona->DNI . ')'];
                    })->toArray())
                    ->required(),
                Forms\Components\TextInput::make('numero_integrantes')
                    ->label('Numero de Integrantes')
                    ->disabled()
                    ->dehydrated(false)
                    ->reactive()
                    ->afterStateHydrated(function ($component, $state, $record) {
                        if ($record) {
                            $component->state($record->clientes()->count());
                        }
                    })
                    ->afterStateUpdated(function ($state, $set, $get) {
                        $set('numero_integrantes', is_array($get('clientes')) ? count($get('clientes')) : 0);
                    }),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nombre_grupo')
                    ->searchable(),
                Tables\Columns\TextColumn::make('numero_integrantes_real')
                    ->label('N° Integrantes')
                    ->sortable(),
                Tables\Columns\TextColumn::make('fecha_registro')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('calificacion_grupo')
                    ->searchable(),
                Tables\Columns\TextColumn::make('estado_grupo')
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('integrantes_nombres')
                    ->label('Integrantes')
                    ->limit(50)
                    ->tooltip(fn($record) => $record->integrantes_nombres),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('imprimir_contratos')
                    ->label('Imprimir Contratos')
                    ->icon('heroicon-o-printer')
                    ->color('success')
                    ->url(fn($record) => $record ? route('contratos.grupo.imprimir', $record->id) : '#')
                    ->openUrlInNewTab()
                    ->visible(fn($record) => $record !== null),
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
            'index' => Pages\ListGrupos::route('/'),
            'create' => Pages\CreateGrupo::route('/create'),
            'edit' => Pages\EditGrupo::route('/{record}/edit'),
        ];
    }
}
