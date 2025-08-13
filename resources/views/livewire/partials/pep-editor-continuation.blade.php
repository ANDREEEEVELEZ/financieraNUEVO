<!-- Continuación de la tabla principal -->
<table class="w-full border-2 border-black mt-4" style="border-collapse: collapse;">
    
    <!-- 10.2 Parientes PEP -->
    <tr>
        <td class="w-8 border border-black p-2 text-center font-bold bg-gray-50" style="border: 1px solid black;">10.2</td>
        <td class="border border-black p-2" style="border: 1px solid black;">
            <div class="space-y-3">
                <div>
                    <span class="font-bold">De ser PEP, indicar los nombres y apellidos de sus:</span><br>
                    <span class="font-bold">(1) Parientes hasta el 2do grado de consanguinidad</span> 
                    <span class="text-xs">(Padre, Madre, Abuelos, Abuelas, Hermanos, Hermanas)</span> 
                    <span class="font-bold">y 2do de afinidad</span> 
                    <span class="text-xs">(suegros, yerno, nuera, cuñados, nueras o cuñadas de cónyuge)</span><br>
                    <span class="font-bold">(2) Cónyuge o conviviente</span>
                </div>
                
                <div>
                    <span class="font-bold">¿Es pariente de PEP hasta el 2do grado?</span><br>
                    <div class="mt-2 flex gap-6">
                        <label class="cursor-pointer">
                            <input type="radio" wire:model.live="es_pariente_pep" value="si_soy" wire:change="autoSave" class="sr-only">
                            <span class="font-bold">SI SOY</span> 
                            <span class="inline-block w-4 h-4 border border-black text-center leading-none ml-1 {{ $es_pariente_pep === 'si_soy' ? 'bg-black text-white' : '' }}">
                                {{ $es_pariente_pep === 'si_soy' ? 'X' : '' }}
                            </span>
                        </label>
                        <label class="cursor-pointer">
                            <input type="radio" wire:model.live="es_pariente_pep" value="no_soy" wire:change="autoSave" class="sr-only">
                            <span class="font-bold">NO SOY</span> 
                            <span class="inline-block w-4 h-4 border border-black text-center leading-none ml-1 {{ $es_pariente_pep === 'no_soy' ? 'bg-black text-white' : '' }}">
                                {{ $es_pariente_pep === 'no_soy' ? 'X' : '' }}
                            </span>
                        </label>
                    </div>
                </div>
                
                @if($es_pariente_pep === 'si_soy')
                <div class="mt-4 p-3 bg-yellow-50 border border-yellow-200 rounded">
                    <div class="flex justify-between items-center mb-3">
                        <span class="font-bold">Datos de Parientes PEP:</span>
                        @if(count($parientes_pep) < 5)
                        <button 
                            wire:click="addPariente" 
                            type="button"
                            class="px-3 py-1 text-xs bg-blue-500 text-white rounded hover:bg-blue-600 transition-colors"
                        >
                            + Agregar Pariente
                        </button>
                        @endif
                    </div>
                    
                    <table class="w-full border border-black" style="border-collapse: collapse;">
                        <thead>
                            <tr>
                                <th class="border border-black p-2 text-center font-bold bg-gray-100">Nombres y Apellidos del PEP</th>
                                <th class="border border-black p-2 text-center font-bold bg-gray-100">Indicar Parentesco</th>
                                <th class="border border-black p-2 text-center font-bold bg-gray-100 w-16">Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($parientes_pep as $index => $pariente)
                            <tr>
                                <td class="border border-black p-2">
                                    <input 
                                        type="text" 
                                        wire:model.live="parientes_pep.{{ $index }}.nombre_pariente"
                                        wire:change="autoSave"
                                        class="w-full bg-transparent focus:outline-none focus:bg-yellow-100 px-1"
                                        placeholder="Escriba nombres y apellidos..."
                                    >
                                </td>
                                <td class="border border-black p-2">
                                    <input 
                                        type="text" 
                                        wire:model.live="parientes_pep.{{ $index }}.parentesco"
                                        wire:change="autoSave"
                                        class="w-full bg-transparent focus:outline-none focus:bg-yellow-100 px-1"
                                        placeholder="Ej: Padre, Madre, Hermano..."
                                    >
                                </td>
                                <td class="border border-black p-2 text-center">
                                    @if(count($parientes_pep) > 1)
                                    <button 
                                        wire:click="removePariente({{ $index }})"
                                        type="button"
                                        class="text-red-500 hover:text-red-700 text-sm"
                                        title="Eliminar pariente"
                                    >
                                        ✕
                                    </button>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @endif
            </div>
        </td>
    </tr>
    
    <!-- 11. Beneficiario de la operación -->
    <tr>
        <td class="w-8 border border-black p-2 text-center font-bold bg-gray-50" style="border: 1px solid black;">11</td>
        <td class="border border-black p-2" style="border: 1px solid black;">
            <div class="space-y-4">
                <div class="text-center font-bold bg-gray-100 p-2 -m-2 mb-4">
                    IDENTIFICACIÓN DEL BENEFICIARIO DE LA OPERACIÓN
                </div>
                
                <div>
                    <span class="font-bold">Realiza esta operación a favor de (marque con una "X" según corresponda):</span><br>
                    <div class="mt-2 grid grid-cols-2 gap-2">
                        <label class="cursor-pointer">
                            <input type="radio" wire:model.live="operacion_favor" value="mi_mismo" wire:change="autoSave" class="sr-only">
                            <span class="font-bold">1. De mi mismo</span> 
                            <span class="inline-block w-4 h-4 border border-black text-center leading-none ml-1 {{ $operacion_favor === 'mi_mismo' ? 'bg-black text-white' : '' }}">
                                {{ $operacion_favor === 'mi_mismo' ? 'X' : '' }}
                            </span>
                        </label>
                        <label class="cursor-pointer">
                            <input type="radio" wire:model.live="operacion_favor" value="tercero_natural" wire:change="autoSave" class="sr-only">
                            <span class="font-bold">2. De un tercero persona natural</span> 
                            <span class="inline-block w-4 h-4 border border-black text-center leading-none ml-1 {{ $operacion_favor === 'tercero_natural' ? 'bg-black text-white' : '' }}">
                                {{ $operacion_favor === 'tercero_natural' ? 'X' : '' }}
                            </span>
                        </label>
                        <label class="cursor-pointer">
                            <input type="radio" wire:model.live="operacion_favor" value="persona_juridica" wire:change="autoSave" class="sr-only">
                            <span class="font-bold">3. Persona jurídica</span> 
                            <span class="inline-block w-4 h-4 border border-black text-center leading-none ml-1 {{ $operacion_favor === 'persona_juridica' ? 'bg-black text-white' : '' }}">
                                {{ $operacion_favor === 'persona_juridica' ? 'X' : '' }}
                            </span>
                        </label>
                        <label class="cursor-pointer">
                            <input type="radio" wire:model.live="operacion_favor" value="ente_juridico" wire:change="autoSave" class="sr-only">
                            <span class="font-bold">4. Ente jurídico</span> 
                            <span class="inline-block w-4 h-4 border border-black text-center leading-none ml-1 {{ $operacion_favor === 'ente_juridico' ? 'bg-black text-white' : '' }}">
                                {{ $operacion_favor === 'ente_juridico' ? 'X' : '' }}
                            </span>
                        </label>
                    </div>
                </div>
                
                <div class="text-xs text-gray-600">
                    <span class="font-bold">Si marcó la opción 2, complete la información del numeral 11.2. Si marcó la opción 3, complete la información del numeral 11.3. Si marcó la opción 4, complete la información del numeral 11.4.</span>
                </div>
            </div>
        </td>
    </tr>
    
    <!-- 11.1 A favor de sí mismo -->
    @if($operacion_favor === 'mi_mismo')
    <tr>
        <td class="w-8 border border-black p-2 text-center font-bold bg-gray-50" style="border: 1px solid black;">11.1</td>
        <td class="border border-black p-2 bg-green-50" style="border: 1px solid black;">
            <div class="space-y-2">
                <span class="font-bold">Si realiza la operación a favor de sí mismo, complete la información siguiente:</span><br>
                <div class="text-sm text-green-700 font-medium">
                    ✓ Esta sección se completa automáticamente con los datos del cliente actual.
                </div>
            </div>
        </td>
    </tr>
    @endif
    
    <!-- 11.2 Tercero persona natural -->
    @if($operacion_favor === 'tercero_natural')
    <tr>
        <td class="w-8 border border-black p-2 text-center font-bold bg-gray-50" style="border: 1px solid black;">11.2</td>
        <td class="border border-black p-2 bg-blue-50" style="border: 1px solid black;">
            <div class="space-y-3">
                <span class="font-bold">Si realiza la operación a favor de un tercero persona natural, complete la información siguiente:</span>
                
                <div>
                    <span class="font-bold">i) Nombres y apellido del tercero persona natural:</span><br>
                    <input 
                        type="text" 
                        wire:model.live="tercero_nombres"
                        wire:change="autoSave"
                        class="w-full border-b border-black bg-transparent focus:outline-none focus:bg-blue-100 mt-2 px-1"
                        placeholder="Escriba nombres y apellidos completos..."
                    >
                </div>
                
                <div>
                    <span class="font-bold">ii) Tipo y número de documento de identidad:</span><br>
                    <input 
                        type="text" 
                        wire:model.live="tercero_documento"
                        wire:change="autoSave"
                        class="w-full border-b border-black bg-transparent focus:outline-none focus:bg-blue-100 mt-2 px-1"
                        placeholder="Ej: DNI 12345678, Pasaporte ABC123..."
                    >
                </div>
                
                <div>
                    <span class="font-bold">iii) Datos de la representación (Marque con una "X" según corresponda):</span><br>
                    <div class="mt-2 flex gap-4">
                        <span class="font-bold">Poder por Escritura Pública</span> ( )
                        <span class="font-bold ml-4">Mandato</span> ( )
                    </div>
                </div>
            </div>
        </td>
    </tr>
    @endif
    
    <!-- 11.3 Persona jurídica o ente jurídico -->
    @if(in_array($operacion_favor, ['persona_juridica', 'ente_juridico']))
    <tr>
        <td class="w-8 border border-black p-2 text-center font-bold bg-gray-50" style="border: 1px solid black;">11.3</td>
        <td class="border border-black p-2 bg-purple-50" style="border: 1px solid black;">
            <div class="space-y-3">
                <span class="font-bold">Si realiza la operación a favor de tercero persona jurídica o ente jurídico, complete la información siguiente:</span>
                
                <div>
                    <span class="font-bold">i) Denominación o Razón Social:</span><br>
                    <input 
                        type="text" 
                        wire:model.live="razon_social"
                        wire:change="autoSave"
                        class="w-full border-b border-black bg-transparent focus:outline-none focus:bg-purple-100 mt-2 px-1"
                        placeholder="Escriba la razón social completa..."
                    >
                </div>
                
                <div>
                    <span class="font-bold">ii) Número de RUC, de ser el caso:</span><br>
                    <input 
                        type="text" 
                        wire:model.live="ruc"
                        wire:change="autoSave"
                        class="w-full border-b border-black bg-transparent focus:outline-none focus:bg-purple-100 mt-2 px-1"
                        placeholder="Escriba el número de RUC..."
                        maxlength="11"
                    >
                </div>
                
                <div>
                    <span class="font-bold">iii) Datos de la representación (Marque con una "X" según corresponda):</span><br>
                    <div class="mt-2 flex flex-wrap gap-4">
                        <span class="font-bold">Poder por acta</span> ( )
                        <span class="font-bold">Poder por Escritura Pública</span> ( )
                        <span class="font-bold">Mandato</span> ( )
                    </div>
                </div>
                
                <div>
                    <span class="font-bold">iv) Indicar si es o ha sido PEP ¿Ha cumplido, en los últimos 5 años, funciones públicas en un organismo público o funciones prominentes en una organización internacional? (marque con una "X" según corresponda):</span><br>
                    <div class="mt-2 flex flex-wrap gap-4">
                        <span class="font-bold">SI SOY</span> ( )
                        <span class="font-bold">SI HA SIDO</span> ( )
                        <span class="font-bold">NO ES</span> ( )
                        <span class="font-bold">NO HA SIDO</span> ( )
                    </div>
                    <div class="mt-2">
                        <span class="font-bold">Si marcó "Si es" o "Si ha sido" complete la información siguiente:</span><br>
                        <span class="font-bold">- Cargo:</span> 
                        <span class="underline inline-block min-w-64 mx-2 border-b border-black"></span>
                    </div>
                </div>
                
                <div>
                    <span class="font-bold">v) Origen de los fondos/activos involucrados en la operación, cuando esta se realice en efectivo o iguale o supere el umbral para efectos del RO.</span>
                </div>
            </div>
        </td>
    </tr>
    @endif
    
    <!-- 12. Beneficiario Final -->
    <tr>
        <td class="w-8 border border-black p-2 text-center font-bold bg-gray-50" style="border: 1px solid black;">12</td>
        <td class="border border-black p-2" style="border: 1px solid black;">
            <div class="space-y-2">
                <span class="font-bold">Identificación del Beneficiario Final del Beneficiario de la operación, conforme al artículo 4 del Decreto Supremo N° 1372 y sus modificatorias; según corresponda (Nombres y Apellidos):</span><br>
                <div class="border-b border-black min-h-8 w-full mt-2"></div>
                <br>
                <span class="font-bold">Afirmo y ratifico todo lo manifestado en la presente declaración jurada</span>
            </div>
        </td>
    </tr>
    
    <!-- 13. Observaciones adicionales -->
    <tr>
        <td class="w-8 border border-black p-2 text-center font-bold bg-gray-50" style="border: 1px solid black;">13</td>
        <td class="border border-black p-2" style="border: 1px solid black;">
            <div>
                <span class="font-bold">Observaciones adicionales:</span><br>
                <textarea 
                    wire:model.live="observaciones"
                    wire:change="autoSave"
                    class="w-full min-h-20 border-b border-black bg-transparent focus:outline-none focus:bg-yellow-50 mt-2 px-1 resize-none"
                    placeholder="Escriba observaciones adicionales si las hubiera..."
                ></textarea>
            </div>
        </td>
    </tr>
    
</table>
