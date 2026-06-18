<?php

namespace App\Filament\Dashboard\Resources;

use App\Filament\Dashboard\Resources\AsesorResource\Pages;
use App\Filament\Dashboard\Resources\AsesorResource\RelationManagers;
use App\Models\Asesor;
use App\Rules\UniqueDNI;
use App\Rules\UniqueCelular;
use App\Rules\UniqueCorreo;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\Rule;
use App\Models\User;


class AsesorResource extends Resource
{
    protected static ?string $model = Asesor::class;
  protected static ?string $navigationGroup = 'Configuración';
    protected static ?string $navigationIcon = 'heroicon-o-user-plus';


    public static function getModelLabel(): string
    {
        return 'Asesor';
    }

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
                    Tabs\Tab::make('Información Personal') ->icon('heroicon-o-user')
    ->schema([
        Forms\Components\Group::make([
            TextInput::make('DNI')
                ->label('DNI')
                ->required()
                ->maxLength(8)
                ->minLength(8)
                ->numeric()
                ->prefixIcon('heroicon-o-identification')
                ->rule('regex:/^[0-9]{8}$/')
                ->rules(function ($livewire) {
                    if ($livewire instanceof \Filament\Resources\Pages\EditRecord) {
                        return [new UniqueDNI($livewire->record->persona_id)];
                    }
                    return [new UniqueDNI()];
                })
                ->live(debounce: 800)
                ->afterStateUpdated(function ($state, callable $get, callable $set) {
                    $set('dni_error', '');

                    $value = trim((string) $state);

                    if (strlen($value) === 8 && ctype_digit($value)) {
                        if (\App\Models\Persona::where('DNI', $value)->exists()) {
                            $set('dni_error', 'Este DNI ya está registrado en el sistema.');
                        }
                    }
                })
                ->helperText(fn (callable $get) => new HtmlString(
                    $get('dni_error')
                        ? '<span class="text-danger-600 dark:text-danger-400">' . e($get('dni_error')) . '</span>'
                        : '<span class="text-gray-500 dark:text-gray-400">Ingrese 8 dígitos</span>'
                ))
                ->extraAttributes(fn (callable $get) => array_merge(
                    ['inputmode' => 'numeric', 'pattern' => '[0-9]*'],
                    $get('dni_error') ? ['style' => 'border-color: #ef4444;'] : []
                ))
                ->mask('99999999')
                ->disabled(fn ($livewire) => $livewire instanceof \Filament\Resources\Pages\EditRecord),
            TextInput::make('nombre')
                ->label('Nombre')
                ->required()
                ->prefixIcon('heroicon-o-user')
                ->rule('regex:/^[\pL\s\ñÑ]+$/u')
                ->dehydrateStateUsing(fn ($state) => strtoupper($state))
                ->formatStateUsing(fn ($state) => strtoupper($state))
                ->disabled(fn ($livewire) => $livewire instanceof \Filament\Resources\Pages\EditRecord),
            TextInput::make('apellidos')
                ->label('Apellidos')
                ->required()
                ->prefixIcon('heroicon-o-user')
                ->rule('regex:/^[\pL\s\ñÑ]+$/u')
                ->dehydrateStateUsing(fn ($state) => strtoupper($state))
                ->formatStateUsing(fn ($state) => strtoupper($state))
                ->disabled(fn ($livewire) => $livewire instanceof \Filament\Resources\Pages\EditRecord),
            Select::make('sexo')
                ->label('Sexo')
                ->required()
                ->prefixIcon('heroicon-o-adjustments-horizontal')
                ->options([
                    'FEMENINO' => 'FEMENINO',
                    'MASCULINO' => 'MASCULINO',
                ])
                ->native(false)
                ->disabled(fn ($livewire) => $livewire instanceof \Filament\Resources\Pages\EditRecord),
            DatePicker::make('fecha_nacimiento')->label('Fecha de Nacimiento')->required()->prefixIcon('heroicon-o-calendar')
    ->disabled(fn ($livewire) => $livewire instanceof \Filament\Resources\Pages\EditRecord),
            TextInput::make('celular')
                ->label('Celular')
                ->maxLength(9)
                ->minLength(9)
                ->numeric()
                ->required()
                ->prefixIcon('heroicon-o-phone')
                ->rule('regex:/^[0-9]{9}$/')
                ->rules(function ($livewire) {
                    if ($livewire instanceof \Filament\Resources\Pages\EditRecord) {
                        return [new UniqueCelular($livewire->record->persona_id)];
                    }
                    return [new UniqueCelular()];
                })
                ->live(debounce: 800)
                ->afterStateUpdated(function ($state, callable $get, callable $set) {
                    $set('celular_error', '');

                    $value = trim((string) $state);

                    if (strlen($value) === 9 && ctype_digit($value)) {
                        if (\App\Models\Persona::where('celular', $value)->exists()) {
                            $set('celular_error', 'Este número de celular ya está registrado en el sistema.');
                        }
                    }
                })
                ->helperText(fn (callable $get) => new HtmlString(
                    $get('celular_error')
                        ? '<span class="text-danger-600 dark:text-danger-400">' . e($get('celular_error')) . '</span>'
                        : '<span class="text-gray-500 dark:text-gray-400">Ingrese 9 dígitos</span>'
                ))
                ->extraAttributes(fn (callable $get) => array_merge(
                    ['inputmode' => 'numeric', 'pattern' => '[0-9]*'],
                    $get('celular_error') ? ['style' => 'border-color: #ef4444;'] : []
                ))
                ->mask('999999999'),
            TextInput::make('correo')
                ->label('Correo Electrónico')
                ->email()
                ->required()
                ->prefixIcon('heroicon-o-envelope')
                ->rules(function ($livewire) {
                    if ($livewire instanceof \Filament\Resources\Pages\EditRecord) {
                        return [new UniqueCorreo($livewire->record->persona_id)];
                    }
                    return [new UniqueCorreo()];
                })
                ->live(debounce: 800)
                ->afterStateUpdated(function ($state, callable $get, callable $set) {
                    $set('correo_error', '');

                    $value = trim(strtolower((string) $state));

                    if (filter_var($value, FILTER_VALIDATE_EMAIL) && \App\Models\Persona::whereRaw('LOWER(correo) = ?', [$value])->exists()) {
                        $set('correo_error', 'Este correo electrónico ya está registrado en el sistema.');
                    }
                })
                ->helperText(fn (callable $get) => new HtmlString(
                    $get('correo_error')
                        ? '<span class="text-danger-600 dark:text-danger-400">' . e($get('correo_error')) . '</span>'
                        : '<span class="text-gray-500 dark:text-gray-400">Ejemplo: usuario@gmail.com</span>'
                ))
                ->extraAttributes(fn (callable $get) => $get('correo_error') ? ['style' => 'border-color: #ef4444;'] : [])
                ->dehydrateStateUsing(fn ($state) => strtolower($state))
                ->formatStateUsing(fn ($state) => strtolower($state)),
            TextInput::make('direccion')
                ->label('Dirección')
                ->required()
                ->prefixIcon('heroicon-o-map-pin')
                ->dehydrateStateUsing(fn ($state) => strtoupper($state))
                ->formatStateUsing(fn ($state) => strtoupper($state)),
            Select::make('distrito')
                ->label('Distrito')
                ->prefixIcon('heroicon-o-map-pin')
                ->options([
                    'SULLANA' => 'SULLANA',
                    'BELLAVISTA' => 'BELLAVISTA',
                    'IGNACIO ESCUDERO' => 'IGNACIO ESCUDERO',
                    'QUERECOTILLO' => 'QUERECOTILLO',
                    'MARCAVELICA' => 'MARCAVELICA',
                    'SALITRAL' => 'SALITRAL',
                    'LANCONES' => 'LANCONES',
                    'MIGUEL CHECA' => 'MIGUEL CHECA',
                ])
                ->native(false)
                ->required(),
            Select::make('estado_civil')
                ->label('Estado Civil')
                ->prefixIcon('heroicon-o-heart')
                ->options([
                    'SOLTERO' => 'SOLTERO',
                    'CASADO' => 'CASADO',
                    'DIVORCIADO' => 'DIVORCIADO',
                    'VIUDO' => 'VIUDO',
                ])
                ->native(false)
                ->required(),
        ])->columns(['default' => 1, 'sm' => 2])->relationship('persona'),

            ]),
                    Tabs\Tab::make('Datos de Usuario')->icon('heroicon-o-cog-6-tooth')
                        ->schema([
                            Forms\Components\Group::make([
                                TextInput::make('name')
                                    ->label('Nombre de Usuario')
                                    ->required()
                                    ->prefixIcon('heroicon-o-user')
                                    ->rules([Rule::unique('users', 'name')])
                                    ->live(debounce: 800)
                                    ->afterStateUpdated(function ($state, callable $set) {
                                        $set('name_error', '');

                                        $value = trim((string) $state);

                                        if ($value !== '' && User::whereRaw('LOWER(name) = ?', [strtolower($value)])->exists()) {
                                            $set('name_error', 'Este nombre de usuario ya está registrado en el sistema.');
                                        }
                                    })
                                    ->helperText(fn (callable $get) => new HtmlString(
                                        $get('name_error')
                                            ? '<span class="text-danger-600 dark:text-danger-400">' . e($get('name_error')) . '</span>'
                                            : '<span class="text-gray-500 dark:text-gray-400">Nombre visible para iniciar sesión</span>'
                                    ))
                                    ->extraAttributes(fn (callable $get) => $get('name_error') ? ['style' => 'border-color: #ef4444;'] : [])
                                    ->disabled(fn ($livewire) => $livewire instanceof \Filament\Resources\Pages\EditRecord),
                                TextInput::make('email')
                                    ->label('Correo')
                                    ->email()
                                    ->required()
                                    ->prefixIcon('heroicon-o-envelope')
                                    ->rules(function ($livewire) {
                                        if ($livewire instanceof \Filament\Resources\Pages\EditRecord) {
                                            return [Rule::unique('users', 'email')->ignore($livewire->record->user_id)];
                                        }

                                        return [Rule::unique('users', 'email')];
                                    })
                                    ->live(debounce: 800)
                                    ->afterStateUpdated(function ($state, callable $get, callable $set) {
                                        $set('user_email_error', '');

                                        $value = trim(strtolower((string) $state));

                                        if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
                                            $query = User::whereRaw('LOWER(email) = ?', [$value]);
                                            $userId = $get('user_id');

                                            if ($userId) {
                                                $query->where('id', '!=', $userId);
                                            }

                                            if ($query->exists()) {
                                                $set('user_email_error', 'Este correo ya está registrado en el sistema.');
                                            }
                                        }
                                    })
                                    ->helperText(fn (callable $get) => new HtmlString(
                                        $get('user_email_error')
                                            ? '<span class="text-danger-600 dark:text-danger-400">' . e($get('user_email_error')) . '</span>'
                                            : '<span class="text-gray-500 dark:text-gray-400">Correo de acceso del usuario</span>'
                                    ))
                                    ->extraAttributes(fn (callable $get) => $get('user_email_error') ? ['style' => 'border-color: #ef4444;'] : [])
                                    ->dehydrateStateUsing(fn ($state) => strtolower($state))
                                    ->formatStateUsing(fn ($state) => strtolower($state)),
                                TextInput::make('password')
                                    ->prefixIcon('heroicon-o-lock-closed')
                                    ->label('Contraseña')
                                    ->password()
                                    ->dehydrateStateUsing(fn ($state) => !empty($state) ? bcrypt($state) : null)
                                    ->dehydrated(fn ($state) => filled($state))
                                    ->required(fn ($livewire) => $livewire instanceof \Filament\Resources\Pages\CreateRecord),
                            ])->relationship('user'),
                        ]),
                    Tabs\Tab::make('Datos del Asesor')->icon('heroicon-o-clipboard-document')
                        ->schema([
                            TextInput::make('codigo_asesor')
                                ->nullable()
                                ->prefixIcon('heroicon-o-tag')
                                ->rules(function ($livewire) {
                                    if ($livewire instanceof \Filament\Resources\Pages\EditRecord) {
                                        return [Rule::unique('asesores', 'codigo_asesor')->ignore($livewire->record->id)];
                                    }

                                    return [Rule::unique('asesores', 'codigo_asesor')];
                                })
                                ->live(debounce: 800)
                                ->afterStateUpdated(function ($state, callable $get, callable $set) {
                                    $set('codigo_asesor_error', '');

                                    $value = trim((string) $state);

                                    if ($value !== '') {
                                        $query = Asesor::whereRaw('LOWER(codigo_asesor) = ?', [strtolower($value)]);
                                        $asesorId = $get('id');

                                        if ($asesorId) {
                                            $query->where('id', '!=', $asesorId);
                                        }

                                        if ($query->exists()) {
                                            $set('codigo_asesor_error', 'Este código de asesor ya está registrado en el sistema.');
                                        }
                                    }
                                })
                                ->helperText(fn (callable $get) => new HtmlString(
                                    $get('codigo_asesor_error')
                                        ? '<span class="text-danger-600 dark:text-danger-400">' . e($get('codigo_asesor_error')) . '</span>'
                                        : '<span class="text-gray-500 dark:text-gray-400">Código interno del asesor</span>'
                                ))
                                ->extraAttributes(fn (callable $get) => $get('codigo_asesor_error') ? ['style' => 'border-color: #ef4444;'] : []),
                            DatePicker::make('fecha_ingreso')
                                ->nullable()
                                ->prefixIcon('heroicon-o-clock')
                                ->default(now()->format('Y-m-d')),
                            Select::make('estado_asesor')
                                ->prefixIcon('heroicon-o-check-circle')
                                ->options([
                                    'ACTIVO' => 'Activo',
                                    'INACTIVO' => 'Inactivo'
                                ])
                                ->default('ACTIVO')
                                ->required()
                                ->visible(fn ($livewire) => $livewire instanceof \Filament\Resources\Pages\EditRecord),
                                ]),

                            ]),
                            Forms\Components\Hidden::make('id'),
                            Forms\Components\Hidden::make('persona_id'),
                            Forms\Components\Hidden::make('user_id'),
                            Forms\Components\Hidden::make('dni_error'),
                            Forms\Components\Hidden::make('celular_error'),
                            Forms\Components\Hidden::make('correo_error'),
                            Forms\Components\Hidden::make('name_error'),
                            Forms\Components\Hidden::make('user_email_error'),
                            Forms\Components\Hidden::make('codigo_asesor_error'),
            ]);
    }
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('persona.nombre')->label('Nombre') ->AlignLeft() ->searchable(),
                Tables\Columns\TextColumn::make('persona.apellidos')->label('Apellidos') ->AlignLeft()->searchable(),
                Tables\Columns\TextColumn::make('persona.DNI')->label('DNI') ->AlignLeft()->searchable(),
                Tables\Columns\TextColumn::make('persona.correo')->label('Correo')->AlignLeft()->wrap()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('codigo_asesor')->label('Código Asesor') ->AlignLeft()->searchable(),
                Tables\Columns\TextColumn::make('estado_asesor')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (string $state): string => match (strtoupper($state)) {
                        'ACTIVO' => 'success',
                        'INACTIVO' => 'danger',
                        default => 'warning',
                    }),
                Tables\Columns\TextColumn::make('fecha_ingreso')->label('Fecha de Ingreso')->AlignLeft()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('estado_asesor')
                    ->label('Estado')
                    ->options([
                        'ACTIVO' => 'Activo',
                        'INACTIVO' => 'Inactivo',
                    ])
                    ->default('ACTIVO')
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
                    ->hidden(fn ($record): bool => $record->estado_asesor === 'ACTIVO')
                    ->after(function ($record) {                        // Activar el asesor y su cuenta de usuario
                        $record->update(['estado_asesor' => 'ACTIVO']);

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
            ->defaultSort('created_at', 'desc');
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
