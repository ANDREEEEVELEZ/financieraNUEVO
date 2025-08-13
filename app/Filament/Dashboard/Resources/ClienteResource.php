<?php

namespace App\Filament\Dashboard\Resources;

use App\Filament\Dashboard\Resources\ClienteResource\Pages;
use App\Models\Cliente;
use App\Rules\UniqueDNI;
use App\Rules\UniqueCelular;
use App\Rules\UniqueCorreo;
use App\Services\DeclaracionJuradaService;
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
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, callable $get, callable $set) {
                                        // Limpiar cualquier error previo
                                        $set('dni_error', '');
                                        
                                        // Solo validar si tiene exactamente 8 dígitos
                                        if (strlen(trim($state)) === 8 && ctype_digit(trim($state))) {
                                            $personaId = $get('persona_id'); // Para edición
                                            $rule = new UniqueDNI($personaId);
                                            
                                            $rule->validate('DNI', $state, function ($message) use ($set) {
                                                $set('dni_error', $message);
                                            });
                                        }
                                    })
                                    ->helperText(fn (callable $get) => $get('dni_error') ?: 'Ingrese 8 dígitos')
                                    ->extraAttributes(fn (callable $get) => $get('dni_error') ? ['style' => 'border-color: #ef4444;'] : [])
                                    ->disabled(fn ($livewire) => $livewire instanceof \Filament\Resources\Pages\EditRecord),
                                TextInput::make('persona.nombre')
                                    ->label('Nombre')
                                    ->required()
                                    ->prefixIcon('heroicon-o-user')
                                    ->rule('regex:/^[\pL\s\ñÑ]+$/u')
                                    ->disabled(fn ($livewire) => $livewire instanceof \Filament\Resources\Pages\EditRecord),
                                TextInput::make('persona.apellidos')
                                    ->label('Apellidos')
                                    ->required()
                                    ->prefixIcon('heroicon-o-user')
                                    ->rule('regex:/^[\pL\s\ñÑ]+$/u')
                                    ->disabled(fn ($livewire) => $livewire instanceof \Filament\Resources\Pages\EditRecord),
                                Select::make('persona.sexo')
                                    ->label('Sexo')
                                    ->required()
                                    ->prefixIcon('heroicon-o-adjustments-horizontal')
                                    ->options([
                                        'Femenino' => 'Femenino',
                                        'Masculino' => 'Masculino',
                                    ])
                                    ->default('Femenino')
                                    ->native(false)
                                    ->disabled(fn ($livewire) => $livewire instanceof \Filament\Resources\Pages\EditRecord),
                                DatePicker::make('persona.fecha_nacimiento')
                                    ->label('Fecha de Nacimiento')
                                    ->required()
                                    ->prefixIcon('heroicon-o-calendar')
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
                                    ->mask('999999999')
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, callable $get, callable $set) {
                                        // Limpiar cualquier error previo
                                        $set('celular_error', '');
                                        
                                        // Solo validar si tiene exactamente 9 dígitos
                                        if (strlen(trim($state)) === 9 && ctype_digit(trim($state))) {
                                            $personaId = $get('persona_id'); // Para edición
                                            $rule = new UniqueCelular($personaId);
                                            
                                            $rule->validate('celular', $state, function ($message) use ($set) {
                                                $set('celular_error', $message);
                                            });
                                        }
                                    })
                                    ->helperText(fn (callable $get) => $get('celular_error') ?: 'Ingrese 9 dígitos')
                                    ->extraAttributes(fn (callable $get) => $get('celular_error') ? ['style' => 'border-color: #ef4444;'] : []),
                                TextInput::make('persona.correo')
                                    ->label('Correo Electrónico')
                                    ->email()
                                    ->required()
                                    ->prefixIcon('heroicon-o-envelope')
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, callable $get, callable $set) {
                                        // Limpiar cualquier error previo
                                        $set('correo_error', '');
                                        
                                        // Solo validar si tiene formato de email válido
                                        if (filter_var(trim($state), FILTER_VALIDATE_EMAIL)) {
                                            $personaId = $get('persona_id'); // Para edición
                                            $rule = new UniqueCorreo($personaId);
                                            
                                            $rule->validate('correo', $state, function ($message) use ($set) {
                                                $set('correo_error', $message);
                                            });
                                        }
                                    })
                                    ->helperText(fn (callable $get) => $get('correo_error') ?: 'Ejemplo: usuario@gmail.com')
                                    ->extraAttributes(fn (callable $get) => $get('correo_error') ? ['style' => 'border-color: #ef4444;'] : []),
                                TextInput::make('persona.direccion')
                                    ->label('Dirección')
                                    ->required()
                                    ->prefixIcon('heroicon-o-map-pin'),
                                Select::make('persona.distrito')
                                    ->label('Distrito')
                                    ->prefixIcon('heroicon-o-map-pin')
                                    ->options([
                                        'Sullana' => 'Sullana',
                                        'Bellavista' => 'Bellavista',
                                        'Ignacio Escudero' => 'Ignacio Escudero',
                                        'Querecotillo' => 'Querecotillo',
                                        'Marcavelica' => 'Marcavelica',
                                        'Salitral' => 'Salitral',
                                        'Lancones' => 'Lancones',
                                        'Miguel Checa' => 'Miguel Checa',
                                    ])
                                    ->native(false)
                                    ->required(),
                                Select::make('persona.estado_civil')
                                    ->label('Estado Civil')
                                    ->prefixIcon('heroicon-o-heart')
                                    ->options([
                                        'Soltero' => 'Soltero',
                                        'Casado' => 'Casado',
                                        'Divorciado' => 'Divorciado',
                                        'Viudo' => 'Viudo',
                                    ])
                                    ->native(false)
                                    ->required(),
                                
                                // Campos ocultos para manejo de validaciones reactivas
                                Forms\Components\Hidden::make('persona_id'),
                                Forms\Components\Hidden::make('dni_error'),
                                Forms\Components\Hidden::make('celular_error'),
                                Forms\Components\Hidden::make('correo_error'),
                            ])->columns(2),

                        Tabs\Tab::make('Información Cliente')
                            ->schema([
                                Select::make('infocorp')
                                    ->label('Infocorp')
                                    ->options([
                                        'Bien Calificado' => 'Bien Calificado',
                                        'Mal Calificado' => 'Mal Calificado',
                                    ])
                                    ->native(false)
                                    ->required()
                                    ->prefixIcon('heroicon-o-document-magnifying-glass'),
                                Select::make('ciclo')
                                    ->label('Ciclo')
                                    ->options([
                                        'I' => 'I',
                                        'II' => 'II',
                                        'III' => 'III',
                                        'IV' => 'IV',
                                    ])
                                    ->native(false)
                                    ->required()
                                    ->prefixIcon('heroicon-o-arrow-path-rounded-square'),
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
                                TextInput::make('actividad')
                                    ->label('Actividad')
                                    ->required()
                                    ->prefixIcon('heroicon-o-briefcase')
                                    ->rule('regex:/^[\pL\pN\s\ñÑ.,()-]+$/u'),
                                Forms\Components\Select::make('condicion_personal')
                                    ->options([
                                        'Capacitado' => 'Capacitado',
                                        'Iletrado' => 'Iletrado',
                                        'PEP' => 'PEP',
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
                                    ->native(false)
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
                // Filtro de condición personal
                Tables\Filters\SelectFilter::make('condicion_personal')
                    ->label('Condición')
                    ->options([
                        'Capacitado' => 'Capacitado',
                        'Iletrado' => 'Iletrado',
                        'PEP' => 'PEP',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when($data['value'], function (Builder $query, string $value): Builder {
                            return $query->where('condicion_personal', $value);
                        });
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->icon('heroicon-o-pencil-square'),
                Tables\Actions\Action::make('declaracion_jurada')
                    ->label('Declaración Jurada')
                    ->icon('heroicon-o-document-text')
                    ->color('info')
                    ->form([
                        Forms\Components\Section::make('Datos del Cliente')
                            ->description('Los siguientes datos se autocompletarán desde la información del cliente')
                            ->schema([
                                Forms\Components\Grid::make(2)->schema([
                                    Forms\Components\TextInput::make('nombres')
                                        ->label('Nombres')
                                        ->disabled()
                                        ->default(fn ($record) => strtoupper($record->persona->nombre)),
                                    Forms\Components\TextInput::make('apellidos')
                                        ->label('Apellidos')
                                        ->disabled()
                                        ->default(fn ($record) => strtoupper($record->persona->apellidos)),
                                    Forms\Components\TextInput::make('dni')
                                        ->label('DNI')
                                        ->disabled()
                                        ->default(fn ($record) => $record->persona->DNI),
                                    Forms\Components\TextInput::make('celular')
                                        ->label('Celular')
                                        ->disabled()
                                        ->default(fn ($record) => $record->persona->celular),
                                ])
                            ]),
                        
                        Forms\Components\Section::make('Información de Documento')
                            ->schema([
                                Forms\Components\Grid::make(3)->schema([
                                    Forms\Components\Select::make('tipo_documento')
                                        ->label('Tipo de Documento')
                                        ->options([
                                            'DNI' => 'DNI',
                                            'Pasaporte' => 'Pasaporte',
                                            'Carnet_Extranjeria' => 'Carné de Extranjería',
                                            'Otro' => 'Otro',
                                        ])
                                        ->default('DNI')
                                        ->reactive(),
                                    Forms\Components\TextInput::make('numero_documento')
                                        ->label('Número de Documento')
                                        ->default(fn ($record) => $record->persona->DNI),
                                    Forms\Components\TextInput::make('otro_documento_detalle')
                                        ->label('Otro (Especificar)')
                                        ->visible(fn (callable $get) => $get('tipo_documento') === 'Otro'),
                                ])
                            ]),
                        
                        Forms\Components\Section::make('Información Complementaria')
                            ->schema([
                                Forms\Components\Grid::make(2)->schema([
                                    Forms\Components\TextInput::make('conyuge_nombres')
                                        ->label('Nombres del Cónyuge/Conviviente')
                                        ->visible(fn ($record) => in_array(strtolower($record->persona->estado_civil), ['casado', 'conviviente'])),
                                    Forms\Components\TextInput::make('telefono_fijo')
                                        ->label('Teléfono Fijo (Opcional)'),
                                ])
                            ]),
                        
                        Forms\Components\Section::make('Información PEP (Persona Expuesta Políticamente)')
                            ->description('Por defecto se marca como NO SOY/NO HE SIDO PEP')
                            ->schema([
                                Forms\Components\Select::make('es_pep')
                                    ->label('¿Es o ha sido una Persona Expuesta Políticamente (PEP)?')
                                    ->options([
                                        'NO_SOY' => 'NO SOY',
                                        'NO_HE_SIDO' => 'NO HE SIDO',
                                        'SOY' => 'SOY',
                                        'HE_SIDO' => 'HE SIDO',
                                    ])
                                    ->default('NO_SOY')
                                    ->reactive(),
                                
                                Forms\Components\Grid::make(2)->schema([
                                    Forms\Components\TextInput::make('cargo_publico')
                                        ->label('Cargo Público')
                                        ->visible(fn (callable $get) => in_array($get('es_pep'), ['SOY', 'HE_SIDO']))
                                        ->required(fn (callable $get) => in_array($get('es_pep'), ['SOY', 'HE_SIDO'])),
                                    Forms\Components\TextInput::make('entidad_publica')
                                        ->label('Entidad Pública')
                                        ->visible(fn (callable $get) => in_array($get('es_pep'), ['SOY', 'HE_SIDO']))
                                        ->required(fn (callable $get) => in_array($get('es_pep'), ['SOY', 'HE_SIDO'])),
                                    Forms\Components\DatePicker::make('fecha_inicio_cargo')
                                        ->label('Fecha Inicio del Cargo')
                                        ->visible(fn (callable $get) => in_array($get('es_pep'), ['SOY', 'HE_SIDO'])),
                                    Forms\Components\DatePicker::make('fecha_fin_cargo')
                                        ->label('Fecha Fin del Cargo')
                                        ->visible(fn (callable $get) => $get('es_pep') === 'HE_SIDO'),
                                ]),
                                
                                Forms\Components\Textarea::make('parentesco_pep')
                                    ->label('¿Tiene parentesco hasta segundo grado con alguna PEP?')
                                    ->visible(fn (callable $get) => in_array($get('es_pep'), ['SOY', 'HE_SIDO']))
                                    ->rows(2),
                            ]),
                        
                        Forms\Components\Section::make('Representación')
                            ->description('Por defecto se marca "Por mi mismo" ya que el préstamo es personal')
                            ->schema([
                                Forms\Components\Select::make('actua_por')
                                    ->label('¿Actúa por cuenta propia o de tercera persona?')
                                    ->options([
                                        'MI_MISMO' => 'Por mi mismo',
                                        'TERCERA_PERSONA' => 'Por tercera persona',
                                    ])
                                    ->default('MI_MISMO')
                                    ->reactive(),
                                
                                Forms\Components\Grid::make(2)->schema([
                                    Forms\Components\TextInput::make('representado_nombres')
                                        ->label('Nombres del Representado')
                                        ->visible(fn (callable $get) => $get('actua_por') === 'TERCERA_PERSONA')
                                        ->required(fn (callable $get) => $get('actua_por') === 'TERCERA_PERSONA'),
                                    Forms\Components\TextInput::make('representado_apellidos')
                                        ->label('Apellidos del Representado')
                                        ->visible(fn (callable $get) => $get('actua_por') === 'TERCERA_PERSONA')
                                        ->required(fn (callable $get) => $get('actua_por') === 'TERCERA_PERSONA'),
                                    Forms\Components\TextInput::make('representado_documento')
                                        ->label('Documento del Representado')
                                        ->visible(fn (callable $get) => $get('actua_por') === 'TERCERA_PERSONA')
                                        ->required(fn (callable $get) => $get('actua_por') === 'TERCERA_PERSONA'),
                                ]),
                            ]),
                    ])
                    ->action(function ($record, $data) {
                        try {
                            return \App\Services\DeclaracionJuradaService::generarPDF($record, $data);
                        } catch (\Exception $e) {
                            \Filament\Notifications\Notification::make()
                                ->danger()
                                ->title('Error al generar PDF')
                                ->body('Ocurrió un error al generar la declaración jurada: ' . $e->getMessage())
                                ->send();
                        }
                    })
                    ->modalHeading('Generar Declaración Jurada')
                    ->modalDescription(fn ($record) => "Generar declaración jurada para {$record->persona->nombre} {$record->persona->apellidos}")
                    ->modalSubmitActionLabel('Generar PDF')
                    ->modalWidth('7xl'),
                Tables\Actions\Action::make('trasladar_cliente')
                    ->label('Trasladar Cliente')
                    ->icon('heroicon-o-arrow-right-circle')
                    ->color('warning')
                    ->visible(fn () => request()->user() && request()->user()->hasAnyRole(['super_admin', 'Jefe de operaciones']))
                    ->form([
                        Forms\Components\Select::make('nuevo_asesor_id')
                            ->label('Nuevo Asesor')
                            ->required()
                            ->options(function () {
                                return \App\Models\Asesor::where('estado_asesor', 'Activo')
                                    ->with('persona')
                                    ->get()
                                    ->mapWithKeys(function ($asesor) {
                                        return [$asesor->id => $asesor->persona->nombre . ' ' . $asesor->persona->apellidos];
                                    });
                            })
                            ->searchable()
                            ->helperText('Seleccione el nuevo asesor para este cliente'),
                    ])
                    ->action(function ($record, $data) {
                        $nuevoAsesorId = $data['nuevo_asesor_id'];
                        $nuevoAsesor = \App\Models\Asesor::with('persona')->find($nuevoAsesorId);
                        $nombreNuevoAsesor = $nuevoAsesor->persona->nombre . ' ' . $nuevoAsesor->persona->apellidos;
                        $nombreCliente = $record->persona->nombre . ' ' . $record->persona->apellidos;
                        
                        // Verificar si el cliente pertenece a un grupo activo
                        if ($record->tieneGrupoActivo()) {
                            $grupo = $record->grupos()->where('estado_grupo', 'Activo')->first();
                            $integrantesGrupo = $grupo->clientes()->count();
                            
                            if ($integrantesGrupo > 1) {
                                // Mostrar modal de confirmación para trasladar todo el grupo
                                \Filament\Notifications\Notification::make()
                                    ->warning()
                                    ->title('Cliente Pertenece a un Grupo')
                                    ->body("El cliente {$nombreCliente} pertenece al grupo '{$grupo->nombre_grupo}' con {$integrantesGrupo} integrantes. Para trasladar este cliente, debe trasladar todo el grupo. ¿Desea continuar trasladando todo el grupo al asesor {$nombreNuevoAsesor}?")
                                    ->actions([
                                        \Filament\Notifications\Actions\Action::make('confirm_group_transfer')
                                            ->label('Sí, trasladar todo el grupo')
                                            ->button()
                                            ->action(function () use ($grupo, $nuevoAsesorId, $nombreNuevoAsesor) {
                                                // Trasladar grupo completo
                                                $grupo->asesor_id = $nuevoAsesorId;
                                                $grupo->save();
                                                
                                                // Trasladar todos los clientes del grupo
                                                $clientesGrupo = $grupo->clientes;
                                                foreach ($clientesGrupo as $clienteGrupo) {
                                                    $clienteGrupo->asesor_id = $nuevoAsesorId;
                                                    $clienteGrupo->save();
                                                }
                                                
                                                \Filament\Notifications\Notification::make()
                                                    ->success()
                                                    ->title('Grupo Trasladado Exitosamente')
                                                    ->body("El grupo '{$grupo->nombre_grupo}' y todos sus {$clientesGrupo->count()} integrantes han sido trasladados al asesor {$nombreNuevoAsesor}.")
                                                    ->send();
                                            }),
                                        \Filament\Notifications\Actions\Action::make('cancel')
                                            ->label('Cancelar')
                                            ->action(function () {
                                                \Filament\Notifications\Notification::make()
                                                    ->info()
                                                    ->title('Traslado Cancelado')
                                                    ->body('El traslado ha sido cancelado.')
                                                    ->send();
                                            })
                                    ])
                                    ->persistent()
                                    ->send();
                                return;
                            } else {
                                // Solo un integrante, trasladar grupo y cliente
                                $grupo->asesor_id = $nuevoAsesorId;
                                $grupo->save();
                            }
                        }
                        
                        // Trasladar cliente individual o único integrante de grupo
                        $record->asesor_id = $nuevoAsesorId;
                        $record->save();
                        
                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title('Cliente Trasladado Exitosamente')
                            ->body("El cliente {$nombreCliente} ha sido trasladado exitosamente al asesor {$nombreNuevoAsesor}.")
                            ->send();
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Confirmar Traslado de Cliente')
                    ->modalDescription(fn ($record) => "¿Está seguro de que desea trasladar al cliente {$record->persona->nombre} {$record->persona->apellidos} a otro asesor?")
                    ->modalSubmitActionLabel('Sí, trasladar'),
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

        return $query->orderBy('created_at', 'desc');
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