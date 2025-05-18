<div class="bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 p-4 rounded-lg shadow-md">
    <div class="overflow-x-auto rounded-md border border-gray-200 dark:border-gray-700">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-800 text-gray-700 dark:text-gray-200">
                <tr>
                    <th class="px-4 py-3 text-left font-semibold">Fecha</th>
                    <th class="px-4 py-3 text-left font-semibold">Consulta</th>
                    <th class="px-4 py-3 text-left font-semibold">Respuesta</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                @forelse ($this->consultas as $consulta)
                    <tr class="hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors duration-150">
                        <td class="px-4 py-3 text-gray-800 dark:text-gray-200">
                            {{ $consulta->created_at->format('d/m/Y H:i') }}
                        </td>
                        <td class="px-4 py-3 text-gray-900 dark:text-gray-100">
                            {{ $consulta->consulta }}
                        </td>
                        <td class="px-4 py-3 text-gray-700 dark:text-gray-300">
                            {{ $consulta->respuesta }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="px-4 py-4 text-center text-gray-500 dark:text-gray-400">
                            No hay consultas registradas.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
