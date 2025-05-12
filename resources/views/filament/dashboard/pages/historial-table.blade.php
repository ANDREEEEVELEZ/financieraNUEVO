<div class="space-y-4 bg-gray-900 text-white p-6 rounded-xl shadow-lg">
    <h2 class="text-xl font-semibold text-gray-100">Historial de Consultas</h2>
    <div class="overflow-x-auto rounded-lg border border-gray-700">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-800 text-gray-300">
                <tr>
                    <th class="px-4 py-3 text-left">Fecha</th>
                    <th class="px-4 py-3 text-left">Consulta</th>
                    <th class="px-4 py-3 text-left">Respuesta</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-700">
                @forelse ($this->consultas as $consulta)
                    <tr class="hover:bg-gray-800 transition duration-200">
                        <td class="px-4 py-2 text-gray-400">{{ $consulta->created_at->format('d/m/Y H:i') }}</td>
                        <td class="px-4 py-2">{{ $consulta->consulta }}</td>
                        <td class="px-4 py-2 text-gray-300">{{ $consulta->respuesta }}</td>
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
