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
    // Aquí se define el nombre singular que aparece en la interfaz
    public static function getModelLabel(): string
    {
        return 'Asesor';
    }
    // Aquí se define el nombre plural que aparece en la interfaz
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
        Forms\Components\Group::make([
            TextInput::make('DNI')->label('DNI')->required()->maxLength(8)->minLength(8)->numeric(),
            TextInput::make('nombre')->label('Nombre')->required(),
            TextInput::make('apellidos')->label('Apellidos')->required(),
            Select::make('sexo')->label('Sexo')->required()->options([
                'Femenino' => 'Femenino',
                'Masculino' => 'Masculino',
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
        ])->columns(2)->relationship('persona'),

            ]),
                    Tabs\Tab::make('Datos de Usuario')
                        ->schema([
                            Forms\Components\Group::make([
                                TextInput::make('name')->label('Nombre de Usuario')->required(),
                                TextInput::make('email')->label('Correo')->email()->required(),
                                TextInput::make('password')
                                    ->label('Contraseña')
                                    ->password()
                                    ->dehydrateStateUsing(fn ($state) => !empty($state) ? bcrypt($state) : null)
                                    ->dehydrated(fn ($state) => filled($state))
                                    ->required(fn ($livewire) => $livewire instanceof \Filament\Resources\Pages\CreateRecord),
                            ])->relationship('user'),
                        ]),
                    Tabs\Tab::make('Datos del Asesor')
                        ->schema([
                            TextInput::make('codigo_asesor')->nullable(),
                            DatePicker::make('fecha_ingreso')->nullable(),
                            Select::make('estado_asesor')
                                ->options([
                                    'Activo' => 'Activo',
                                    'Inactivo' => 'Inactivo'
                                ])
                                ->default('Activo')
                                ->required(),
                            ]),

                    ]),
            ]);
    }
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('persona.nombre')->label('Nombre') ->AlignLeft(),
                Tables\Columns\TextColumn::make('persona.apellidos')->label('Apellidos') ->AlignLeft(),
                Tables\Columns\TextColumn::make('persona.DNI')->label('DNI') ->AlignLeft(),
                Tables\Columns\TextColumn::make('persona.correo')->label('Correo') ->AlignLeft(),
                Tables\Columns\TextColumn::make('codigo_asesor')->label('Código Asesor') ->AlignLeft(),
                Tables\Columns\TextColumn::make('estado_asesor')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Activo' => 'success',
                        'Inactivo' => 'danger',
                        default => 'warning',
                    }),
                Tables\Columns\TextColumn::make('fecha_ingreso')->label('Fecha de Ingreso') ->AlignLeft(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('estado_asesor')
                    ->label('Estado')
                    ->options([
                        'Activo' => 'Activo',
                        'Inactivo' => 'Inactivo',
                    ])
                    ->default('Activo')
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('activar')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Activar Asesor')
                    ->modalDescription('¿Estás seguro de que quieres activar este asesor? Se reactivará su acceso al sistema.')
                    ->modalSubmitActionLabel('Sí, activar')
                    ->hidden(fn ($record): bool => $record->estado_asesor === 'Activo')
                    ->after(function ($record) {                        // Activar el asesor y su cuenta de usuario
                        $record->update(['estado_asesor' => 'Activo']);

                        if ($record->user) {
                            $record->user->update(['active' => true]);

                            // Asegurar que tenga el rol de Asesor
                            $role = \Spatie\Permission\Models\Role::findByName('Asesor');
                            if ($role) {
                                // Limpiar caché de permisos
                                app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

                                // Asignar rol y permisos
                                $record->user->syncRoles([$role]);
                                $record->user->syncPermissions($role->permissions);
                            }
                        }

                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title('Asesor Activado')
                            ->body('El asesor ha sido activado exitosamente con todos sus permisos.')
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\BulkAction::make('activarSeleccionados')
                        ->label('Activar Seleccionados')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Activar Asesores Seleccionados')
                        ->modalDescription('¿Estás seguro de que quieres activar los asesores seleccionados? Se reactivará su acceso al sistema.')
                        ->modalSubmitActionLabel('Sí, activar')
                        ->after(function ($records) {
                            $count = 0;
                            $records->each(function ($record) use (&$count) {
                                if ($record->estado_asesor === 'Inactivo') {
                                    // Activar el asesor y su cuenta de usuario
                                    $record->update(['estado_asesor' => 'Activo']);

                                    if ($record->user) {
                                        $record->user->update(['active' => true]);

                                        // Asegurar que tenga el rol de Asesor
                                        $role = \Spatie\Permission\Models\Role::findByName('Asesor');
                                        if ($role) {
                                            // Limpiar caché de permisos
                                            app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

                                            // Asignar rol y permisos
                                            $record->user->syncRoles([$role]);
                                            $record->user->syncPermissions($role->permissions);
                                        }
                                    }
                                    $count++;
                                }
                            });

                            if ($count > 0) {
                                \Filament\Notifications\Notification::make()
                                    ->success()
                                    ->title('Asesores Activados')
                                    ->body("Se han activado $count asesores exitosamente.")
                                    ->send();
                            }
                        })
                        ->deselectRecordsAfterCompletion()
                        ->hidden(fn ($records) => !$records || !$records->contains('estado_asesor', 'Inactivo')),
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
            'index' => Pages\ListAsesors::route('/'),
            'create' => Pages\CreateAsesor::route('/create'),
            'edit' => Pages\EditAsesor::route('/{record}/edit'),
        ];
    }
}
