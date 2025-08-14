<?php

namespace App\Livewire;

use App\Models\Cliente;
use Livewire\Component;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;

class DeclaracionJurada extends Component implements HasForms
{
    use InteractsWithForms;

    public ?Cliente $cliente = null;
    public ?array $data = [];
    
    // Datos del formulario de declaración jurada
    public $nombres = '';
    public $apellidos = '';
    public $tipo_documento = 'DNI';
    public $numero_documento = '';
    public $nacionalidad = 'PERUANA';
    public $estado_civil = '';
    public $nombre_conyuge = '';
    public $domicilio = '';
    public $numero_domicilio = '';
    public $distrito = '';
    public $provincia = '';
    public $departamento = '';
    public $ocupacion = '';
    public $telefono = '';
    public $celular = '';
    public $correo = '';
    
    // Campos PEP
    public $es_pep = false;
    public $ha_sido_pep = false;
    public $pep_detalles = '';
    public $familiares_pep = false;
    public $conyuge_pep = false;
    public $detalles_familiares_pep = '';
    
    // Beneficiario
    public $operacion_favor = 'propio'; // propio, tercero_natural, tercero_juridico
    public $tercero_nombres = '';
    public $tercero_apellidos = '';
    public $tercero_documento = '';
    public $tercero_tipo_documento = 'DNI';
    public $tercero_datos_representacion = '';
    public $tercero_es_pep = false;
    public $tercero_razon_social = '';
    public $tercero_ruc = '';
    public $beneficiario_final_identificacion = '';

    public function mount($cliente_id = null)
    {
        if ($cliente_id) {
            $this->cliente = Cliente::with('persona')->find($cliente_id);
            if (!$this->cliente) {
                abort(404, 'Cliente no encontrado');
            }
            $this->cargarDatosCliente();
        }
        
        $this->form->fill($this->data);
    }

