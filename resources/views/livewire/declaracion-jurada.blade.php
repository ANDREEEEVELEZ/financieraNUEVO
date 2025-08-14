<div class="p-6 max-w-6xl mx-auto">
    <div class="mb-6">
        <div class="flex items-center justify-between mb-4">
            <h1 class="text-2xl font-bold text-gray-900">
                Declaración Jurada de Conocimiento del Cliente - Régimen General
            </h1>
            <a href="{{ route('filament.dashboard.resources.clientes.index') }}" 
               class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition-colors">
                <i class="fas fa-arrow-left mr-2"></i>
                Volver a Clientes
            </a>
        </div>
        <p class="text-sm text-gray-600 mb-2">
            Información mínima para ser llenada por el cliente del sujeto obligado
        </p>
        <p class="text-xs text-gray-500">
            Resolución SBS 2351-2023 - Formato de Declaración Jurada Régimen General - Persona Natural
        </p>
        
        @if($cliente)
            <div class="mt-4 p-4 bg-blue-50 rounded-lg border border-blue-200">
                <h3 class="text-lg font-semibold text-blue-900">Cliente Seleccionado:</h3>
                <p class="text-blue-800">
                    {{ $cliente->persona->nombre }} {{ $cliente->persona->apellidos }} 
                    - DNI: {{ $cliente->persona->DNI }}
                </p>
            </div>
        @endif
    </div>

    <div class="declaracion-form p-6">
        <form wire:submit.prevent="generarPDF">
            {{ $this->form }}
            
            <div class="mt-8 flex justify-end space-x-4 border-t pt-6">
                <button 
                    type="button" 
                    wire:click="cancelar"
                    class="px-6 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors"
                >
                    <i class="fas fa-times mr-2"></i>
                    Cancelar
                </button>
                
                <button 
                    type="submit"
                    class="px-6 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors"
                >
                    <i class="fas fa-file-pdf mr-2"></i>
                    Generar Declaración Jurada
                </button>
            </div>
        </form>
    </div>
    
    <div class="mt-8 p-4 bg-yellow-50 rounded-lg border border-yellow-200">
        <h3 class="text-lg font-semibold text-yellow-800 mb-2">
            <i class="fas fa-info-circle mr-2"></i>
            Información Importante:
        </h3>
        <ul class="text-sm text-yellow-700 space-y-1">
            <li>• Los campos con fondo gris se autocompletaron con información del cliente en el sistema</li>
            <li>• Complete los campos restantes según la información proporcionada por el cliente</li>
            <li>• Esta declaración se genera para impresión y firma del cliente</li>
            <li>• Los datos NO se guardan en la base de datos del sistema</li>
            <li>• Asegúrese de imprimir y archivar físicamente el documento firmado</li>
        </ul>
    </div>
</div>
