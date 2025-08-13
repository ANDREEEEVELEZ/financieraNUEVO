<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Cliente;
use App\Services\PepDocumentService;
use Illuminate\Support\Facades\Log;

class PepEditor extends Component
{
    public Cliente $cliente;
    public $persona;
    
    // Campos editables del formulario
    public $conyuge_conviviente = '';
    public $telefono_fijo = '';
    public $proposito_relacion = '';
    
    // Campos PEP
    public $es_pep = '';
    public $es_colaborador_pep = '';
    public $cargo_pep = '';
    public $institucion_pep = '';
    
    // Parientes PEP
    public $es_pariente_pep = '';
    public $parientes_pep = [];
    
    // Beneficiario
    public $operacion_favor = '';
    public $tercero_nombres = '';
    public $tercero_documento = '';
    public $razon_social = '';
    public $ruc = '';
    
    public $observaciones = '';
    
    // Estado del editor
    public $isLoading = false;
    public $autoSaveEnabled = true;
    
    protected $rules = [
        'conyuge_conviviente' => 'nullable|string|max:255',
        'telefono_fijo' => 'nullable|string|max:20',
        'proposito_relacion' => 'nullable|string|max:500',
        'es_pep' => 'nullable|in:si_soy,si_he_sido,no_soy,no_he_sido',
        'es_colaborador_pep' => 'nullable|in:si_soy,si_he_sido,no_soy,no_he_sido',
        'cargo_pep' => 'nullable|string|max:255',
        'institucion_pep' => 'nullable|string|max:255',
        'es_pariente_pep' => 'nullable|in:si_soy,no_soy',
        'parientes_pep' => 'nullable|array|max:5',
        'parientes_pep.*.nombre_pariente' => 'required_if:es_pariente_pep,si_soy|string|max:255',
        'parientes_pep.*.parentesco' => 'required_if:es_pariente_pep,si_soy|string|max:100',
        'operacion_favor' => 'nullable|in:mi_mismo,tercero_natural,persona_juridica,ente_juridico',
        'tercero_nombres' => 'required_if:operacion_favor,tercero_natural|string|max:255',
        'tercero_documento' => 'required_if:operacion_favor,tercero_natural|string|max:100',
        'razon_social' => 'required_if:operacion_favor,persona_juridica,ente_juridico|string|max:255',
        'ruc' => 'required_if:operacion_favor,persona_juridica,ente_juridico|string|max:20',
        'observaciones' => 'nullable|string|max:500',
    ];

    public function mount(Cliente $cliente)
    {
        $this->cliente = $cliente;
        $this->loadCliente();
        $this->initializeParientes();
    }

    public function loadCliente()
    {
        // Cargar la relación persona si no está cargada
        if (!$this->cliente->relationLoaded('persona')) {
            $this->cliente->load('persona');
        }
        
        $this->persona = $this->cliente->persona;
        
        // Verificar que sea PEP
        if ($this->cliente->condicion_personal !== 'PEP') {
            session()->flash('error', 'Este cliente no tiene condición PEP.');
            return redirect()->route('filament.dashboard.resources.clientes.index');
        }
        
        // Cargar datos guardados en sesión si existen
        $this->loadSavedData();
    }

    public function initializeParientes()
    {
        if (empty($this->parientes_pep)) {
            $this->parientes_pep = [
                ['nombre_pariente' => '', 'parentesco' => '']
            ];
        }
    }

    public function addPariente()
    {
        if (count($this->parientes_pep) < 5) {
            $this->parientes_pep[] = ['nombre_pariente' => '', 'parentesco' => ''];
        }
    }

    public function removePariente($index)
    {
        if (count($this->parientes_pep) > 1) {
            unset($this->parientes_pep[$index]);
            $this->parientes_pep = array_values($this->parientes_pep);
        }
    }

