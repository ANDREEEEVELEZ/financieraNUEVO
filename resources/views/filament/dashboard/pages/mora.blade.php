<x-filament::page>



    <div class="w-full overflow-x-auto bg-white dark:bg-gray-900 rounded-lg px-4 sm:px-6 py-4 shadow">
        <table class="w-full bg-white dark:bg-gray-800 text-sm leading-tight">
            <thead class="bg-gray-100 dark:bg-gray-700 text-gray-900 dark:text-white text-left">
                <tr>
                    <th class="px-4 py-2">#</th>
                    <th class="px-4 py-2">Nombre del Grupo</th>
                    <th class="px-4 py-2">Monto de Cuota</th>
                    <th class="px-4 py-2">N° Cuota</th>
                    <th class="px-4 py-2">Fecha Vencimiento</th>
                    <th class="px-4 py-2">Días de Atraso</th>
                    <th class="px-4 py-2">Monto Mora</th>
                    <th class="px-4 py-2">Estado</th>
                </tr>
            </thead>
                    <tbody>
           @forelse ($cuotas_mora as $cuota)
            <tr class="hover:bg-gray-100 dark:hover:bg-gray-700 transition duration-150">
                <td class="px-4 py-2 text-gray-800 dark:text-white">{{ $cuota->id }}</td>
                <td class="px-4 py-2 text-gray-800 dark:text-white">{{ $cuota->prestamo->grupo->nombre_grupo ?? 'N/A' }}</td>
                <td class="px-4 py-2 text-gray-800 dark:text-white">S/ {{ number_format($cuota->monto_cuota_grupal, 2) }}</td>
                <td class="px-4 py-2 text-gray-800 dark:text-white">{{ $cuota->numero_cuota }}</td>
                <td class="px-4 py-2 text-gray-800 dark:text-white">{{ \Carbon\Carbon::parse($cuota->fecha_vencimiento)->format('d/m/Y') }}</td>
                <td class="px-4 py-2 text-gray-800 dark:text-white">
                    {{ floor(\Carbon\Carbon::parse($cuota->fecha_vencimiento)->diffInDays(\Carbon\Carbon::now(), false)) }}

                </td>
                <td class="px-4 py-2 text-gray-800 dark:text-white">
                    S/ {{ number_format(($cuota->saldo_pendiente ?? 0) * 0.05, 2) }} {{-- ejemplo de cálculo de mora --}}
                </td>
                <td class="px-4 py-2 text-gray-800 dark:text-white">
                    <span class="px-3 py-1 rounded  dark:text-white text-xs bg-red-500">{{ $cuota->estado_cuota_grupal }}</span>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="9" class="px-4 py-4 text-center text-gray-500 dark:text-gray-400">No hay cuotas en mora.</td>
            </tr>
            @endforelse
        </tbody>
        </table>
    </div>
</x-filament::page>
