<?php

namespace App\Filament\Dashboard\Resources;

use App\Filament\Dashboard\Resources\ProductoFinancieroResource\Pages;
use App\Models\ProductoFinanciero;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ProductoFinancieroResource extends Resource
{
    protected static ?string $model = ProductoFinanciero::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationLabel = 'Productos Financieros';

    protected static ?string $modelLabel = 'Producto Financiero';

    protected static ?string $pluralModelLabel = 'Productos Financieros';

    protected static ?string $navigationGroup = 'Configuración';

    protected static ?int $navigationSort = 100;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Información General')
                    ->description('Datos básicos del producto financiero')
                    ->schema([
                        Forms\Components\TextInput::make('codigo')
                            ->label('Código')
                            ->placeholder('CG-BÁSICO')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(20),
                        Forms\Components\TextInput::make('nombre')
                            ->label('Nombre del Producto')
                            ->placeholder('Crédito Grupal Básico')
                            ->required()
                            ->maxLength(100),
                        Forms\Components\Select::make('tipo')
                            ->label('Tipo de Producto')
                            ->options([
                                'grupal' => 'Crédito Grupal',
                                'individual' => 'Crédito Individual',
                                'hipotecario' => 'Crédito Hipotecario',
                            ])
                            ->required()
                            ->native(false),
                        Forms\Components\Toggle::make('activo')
                            ->label('¿Activo?')
                            ->default(true)
                            ->helperText('Los productos inactivos no aparecerán al crear préstamos'),
                    ])->columns(2),

                Forms\Components\Section::make('Tasas')
                    ->description('Configuración de tasas de interés y mora (en formato decimal)')
                    ->schema([
                        Forms\Components\TextInput::make('tasa_interes')
                            ->label('Tasa de Interés Anual')
                            ->numeric()
                            ->step(0.0001)
                            ->required()
                            ->helperText('Formato decimal: 0.10 = 10%, 0.15 = 15%'),
                        Forms\Components\TextInput::make('tasa_mora')
                            ->label('Tasa de Mora Mensual')
                            ->numeric()
                            ->step(0.0001)
                            ->default(0.05)
                            ->helperText('Formato decimal: 0.05 = 5%'),
                        Forms\Components\Toggle::make('permite_condonacion_mora')
                            ->label('¿Permite Condonación de Mora?')
                            ->default(true)
                            ->helperText('Si está activo, se podrán crear ajustes para perdonar mora'),
                    ])->columns(3),

                Forms\Components\Section::make('Límites')
                    ->description('Montos y plazos permitidos')
                    ->schema([
                        Forms\Components\TextInput::make('monto_minimo')
                            ->label('Monto Mínimo')
                            ->prefix('S/')
                            ->numeric()
                            ->step(100)
                            ->default(1000)
                            ->required(),
                        Forms\Components\TextInput::make('monto_maximo')
                            ->label('Monto Máximo')
                            ->prefix('S/')
                            ->numeric()
                            ->step(1000)
                            ->default(50000)
                            ->required(),
                        Forms\Components\TextInput::make('plazo_minimo_meses')
                            ->label('Plazo Mínimo')
                            ->suffix('meses')
                            ->numeric()
                            ->minValue(1)
                            ->default(3)
                            ->required(),
                        Forms\Components\TextInput::make('plazo_maximo_meses')
                            ->label('Plazo Máximo')
                            ->suffix('meses')
                            ->numeric()
                            ->maxValue(120)
                            ->default(24)
                            ->required(),
                    ])->columns(4),

                Forms\Components\Section::make('Configuración Avanzada')
                    ->description('Reglas adicionales en formato JSON')
                    ->collapsed()
                    ->schema([
                        Forms\Components\KeyValue::make('config_json')
                            ->label('Configuración Adicional')
                            ->keyLabel('Parámetro')
                            ->valueLabel('Valor')
                            ->addActionLabel('Agregar parámetro')
                            ->helperText('Ejemplo: calculo_interes = flat, permite_prepago = true'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('codigo')
                    ->label('Código')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('primary'),
                Tables\Columns\TextColumn::make('nombre')
                    ->label('Nombre')
                    ->searchable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('tipo')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'grupal' => 'success',
                        'individual' => 'info',
                        'hipotecario' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('tasa_interes')
                    ->label('Tasa Interés')
                    ->suffix('%')
                    ->sortable(),
                Tables\Columns\TextColumn::make('tasa_mora')
                    ->label('Tasa Mora')
                    ->suffix('%')
                    ->sortable(),
                Tables\Columns\IconColumn::make('permite_condonacion_mora')
                    ->label('Condonación')
                    ->boolean(),
                Tables\Columns\TextColumn::make('monto_minimo')
                    ->label('Mín.')
                    ->money('PEN')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('monto_maximo')
                    ->label('Máx.')
                    ->money('PEN')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('activo')
                    ->label('Activo')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('tipo')
                    ->options([
                        'grupal' => 'Grupal',
                        'individual' => 'Individual',
                        'hipotecario' => 'Hipotecario',
                    ]),
                Tables\Filters\TernaryFilter::make('activo')
                    ->label('Estado')
                    ->placeholder('Todos')
                    ->trueLabel('Solo activos')
                    ->falseLabel('Solo inactivos'),
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
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProductoFinancieros::route('/'),
            'create' => Pages\CreateProductoFinanciero::route('/create'),
            'edit' => Pages\EditProductoFinanciero::route('/{record}/edit'),
        ];
    }
}
