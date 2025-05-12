<x-filament::page>
    <div class="overflow-x-auto bg-gray-900 rounded-lg">
        <table class="min-w-full bg-gray-800">
            <thead class="bg-gray-700 text-white text-left">
                <tr>
                    <th class="px-6 py-3">#</th>
                    <th class="px-6 py-3">Cuota Grupal</th>
                    <th class="px-6 py-3">Días de Atraso</th>
                    <th class="px-6 py-3">Monto Mora</th>
                    <th class="px-6 py-3">Estado</th>
                    <th class="px-6 py-3">Fecha</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($moras as $mora)
                    <tr class="hover:bg-gray-700">
                        <td class="px-6 py-4 text-white">{{ $mora->id }}</td>
                        <td class="px-6 py-4 text-white">{{ $mora->cuotaGrupal->nombre ?? 'N/A' }}</td>
                        <td class="px-6 py-4 text-white">{{ $mora->dias_atraso }}</td>
                        <td class="px-6 py-4 text-white">S/ {{ number_format($mora->monto_mora, 2) }}</td>
                        <td class="px-6 py-4">
                            <span class="px-3 py-2 rounded text-white text-xs {{ $mora->estado_mora === 'pendiente' ? 'bg-red-500' : 'bg-green-500' }}">
                                {{ ucfirst(str_replace('_', ' ', $mora->estado_mora)) }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-white">{{ $mora->created_at->format('d/m/Y') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-6 py-4 text-center text-gray-500">No hay moras registradas.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-filament::page>