    public function updatedEsPep()
    {
        if (!in_array($this->es_pep, ['si_soy', 'si_he_sido'])) {
            $this->cargo_pep = '';
            $this->institucion_pep = '';
        }
    }

    public function updatedEsParientePep()
    {
        if ($this->es_pariente_pep !== 'si_soy') {
            $this->parientes_pep = [['nombre_pariente' => '', 'parentesco' => '']];
        }
    }

    public function updatedOperacionFavor()
    {
        // Limpiar campos según el tipo de operación
        if ($this->operacion_favor !== 'tercero_natural') {
            $this->tercero_nombres = '';
            $this->tercero_documento = '';
        }
        
        if (!in_array($this->operacion_favor, ['persona_juridica', 'ente_juridico'])) {
            $this->razon_social = '';
            $this->ruc = '';
        }
    }

    public function autoSave()
    {
        if ($this->autoSaveEnabled) {
            // Guardar en sesión para no perder datos
            session([
                'pep_editor_data_' . $this->cliente->id => [
                    'conyuge_conviviente' => $this->conyuge_conviviente,
                    'telefono_fijo' => $this->telefono_fijo,
                    'proposito_relacion' => $this->proposito_relacion,
                    'es_pep' => $this->es_pep,
                    'es_colaborador_pep' => $this->es_colaborador_pep,
                    'cargo_pep' => $this->cargo_pep,
                    'institucion_pep' => $this->institucion_pep,
                    'es_pariente_pep' => $this->es_pariente_pep,
                    'parientes_pep' => $this->parientes_pep,
                    'operacion_favor' => $this->operacion_favor,
                    'tercero_nombres' => $this->tercero_nombres,
                    'tercero_documento' => $this->tercero_documento,
                    'razon_social' => $this->razon_social,
                    'ruc' => $this->ruc,
                    'observaciones' => $this->observaciones,
                ]
            ]);
        }
    }

    public function loadSavedData()
    {
        $savedData = session('pep_editor_data_' . $this->cliente->id);
        
        if ($savedData) {
            foreach ($savedData as $key => $value) {
                if (property_exists($this, $key)) {
                    $this->{$key} = $value;
                }
            }
        }
    }

    public function generatePdf()
    {
        try {
            $this->isLoading = true;
            
            $this->validate();

            $pepData = [
                'conyuge_conviviente' => $this->conyuge_conviviente,
                'telefono_fijo' => $this->telefono_fijo,
                'proposito_relacion' => $this->proposito_relacion,
                'es_pep' => $this->es_pep,
                'es_colaborador_pep' => $this->es_colaborador_pep,
                'cargo_pep' => $this->cargo_pep,
                'institucion_pep' => $this->institucion_pep,
                'es_pariente_pep' => $this->es_pariente_pep,
                'parientes_pep' => $this->parientes_pep,
                'operacion_favor' => $this->operacion_favor,
                'tercero_nombres' => $this->tercero_nombres,
                'tercero_documento' => $this->tercero_documento,
                'razon_social' => $this->razon_social,
                'ruc' => $this->ruc,
                'observaciones' => $this->observaciones,
            ];

            $pdfContent = app(PepDocumentService::class)->generatePdfWithData($this->cliente, $pepData);
            $filename = "DJ_PEP_{$this->persona->DNI}_{$this->persona->nombre}_{$this->persona->apellidos}.pdf";
            
            // Limpiar datos de sesión después de generar PDF exitosamente
            session()->forget('pep_editor_data_' . $this->cliente->id);
            
            session()->flash('success', 'PDF generado exitosamente.');
            
            $this->isLoading = false;
            
            return response()->streamDownload(function () use ($pdfContent) {
                echo $pdfContent;
            }, $filename);
            
        } catch (\Exception $e) {
            $this->isLoading = false;
            Log::error('Error generando PDF PEP: ' . $e->getMessage());
            session()->flash('error', 'Error al generar PDF: ' . $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.pep-editor');
    }
}
