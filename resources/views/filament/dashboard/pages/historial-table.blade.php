<div class="space-y-4 bg-gray-900 text-white p-6 rounded-xl shadow-lg">
    <div class="overflow-x-auto rounded-lg border border-gray-700">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-800 text-gray-300">
                <tr>
                    <th class="px-4 py-3 text-left">Fecha</th>
                    <th class="px-6 py-3 text-left">Consulta</th>
                    <th class="px-6 py-3 text-left">Respuesta</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-700">
                @forelse ($this->consultas as $consulta)
                    <tr class="hover:bg-gray-800 transition duration-200">
                        <td class="px-4 py-3 text-gray-400">{{ $consulta->created_at->format('d/m/Y H:i') }}</td>
                        <!-- Ajuste: Mayor espacio con padding y line-height -->
                        <td class="px-6 py-6 leading-relaxed">{{ $consulta->consulta }}</td>
                        <td class="px-6 py-6 leading-relaxed text-gray-300">{{ $consulta->respuesta }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="px-4 py-4 text-center text-gray-500">No hay consultas registradas.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
