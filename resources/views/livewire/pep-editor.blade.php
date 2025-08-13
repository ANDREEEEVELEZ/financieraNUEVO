<div class="min-h-screen bg-gray-100">
    <!-- Header del Editor -->
    <div class="bg-white shadow-sm border-b border-gray-200 p-4">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <div class="flex items-center space-x-4">
                <button 
                    onclick="window.history.back()" 
                    class="flex items-center px-3 py-2 text-gray-600 hover:text-gray-900 transition-colors"
                >
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Volver
                </button>
                <div class="border-l border-gray-300 pl-4">
                    <h1 class="text-lg font-semibold text-gray-900">Editor de Declaración Jurada PEP</h1>
                    <p class="text-sm text-gray-600">
                        {{ $persona->nombre }} {{ $persona->apellidos }} - DNI: {{ $persona->DNI }}
                    </p>
                </div>
            </div>
            
            <div class="flex items-center space-x-3">
                <!-- Indicador de guardado automático -->
                <div class="flex items-center text-sm text-gray-500" wire:loading.remove>
                    <svg class="w-4 h-4 mr-1 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"/>
                    </svg>
                    Autoguardado activo
                </div>
                
                <!-- Indicador de carga -->
                <div wire:loading class="flex items-center text-sm text-blue-500">
                    <svg class="animate-spin w-4 h-4 mr-1" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
                    </svg>
                    Guardando...
                </div>
                
                <!-- Botón generar PDF -->
                <button 
                    wire:click="generatePdf"
                    wire:loading.attr="disabled"
                    class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center"
                >
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <span wire:loading.remove wire:target="generatePdf">Generar PDF</span>
                    <span wire:loading wire:target="generatePdf">Generando...</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Contenedor principal del documento -->
    <div class="max-w-5xl mx-auto p-6">
        <div class="bg-white shadow-lg rounded-lg overflow-hidden">
            <!-- Documento editable -->
            <div class="p-8" style="font-family: Arial, sans-serif; font-size: 11px; line-height: 1.3;">
                
                <!-- Tabla principal del documento -->
                <table class="w-full border-2 border-black" style="border-collapse: collapse;">
                    
                    <!-- Header -->
                    <tr>
                        <td colspan="2" class="border border-black p-3 text-center bg-gray-50" style="border: 1px solid black;">
                            <div class="font-bold text-sm mb-2">FORMATO DE DECLARACIÓN JURADA DE CONOCIMIENTO DEL CLIENTE BAJO EL RÉGIMEN GENERAL – PERSONA NATURAL</div>
                            <div class="text-xs">(Información mínima para ser llenada por el cliente del sujeto obligado)</div>
                        </td>
                    </tr>
                    
                    <!-- 1. Nombres y Apellidos -->
                    <tr>
                        <td class="w-8 border border-black p-2 text-center font-bold bg-gray-50" style="border: 1px solid black;">1</td>
                        <td class="border border-black p-2" style="border: 1px solid black;">
                            <span class="font-bold">Nombres:</span> 
                            <span class="underline inline-block min-w-48 mx-2 border-b border-black">{{ strtoupper($persona->nombre) }}</span>
                            <span class="font-bold ml-12">Apellidos:</span> 
                            <span class="underline inline-block min-w-48 mx-2 border-b border-black">{{ strtoupper($persona->apellidos) }}</span>
                        </td>
                    </tr>
                    
                    <!-- 2. Documento de identidad -->
                    <tr>
                        <td class="w-8 border border-black p-2 text-center font-bold bg-gray-50" style="border: 1px solid black;">2</td>
                        <td class="border border-black p-2" style="border: 1px solid black;">
                            <span class="font-bold">Tipo y número de documento de identidad (marque con una "X" según corresponda):</span><br>
                            <span class="font-bold">DNI</span> ( <span class="text-lg">X</span> ) 
                            <span class="font-bold">Pasaporte</span> ( ) 
                            <span class="font-bold">Carné de Extranjería</span> ( ) 
                            <span class="font-bold">Otro (Indique):</span> ( )<br>
                            <span class="font-bold ml-12">N°:</span> 
                            <span class="underline inline-block min-w-32 mx-2 border-b border-black">{{ $persona->DNI }}</span>
                        </td>
                    </tr>
                    
                    <!-- 3. Nacionalidad -->
                    <tr>
                        <td class="w-8 border border-black p-2 text-center font-bold bg-gray-50" style="border: 1px solid black;">3</td>
                        <td class="border border-black p-2" style="border: 1px solid black;">
                            <span class="font-bold">Nacionalidad (en el caso de extranjero):</span> 
                            <span class="underline inline-block min-w-32 mx-2 border-b border-black">PERUANA</span>
                        </td>
                    </tr>
                    
                    <!-- 4. Estado civil -->
                    <tr>
                        <td class="w-8 border border-black p-2 text-center font-bold bg-gray-50" style="border: 1px solid black;">4</td>
                        <td class="border border-black p-2" style="border: 1px solid black;">
                            <span class="font-bold">Estado civil (marque con una "X" según corresponda): soltero(a) ( ) casado(a) ( ) viudo(a) ( ) divorciado(a) ( ) Conviviente ( )</span>
                        </td>
                    </tr>
                    
                    <!-- 5. Cónyuge -->
                    <tr>
                        <td class="w-8 border border-black p-2 text-center font-bold bg-gray-50" style="border: 1px solid black;">5</td>
                        <td class="border border-black p-2" style="border: 1px solid black;">
                            <span class="font-bold">Nombres y apellidos del cónyuge o conviviente:</span> 
                            <input 
                                type="text" 
                                wire:model.live="conyuge_conviviente"
                                wire:change="autoSave"
                                class="underline border-b border-black bg-transparent focus:outline-none focus:bg-yellow-50 min-w-64 mx-2 px-1"
                                placeholder="Escriba aquí..."
                            >
                        </td>
                    </tr>
                    
                    <!-- 6. Domicilio -->
                    <tr>
                        <td class="w-8 border border-black p-2 text-center font-bold bg-gray-50" style="border: 1px solid black;">6</td>
                        <td class="border border-black p-2" style="border: 1px solid black;">
                            <span class="font-bold">Domicilio (indicar tipo y nombre de la vía): Jr. / Av. / Calle / Pasaje / Ovalo</span><br>
                            <span class="underline inline-block min-w-96 border-b border-black">{{ strtoupper($persona->direccion) }}</span><br>
                            <span class="font-bold">Urb. Complejo Zona - Sector:</span> 
                            <span class="underline inline-block min-w-24 mx-2 border-b border-black"></span>
                            <span class="font-bold">Distrito:</span> 
                            <span class="underline inline-block min-w-32 mx-2 border-b border-black">{{ strtoupper($persona->distrito) }}</span><br>
                            <span class="font-bold">Int:</span> 
                            <span class="underline inline-block min-w-12 mx-2 border-b border-black"></span>
                            <span class="font-bold">Dpto./Int. N°:</span> 
                            <span class="underline inline-block min-w-12 mx-2 border-b border-black"></span><br>
                            <span class="font-bold">Provincia:</span> 
                            <span class="underline inline-block min-w-24 mx-2 border-b border-black">SULLANA</span>
                            <span class="font-bold">Departamento:</span> 
                            <span class="underline inline-block min-w-24 mx-2 border-b border-black">PIURA</span>
                        </td>
                    </tr>
                    
                    <!-- 7. Ocupación -->
                    <tr>
                        <td class="w-8 border border-black p-2 text-center font-bold bg-gray-50" style="border: 1px solid black;">7</td>
                        <td class="border border-black p-2" style="border: 1px solid black;">
                            <span class="font-bold">Ocupación:</span> 
                            <span class="underline inline-block min-w-64 mx-2 border-b border-black">{{ strtoupper($cliente->actividad) }}</span>
                        </td>
                    </tr>
                    
                    <!-- 8. Teléfonos -->
                    <tr>
                        <td class="w-8 border border-black p-2 text-center font-bold bg-gray-50" style="border: 1px solid black;">8</td>
                        <td class="border border-black p-2" style="border: 1px solid black;">
                            <span class="font-bold">N° Teléfono Fijo (indicar código de ciudad):</span> 
                            <input 
                                type="text" 
                                wire:model.live="telefono_fijo"
                                wire:change="autoSave"
                                class="underline border-b border-black bg-transparent focus:outline-none focus:bg-yellow-50 min-w-32 mx-2 px-1"
                                placeholder="Escriba aquí..."
                            >
                            <span class="font-bold ml-6">Celular:</span> 
                            <span class="underline inline-block min-w-32 mx-2 border-b border-black">{{ $persona->celular }}</span>
                            <span class="font-bold ml-6">Correo electrónico:</span> 
                            <span class="underline inline-block min-w-48 mx-2 border-b border-black">{{ strtolower($persona->correo) }}</span>
                        </td>
                    </tr>
                    
                    <!-- 9. Propósito -->
                    <tr>
                        <td class="w-8 border border-black p-2 text-center font-bold bg-gray-50" style="border: 1px solid black;">9</td>
                        <td class="border border-black p-2" style="border: 1px solid black;">
                            <span class="font-bold">Propósito de la relación comercial o de negocio (siempre que esta se desprenda directamente del objeto del contrato):</span><br>
                            <textarea 
                                wire:model.live="proposito_relacion"
                                wire:change="autoSave"
                                class="w-full min-h-16 border-b border-black bg-transparent focus:outline-none focus:bg-yellow-50 mt-2 px-1 resize-none"
                                placeholder="Escriba el propósito de la relación comercial..."
                            ></textarea>
                        </td>
                    </tr>
                    
                    <!-- 10. Sección PEP -->
                    <tr>
                        <td class="w-8 border border-black p-2 text-center font-bold bg-gray-50" style="border: 1px solid black;">10</td>
                        <td class="border border-black p-2" style="border: 1px solid black;">
                            <div class="space-y-3">
                                <div>
                                    <span class="font-bold">10.1. Indicar si es o ha sido PEP: ¿Ha cumplido, en los últimos 5 años, funciones públicas en un organismo público o funciones prominentes en una organización internacional? (marque con una "X" según corresponda):</span><br>
                                    <div class="mt-2 flex flex-wrap gap-4">
                                        <label class="cursor-pointer">
                                            <input type="radio" wire:model.live="es_pep" value="si_soy" wire:change="autoSave" class="sr-only">
                                            <span class="font-bold">SI SOY</span> 
                                            <span class="inline-block w-4 h-4 border border-black text-center leading-none ml-1 {{ $es_pep === 'si_soy' ? 'bg-black text-white' : '' }}">
                                                {{ $es_pep === 'si_soy' ? 'X' : '' }}
                                            </span>
                                        </label>
                                        <label class="cursor-pointer">
                                            <input type="radio" wire:model.live="es_pep" value="si_he_sido" wire:change="autoSave" class="sr-only">
                                            <span class="font-bold">SI HE SIDO</span> 
                                            <span class="inline-block w-4 h-4 border border-black text-center leading-none ml-1 {{ $es_pep === 'si_he_sido' ? 'bg-black text-white' : '' }}">
                                                {{ $es_pep === 'si_he_sido' ? 'X' : '' }}
                                            </span>
                                        </label>
                                        <label class="cursor-pointer">
                                            <input type="radio" wire:model.live="es_pep" value="no_soy" wire:change="autoSave" class="sr-only">
                                            <span class="font-bold">NO SOY</span> 
                                            <span class="inline-block w-4 h-4 border border-black text-center leading-none ml-1 {{ $es_pep === 'no_soy' ? 'bg-black text-white' : '' }}">
                                                {{ $es_pep === 'no_soy' ? 'X' : '' }}
                                            </span>
                                        </label>
                                        <label class="cursor-pointer">
                                            <input type="radio" wire:model.live="es_pep" value="no_he_sido" wire:change="autoSave" class="sr-only">
                                            <span class="font-bold">NO HE SIDO</span> 
                                            <span class="inline-block w-4 h-4 border border-black text-center leading-none ml-1 {{ $es_pep === 'no_he_sido' ? 'bg-black text-white' : '' }}">
                                                {{ $es_pep === 'no_he_sido' ? 'X' : '' }}
                                            </span>
                                        </label>
                                    </div>
                                </div>
                                
                                <div>
                                    <span class="font-bold">¿Ha sido colaborador directo de la máxima autoridad en dichas instituciones?</span><br>
                                    <div class="mt-2 flex flex-wrap gap-4">
                                        <label class="cursor-pointer">
                                            <input type="radio" wire:model.live="es_colaborador_pep" value="si_soy" wire:change="autoSave" class="sr-only">
                                            <span class="font-bold">SI SOY</span> 
                                            <span class="inline-block w-4 h-4 border border-black text-center leading-none ml-1 {{ $es_colaborador_pep === 'si_soy' ? 'bg-black text-white' : '' }}">
                                                {{ $es_colaborador_pep === 'si_soy' ? 'X' : '' }}
                                            </span>
                                        </label>
                                        <label class="cursor-pointer">
                                            <input type="radio" wire:model.live="es_colaborador_pep" value="si_he_sido" wire:change="autoSave" class="sr-only">
                                            <span class="font-bold">SI HE SIDO</span> 
                                            <span class="inline-block w-4 h-4 border border-black text-center leading-none ml-1 {{ $es_colaborador_pep === 'si_he_sido' ? 'bg-black text-white' : '' }}">
                                                {{ $es_colaborador_pep === 'si_he_sido' ? 'X' : '' }}
                                            </span>
                                        </label>
                                        <label class="cursor-pointer">
                                            <input type="radio" wire:model.live="es_colaborador_pep" value="no_soy" wire:change="autoSave" class="sr-only">
                                            <span class="font-bold">NO SOY</span> 
                                            <span class="inline-block w-4 h-4 border border-black text-center leading-none ml-1 {{ $es_colaborador_pep === 'no_soy' ? 'bg-black text-white' : '' }}">
                                                {{ $es_colaborador_pep === 'no_soy' ? 'X' : '' }}
                                            </span>
                                        </label>
                                        <label class="cursor-pointer">
                                            <input type="radio" wire:model.live="es_colaborador_pep" value="no_he_sido" wire:change="autoSave" class="sr-only">
                                            <span class="font-bold">NO HE SIDO</span> 
                                            <span class="inline-block w-4 h-4 border border-black text-center leading-none ml-1 {{ $es_colaborador_pep === 'no_he_sido' ? 'bg-black text-white' : '' }}">
                                                {{ $es_colaborador_pep === 'no_he_sido' ? 'X' : '' }}
                                            </span>
                                        </label>
                                    </div>
                                </div>
                                
                                @if(in_array($es_pep, ['si_soy', 'si_he_sido']))
                                <div class="space-y-2 mt-4 p-3 bg-yellow-50 border border-yellow-200 rounded">
                                    <div>
                                        <span class="font-bold">Cargo:</span> 
                                        <input 
                                            type="text" 
                                            wire:model.live="cargo_pep"
                                            wire:change="autoSave"
                                            class="underline border-b border-black bg-transparent focus:outline-none focus:bg-yellow-100 min-w-64 mx-2 px-1"
                                            placeholder="Escriba el cargo..."
                                        >
                                    </div>
                                    <div>
                                        <span class="font-bold">Nombre de la institución (organismo público u organización internacional):</span><br>
                                        <input 
                                            type="text" 
                                            wire:model.live="institucion_pep"
                                            wire:change="autoSave"
                                            class="underline border-b border-black bg-transparent focus:outline-none focus:bg-yellow-100 w-full mt-2 px-1"
                                            placeholder="Escriba el nombre de la institución..."
                                        >
                                    </div>
                                </div>
                                @endif
                            </div>
                        </td>
                    </tr>
                    
                    <!-- Continúa en la siguiente parte... -->
                    
                </table>
                
                <!-- Continuación de la tabla con parientes PEP, beneficiarios, etc. -->
                @include('livewire.partials.pep-editor-continuation')
                
                <!-- Sección de firma -->
                <div class="mt-8 text-center">
                    <div class="mb-4">
                        <span class="font-bold">FECHA (día/mes/año):</span> 
                        <span class="underline inline-block min-w-32 mx-2 border-b border-black">{{ now()->format('d/m/Y') }}</span>
                        <span class="font-bold ml-12">FIRMA</span>
                    </div>
                    
                    <div class="mt-8">
                        <div class="border-t border-black w-48 mx-auto"></div>
                        <div class="mt-2 font-bold">{{ strtoupper($persona->nombre) }} {{ strtoupper($persona->apellidos) }}</div>
                        <div>DNI: {{ $persona->DNI }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts para mejorar la experiencia -->
    <script>
        // Auto-save cada 30 segundos
        setInterval(function() {
            if (typeof Livewire !== 'undefined') {
                Livewire.emit('autoSave');
            }
        }, 30000);
        
        // Advertencia antes de salir si hay cambios sin guardar
        window.addEventListener('beforeunload', function(e) {
            e.preventDefault();
            e.returnValue = '¿Estás seguro de que quieres salir? Los cambios se guardan automáticamente.';
        });
        
        // Atajos de teclado
        document.addEventListener('keydown', function(e) {
            // Ctrl+S para generar PDF
            if (e.ctrlKey && e.key === 's') {
                e.preventDefault();
                Livewire.emit('generatePdf');
            }
        });
    </script>
</div>
