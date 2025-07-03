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
  protected static ?string $navigationIcon = 'heroicon-o-user-plus';



    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Tabs::make('Cliente')
                    ->tabs([
                        Tabs\Tab::make('Información Personal')->icon('heroicon-o-user')
                            ->schema([
                                TextInput::make('persona.DNI')
                                    ->label('DNI')
                                    ->required()
                                    ->maxLength(8)
                                    ->minLength(8)
                                    ->numeric()
                                    ->prefixIcon('heroicon-o-identification')
                                    ->rule('regex:/^[0-9]{8}$/')
                                    ->extraAttributes(['inputmode' => 'numeric', 'pattern' => '[0-9]*'])
                                    ->mask('99999999')
                                    ->disabled(fn ($livewire) => $livewire instanceof \Filament\Resources\Pages\EditRecord),
                                TextInput::make('persona.nombre')->label('Nombre')->required()->prefixIcon('heroicon-o-user')->rule('regex:/^[\pL\s]+$/u')
                                    ->disabled(fn ($livewire) => $livewire instanceof \Filament\Resources\Pages\EditRecord),
                                TextInput::make('persona.apellidos')->label('Apellidos')->required()->prefixIcon('heroicon-o-user')->rule('regex:/^[\pL\s]+$/u')
                                    ->disabled(fn ($livewire) => $livewire instanceof \Filament\Resources\Pages\EditRecord),
                                Select::make('persona.sexo')->label('Sexo')->required()->prefixIcon('heroicon-o-adjustments-horizontal')->options([
                                      'Femenino' => 'Femenino',
                                    'Masculino' => 'Masculino',
                                ])->native(false)
                                    ->disabled(fn ($livewire) => $livewire instanceof \Filament\Resources\Pages\EditRecord),
                                DatePicker::make('persona.fecha_nacimiento')->label('Fecha de Nacimiento')->required()->prefixIcon('heroicon-o-calendar')
                                    ->disabled(fn ($livewire) => $livewire instanceof \Filament\Resources\Pages\EditRecord),
                                TextInput::make('persona.celular')
                                    ->label('Celular')
                                    ->maxLength(9)
                                    ->minLength(9)
                                    ->numeric()
                                    ->required()
                                    ->prefixIcon('heroicon-o-phone')
                                    ->rule('regex:/^[0-9]{9}$/')
                                    ->extraAttributes(['inputmode' => 'numeric', 'pattern' => '[0-9]*'])
                                    ->mask('999999999'),
                                TextInput::make('persona.correo')->label('Correo Electrónico')->email()->required()->prefixIcon('heroicon-o-envelope'),
                                TextInput::make('persona.direccion')->label('Dirección')->required()->prefixIcon('heroicon-o-map-pin'),
                                Select::make('persona.distrito')->label('Distrito')->prefixIcon('heroicon-o-map-pin')->options([

                                    'Sullana' => 'Sullana',
                                    'Bellavista ' => 'Bellavista',
                                    'Ignacio Escudero' => 'Ignacio Escudero',
                                    'Querecotillo' => 'Querecotillo',
                                    'Marcavelica' => 'Marcavelica',
                                    'Salitral' => 'Salitral',
                                    'Lancones' => 'Lancones',
                                    'Miguel Checa' => 'Miguel Checa',

                                ])->native(false)->required(),
                                Select::make('persona.estado_civil')->label('Estado Civil')->prefixIcon('heroicon-o-heart')->options([
                                    'Soltero' => 'Soltero',
                                    'Casado' => 'Casado',
                                    'Divorciado' => 'Divorciado',
                                    'Viudo' => 'Viudo',
                                ])->native(false)->required(),
                            ])->columns(2),

                        Tabs\Tab::make('Información Cliente')
                            ->schema([
                                TextInput::make('infocorp')->label('Infocorp')->required()->prefixIcon('heroicon-o-document-magnifying-glass'),
                                TextInput::make('ciclo')->label('Ciclo')->required() ->prefixIcon('heroicon-o-arrow-path-rounded-square'),
                                Forms\Components\Select::make('condicion_vivienda')
                                    ->options([
                                        'Propia' => 'Propia',
                                        'Alquilada' => 'Alquilada',
                                        'Familiar' => 'Familiar',
                                    ])
                                    ->native(false)
                                    ->searchable()
                                    ->label('Condición de Vivienda')
                                    ->prefixIcon('heroicon-o-home-modern')
                                    ->required(),
                                TextInput::make('actividad')->label('Actividad')->required() ->prefixIcon('heroicon-o-briefcase'),
                                Forms\Components\Select::make('condicion_personal')
                                    ->options([
                                        'Capacitado' => 'Capacitado',
                                        'Iletrado' => 'Iletrado',
                                        'PEP ' => 'PEP',
                                    ])
                                    ->native(false)
                                    ->searchable()
                                    ->label('Condición Personal')
                                    ->prefixIcon('heroicon-o-user-circle')
                                    ->required(),
                                Forms\Components\Select::make('estado_cliente')
                                   ->prefixIcon('heroicon-o-check-circle')
                                ->options([
                                        'Activo' => 'Activo',
                                        'Inactivo' => 'Inactivo',
                                    ])
                                    ->default('Activo')
                                    ->disabled()
                                    ->label('Estado Cliente')
                                    ->required(),
                                Forms\Components\Select::make('asesor_id')
                                    ->label('Asesor responsable')
                                    ->options(function () {
                                        return \App\Models\Asesor::where('estado_asesor', 'Activo')
                                            ->with('persona')
                                            ->get()
                                            ->mapWithKeys(function ($asesor) {
                                                return [$asesor->id => $asesor->persona->nombre . ' ' . $asesor->persona->apellidos];
                                            });
                                    })
                                    ->searchable()
                                    ->required(fn () => \Illuminate\Support\Facades\Auth::check() && \Illuminate\Support\Facades\Auth::user()->hasAnyRole(['super_admin', 'Jefe de operaciones']))
                                    ->visible(fn () => \Illuminate\Support\Facades\Auth::check() && \Illuminate\Support\Facades\Auth::user()->hasAnyRole(['super_admin', 'Jefe de operaciones']))
                                    ->helperText('Seleccione el asesor responsable para este cliente.')
                                    ->prefixIcon('heroicon-o-user-group'),
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
                }),
            Tables\Columns\TextColumn::make('grupos')
                ->label('Grupos Pertenecientes')
                ->formatStateUsing(fn ($record) => $record->grupos->pluck('nombre_grupo')->implode(' - ') ?: '-')
                ->searchable(false),
        ];

        // Agregar columna de asesor solo para roles administrativos al final
        if (\Illuminate\Support\Facades\Auth::check() && \Illuminate\Support\Facades\Auth::user()->hasAnyRole(['super_admin', 'Jefe de operaciones', 'Jefe de creditos'])) {
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
                // Filtro de estado existente
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
                    }),
                // Filtro de asesor solo para super_admin y jefes
                Tables\Filters\SelectFilter::make('asesor_id')
                    ->label('Nombre de Asesor')
                    ->options(function () {
                        return \App\Models\Asesor::where('estado_asesor', 'Activo')
                            ->with('persona')
                            ->get()
                            ->mapWithKeys(function ($asesor) {
                                return [$asesor->id => $asesor->persona->nombre . ' ' . $asesor->persona->apellidos];
                            });
                    })
                    ->visible(fn () => \Illuminate\Support\Facades\Auth::check() && \Illuminate\Support\Facades\Auth::user()->hasAnyRole(['super_admin', 'Jefe de operaciones']))
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when($data['value'], function (Builder $query, $value) {
                            return $query->where('asesor_id', $value);
                        });
                    }),
                // Filtro de grupo (con grupo/sin grupo) visible para todos
                Tables\Filters\SelectFilter::make('grupo')
                    ->label('Grupo')
                    ->options([
                        'con_grupo' => 'Con grupo',
                        'sin_grupo' => 'Sin grupo',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if ($data['value'] === 'con_grupo') {
                            // Clientes que tienen al menos un grupo activo
                            return $query->whereHas('grupos', function ($q) {
                                $q->where('estado_grupo', 'Activo');
                            });
                        } elseif ($data['value'] === 'sin_grupo') {
                            // Clientes que no tienen ningún grupo activo
                            return $query->whereDoesntHave('grupos', function ($q) {
                                $q->where('estado_grupo', 'Activo');
                            });
                        }
                        return $query;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->icon('heroicon-o-pencil-square'),
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
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->label('Desactivar Seleccionados')
                        ->modalHeading('Desactivar Clientes Seleccionados')
                        ->modalDescription('¿Estás seguro de que quieres desactivar los clientes seleccionados? Se deshabilitará su acceso al sistema.')
                        ->modalSubmitActionLabel('Sí, desactivar')
                        ->action(function ($records) {
                            $count = 0;
                            $inactivos = 0;
                            $records->each(function ($record) use (&$count, &$inactivos) {
                                if ($record->estado_cliente === 'Activo') {
                                    $record->update(['estado_cliente' => 'Inactivo']);
                                    $count++;
                                } else {
                                    $inactivos++;
                                }
                            });

                            if ($count > 0) {
                                \Filament\Notifications\Notification::make()
                                    ->success()
                                    ->title('Clientes Desactivados')
                                    ->body("Se han desactivado $count clientes exitosamente." . ($inactivos > 0 ? " $inactivos ya estaban inactivos." : ""))
                                    ->send();
                            } elseif ($inactivos > 0) {
                                \Filament\Notifications\Notification::make()
                                    ->warning()
                                    ->title('Sin cambios')
                                    ->body("Los clientes seleccionados ya están inactivos.")
                                    ->send();
                            }
                        }),
                    Tables\Actions\BulkAction::make('activarSeleccionados')
                        ->label('Activar Seleccionados')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Activar Clientes Seleccionados')
                        ->modalDescription('¿Estás seguro de que quieres activar los clientes seleccionados?')
                        ->modalSubmitActionLabel('Sí, activar')
                        ->action(function ($records) {
                            $count = 0;
                            $activos = 0;
                            $records->each(function ($record) use (&$count, &$activos) {
                                if ($record->estado_cliente === 'Inactivo') {
                                    $record->update(['estado_cliente' => 'Activo']);
                                    $count++;
                                } else {
                                    $activos++;
                                }
                            });

                            if ($count > 0) {
                                \Filament\Notifications\Notification::make()
                                    ->success()
                                    ->title('Clientes Activados')
                                    ->body("Se han activado $count clientes exitosamente." . ($activos > 0 ? " $activos ya estaban activos." : ""))
                                    ->send();
                            } elseif ($activos > 0) {
                                \Filament\Notifications\Notification::make()
                                    ->warning()
                                    ->title('Sin cambios')
                                    ->body("Los clientes seleccionados ya están activos.")
                                    ->send();
                            }
                        })
                        ->deselectRecordsAfterCompletion(),
                        // ->hidden(fn ($records) => !$records || !$records->contains('estado_cliente', 'Inactivo')), // Removido para permitir siempre la reactivación
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
