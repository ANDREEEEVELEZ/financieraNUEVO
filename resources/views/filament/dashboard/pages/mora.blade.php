<x-filament::page>
    <div class="w-full overflow-x-auto bg-white dark:bg-gray-900 rounded-lg px-4 sm:px-6 py-4 shadow">
        <table class="w-full bg-white dark:bg-gray-800 text-sm leading-tight">
            <thead class="bg-gray-100 dark:bg-gray-700 text-gray-900 dark:text-white text-left">
                <tr>
                    <th class="px-4 py-2">#</th>
                    <th class="px-4 py-2">Cuota Grupal</th>
                    <th class="px-4 py-2">Días de Atraso</th>
                    <th class="px-4 py-2">Monto Mora</th>
                    <th class="px-4 py-2">Fecha</th>
                    <th class="px-4 py-2">Estado</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($moras as $mora)
                    <tr class="hover:bg-gray-100 dark:hover:bg-gray-700 transition duration-150">
                        <td class="px-4 py-2 text-gray-800 dark:text-white">{{ $mora->id }}</td>
                        <td class="px-4 py-2 text-gray-800 dark:text-white">{{ $mora->cuotaGrupal->nombre ?? 'N/A' }}</td>
                        <td class="px-4 py-2 text-gray-800 dark:text-white">{{ $mora->dias_atraso }}</td>
                        <td class="px-4 py-2 text-gray-800 dark:text-white">S/ {{ number_format($mora->monto_mora, 2) }}</td>
                        <td class="px-4 py-2 text-gray-800 dark:text-white">{{ $mora->created_at->format('d/m/Y') }}</td>
                        <td class="px-4 py-2">
                            <span class="px-3 py-1 rounded text-white text-xs {{ $mora->estado_mora === 'pendiente' ? 'bg-red-500' : 'bg-green-500' }}">
                                {{ ucfirst(str_replace('_', ' ', $mora->estado_mora)) }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-4 text-center text-gray-500 dark:text-gray-400">No hay moras registradas.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-filament::page>