    protected function cargarDatosCliente()
    {
        if ($this->cliente && $this->cliente->persona) {
            $persona = $this->cliente->persona;
            
            // Cargar datos automáticamente desde la base de datos
            $this->nombres = $persona->nombre;
            $this->apellidos = $persona->apellidos;
            $this->numero_documento = $persona->DNI;
            $this->estado_civil = $persona->estado_civil;
            $this->domicilio = $persona->direccion;
            $this->distrito = $persona->distrito;
            $this->celular = $persona->celular;
            $this->correo = $persona->correo;
            $this->ocupacion = $this->cliente->actividad ?? '';
        }
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Información Personal del Cliente')
                    ->description('Datos que se autocompletaron desde el sistema')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('nombres')
                                    ->label('Nombres')
                                    ->required()
                                    ->disabled(),
                                
                                Forms\Components\TextInput::make('apellidos')
                                    ->label('Apellidos')
                                    ->required()
                                    ->disabled(),
                                
                                Forms\Components\Select::make('tipo_documento')
                                    ->label('Tipo de Documento')
                                    ->options([
                                        'DNI' => 'DNI',
                                        'Pasaporte' => 'Pasaporte',
                                        'Carné de Extranjería' => 'Carné de Extranjería',
                                        'Otro' => 'Otro',
                                    ])
                                    ->default('DNI')
                                    ->required(),
                                
                                Forms\Components\TextInput::make('numero_documento')
                                    ->label('Número de Documento')
                                    ->required()
                                    ->disabled(),
                                
                                Forms\Components\TextInput::make('nacionalidad')
                                    ->label('Nacionalidad')
                                    ->default('PERUANA')
                                    ->required(),
                                
                                Forms\Components\Select::make('estado_civil')
                                    ->label('Estado Civil')
                                    ->options([
                                        'Soltero' => 'Soltero',
                                        'Casado' => 'Casado',
                                        'Divorciado' => 'Divorciado',
                                        'Viudo' => 'Viudo',
                                        'Conviviente' => 'Conviviente',
                                    ])
                                    ->required()
                                    ->reactive()
                                    ->disabled(),
                            ]),
                            
                        Forms\Components\TextInput::make('nombre_conyuge')
                            ->label('Nombres y apellidos del cónyuge o conviviente')
                            ->visible(fn ($get) => in_array($get('estado_civil'), ['Casado', 'Conviviente']))
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Domicilio')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('domicilio')
                                    ->label('Jr./Av./Calle/Pasaje/Óvalo')
                                    ->required()
                                    ->disabled(),
                                
                                Forms\Components\TextInput::make('numero_domicilio')
                                    ->label('N°')
                                    ->placeholder('29'),
                                
                                Forms\Components\TextInput::make('distrito')
                                    ->label('Distrito')
                                    ->required()
                                    ->disabled(),
                                
                                Forms\Components\TextInput::make('provincia')
                                    ->label('Provincia')
                                    ->required()
                                    ->placeholder('Piura'),
                                
                                Forms\Components\TextInput::make('departamento')
                                    ->label('Departamento')
                                    ->required()
                                    ->placeholder('Piura'),
                            ]),
                    ]),

                Forms\Components\Section::make('Contacto y Ocupación')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('ocupacion')
                                    ->label('Ocupación')
                                    ->required()
                                    ->disabled(),
                                
                                Forms\Components\TextInput::make('telefono')
                                    ->label('N° Teléfono Fijo')
                                    ->tel(),
                                
                                Forms\Components\TextInput::make('celular')
                                    ->label('Celular')
                                    ->required()
                                    ->tel()
                                    ->disabled(),
                                
                                Forms\Components\TextInput::make('correo')
                                    ->label('Correo Electrónico')
                                    ->email()
                                    ->required()
                                    ->disabled(),
                            ]),
                    ]),

                Forms\Components\Section::make('Información PEP (Persona Expuesta Políticamente)')
                    ->description('Marque según corresponda si ha cumplido funciones públicas')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Checkbox::make('es_pep')
                                    ->label('SÍ SOY PEP')
                                    ->reactive(),
                                
                                Forms\Components\Checkbox::make('ha_sido_pep')
                                    ->label('SÍ HE SIDO PEP')
                                    ->reactive(),
                            ]),
                        
                        Forms\Components\Textarea::make('pep_detalles')
                            ->label('Si marcó "Sí soy" o "Sí he sido", complete la información del cargo e institución')
                            ->visible(fn ($get) => $get('es_pep') || $get('ha_sido_pep'))
                            ->rows(3)
                            ->columnSpanFull(),
                            
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Checkbox::make('familiares_pep')
                                    ->label('Parientes hasta el 2do grado de consanguinidad y 2do de afinidad son PEP')
                                    ->reactive(),
                                
                                Forms\Components\Checkbox::make('conyuge_pep')
                                    ->label('Cónyuge o conviviente es PEP')
                                    ->reactive(),
                            ]),
                            
                        Forms\Components\Textarea::make('detalles_familiares_pep')
                            ->label('Especifique los datos de familiares PEP')
                            ->visible(fn ($get) => $get('familiares_pep') || $get('conyuge_pep'))
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Identidad del Beneficiario de la Operación')
                    ->description('Realizo esta operación a favor de:')
                    ->schema([
                        Forms\Components\Radio::make('operacion_favor')
                            ->label('Realizo esta operación a favor de:')
                            ->options([
                                'propio' => 'De mí mismo',
                                'tercero_natural' => 'Tercera persona natural',
                                'tercero_juridico' => 'Tercera persona jurídica o ente jurídico',
                            ])
                            ->reactive()
                            ->required(),
                            
                        // Campos para tercera persona natural
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('tercero_nombres')
                                    ->label('Nombres y apellidos del tercero')
                                    ->required()
                                    ->visible(fn ($get) => $get('operacion_favor') === 'tercero_natural'),
                                
                                Forms\Components\Select::make('tercero_tipo_documento')
                                    ->label('Tipo de documento')
                                    ->options([
                                        'DNI' => 'DNI',
                                        'Pasaporte' => 'Pasaporte',
                                        'Carné de Extranjería' => 'Carné de Extranjería',
                                    ])
                                    ->visible(fn ($get) => $get('operacion_favor') === 'tercero_natural'),
                                
                                Forms\Components\TextInput::make('tercero_documento')
                                    ->label('Número de documento')
                                    ->required()
                                    ->visible(fn ($get) => $get('operacion_favor') === 'tercero_natural'),
                                
                                Forms\Components\TextInput::make('tercero_datos_representacion')
                                    ->label('Datos de la representación (Poder, Mandato)')
                                    ->visible(fn ($get) => $get('operacion_favor') === 'tercero_natural'),
                            ])
                            ->visible(fn ($get) => $get('operacion_favor') === 'tercero_natural'),
                            
                        Forms\Components\Checkbox::make('tercero_es_pep')
                            ->label('¿Es o ha sido PEP?')
                            ->visible(fn ($get) => $get('operacion_favor') === 'tercero_natural'),
                            
                        // Campos para tercera persona jurídica
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('tercero_razon_social')
                                    ->label('Denominación o Razón Social')
                                    ->required()
                                    ->visible(fn ($get) => $get('operacion_favor') === 'tercero_juridico'),
                                
                                Forms\Components\TextInput::make('tercero_ruc')
                                    ->label('Número de RUC')
                                    ->required()
                                    ->visible(fn ($get) => $get('operacion_favor') === 'tercero_juridico'),
                            ])
                            ->visible(fn ($get) => $get('operacion_favor') === 'tercero_juridico'),
                            
                        Forms\Components\Textarea::make('beneficiario_final_identificacion')
                            ->label('Identificación del Beneficiario Final según artículo 4 del Decreto Legislativo N° 1372')
                            ->rows(3)
                            ->columnSpanFull()
                            ->visible(fn ($get) => in_array($get('operacion_favor'), ['tercero_natural', 'tercero_juridico'])),
                    ]),
            ])
            ->statePath('data');
    }

    public function generarPDF()
    {
        $this->form->getState();
        
        // Aquí puedes agregar la lógica para generar el PDF
        // Por ahora mostraremos una notificación
        Notification::make()
            ->title('Declaración Jurada generada')
            ->body('La declaración jurada se ha preparado correctamente. Implementar generación de PDF.')
            ->success()
            ->send();
            
        // Redirect back to cliente index
        return redirect()->route('filament.dashboard.resources.clientes.index');
    }

    public function cancelar()
    {
        return redirect()->route('filament.dashboard.resources.clientes.index');
    }

    public function render()
    {
        return view('livewire.declaracion-jurada');
    }
}
