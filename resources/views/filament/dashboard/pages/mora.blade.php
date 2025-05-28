<x-filament::page>
    <div class="w-full overflow-x-auto bg-gradient-to-br from-blue-50 via-white to-blue-100 dark:from-gray-900 dark:via-gray-800 dark:to-gray-900 rounded-xl px-4 sm:px-8 py-6 shadow-lg border border-blue-100 dark:border-gray-700">
        <table class="w-full bg-white dark:bg-gray-800 rounded-lg overflow-hidden shadow text-sm leading-tight">
            <thead class="bg-blue-100 dark:bg-gray-700 text-blue-900 dark:text-white text-center">
                <tr>
                    <th class="px-4 py-3 font-semibold">Nombre del Grupo</th>
                    <th class="px-4 py-3 font-semibold">N° Integrantes</th>
                    <th class="px-4 py-3 font-semibold">N° Cuota</th>
                    <th class="px-4 py-3 font-semibold">Monto de Cuota</th>
                    <th class="px-4 py-3 font-semibold">Fecha Vencimiento</th>
                    <th class="px-4 py-3 font-semibold">Saldo pendiente</th>
                    <th class="px-4 py-3 font-semibold">Días de Atraso</th>
                    <th class="px-4 py-3 font-semibold">Monto Mora</th>
                    <th class="px-4 py-3 font-semibold">Monto total a pagar</th>
                    <th class="px-4 py-3 font-semibold">Estado</th>
                    <th class="px-4 py-3 font-semibold">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($cuotas_mora as $cuota)
                    <tr class="transition duration-150 text-center border-b border-blue-100 dark:border-gray-700 bg-white dark:bg-gray-800 hover:bg-blue-50 dark:hover:bg-gray-800">
                        <td class="px-4 py-3 text-gray-800 dark:text-white">{{ $cuota->prestamo->grupo->nombre_grupo ?? 'N/A' }}</td>
                        <td class="px-4 py-3 text-gray-800 dark:text-white">{{ $cuota->prestamo->grupo->clientes()->count() ?? 0 }}</td>
                        <td class="px-4 py-3 text-gray-800 dark:text-white">{{ $cuota->numero_cuota }}</td>
                        <td class="px-4 py-3 text-blue-700 dark:text-blue-200">S/ {{ number_format($cuota->monto_cuota_grupal, 2) }}</td>
                        <td class="px-4 py-3 text-gray-800 dark:text-white">{{ \Carbon\Carbon::parse($cuota->fecha_vencimiento)->format('d/m/Y') }}</td>
                        <td class="px-4 py-3 text-gray-800 dark:text-white">
                            @if($cuota->saldo_pendiente < $cuota->monto_cuota_grupal)
                                <span class="px-2 py-1 rounded bg-yellow-100 text-yellow-800 font-semibold">S/ {{ number_format($cuota->saldo_pendiente, 2) }}</span>
                                <span class="text-xs text-yellow-700 block"></span>
                            @else
                                S/ {{ number_format($cuota->saldo_pendiente, 2) }}
                            @endif
                        </td>
                        <td class="px-4 py-3 text-red-600 dark:text-red-300 font-semibold">
                            {{ floor(\Carbon\Carbon::parse($cuota->fecha_vencimiento)->addDay()->diffInDays(\Carbon\Carbon::now(), false)) }}
                        </td>
                        <td class="px-4 py-3 text-pink-700 dark:text-pink-200">
                            @if($cuota->mora)
                                S/ {{ number_format(abs($cuota->mora->monto_mora_calculado), 2) }}
                            @else
                                S/ 0.00
                            @endif
                        </td>
                        <td class="px-4 py-3 text-green-700 dark:text-green-200">
                            @php
                                $mostrarTotal = false;
                                $montoTotal = 0;
                                if ($cuota->mora && in_array($cuota->mora->estado_mora, ['pendiente', 'parcial'])) {
                                    $montoTotal = $cuota->saldo_pendiente + abs($cuota->mora->monto_mora_calculado);
                                    $mostrarTotal = $montoTotal > 0;
                                } elseif ($cuota->saldo_pendiente > 0) {
                                    $montoTotal = $cuota->saldo_pendiente;
                                    $mostrarTotal = true;
                                }
                            @endphp
                            @if($mostrarTotal)
                                S/ {{ number_format($montoTotal, 2) }}
                            @else
                                <span class="text-gray-400">S/ 0.00</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-gray-800 dark:text-white">
                            @if($cuota->mora)
                                <span class="px-3 py-1 rounded text-xs font-semibold bg-red-200 text-red-800 dark:bg-red-700 dark:text-white">
                                    {{ ucfirst(str_replace('_', ' ', $cuota->mora->estado_mora)) }}
                                </span>
                            @else
                                <span class="px-3 py-1 rounded text-xs font-semibold bg-gray-200 text-gray-800 dark:bg-gray-600 dark:text-white">
                                    Sin mora
                                </span>
                            @endif
                        </td>
                        <td class="px-4 py-2 text-gray-800 dark:text-white">
                            @if($cuota->mora && in_array($cuota->mora->estado_mora, ['pendiente', 'parcial']))
                                <a href="{{ route('filament.dashboard.resources.pagos.create', ['cuota_grupal_id' => $cuota->id]) }}"
                                    class="inline-flex items-center px-4 py-2 text-sm font-semibold rounded-lg shadow 
                                        bg-blue-100 hover:bg-blue-200 text-black 
                                        dark:bg-blue-500 dark:hover:bg-blue-600 
                                        border border-blue-700 dark:border-blue-400
                                        transition duration-150 ease-in-out">
                                    <svg class="w-4 h-4 mr-2 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <span class="text-black dark:text-white align-middle">Registrar pago</span>
                                </a>
                            @elseif($cuota->mora && $cuota->mora->estado_mora === 'pagada')
                                <span class="inline-flex items-center px-3 py-1 rounded bg-green-100 text-green-800 font-semibold text-xs">
                                    <svg class="w-4 h-4 mr-1 text-green-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                    </svg>
                                    Pagada
                                </span>
                            @else
                                <span class="text-gray-400 text-xs">Sin acciones</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="11" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400 bg-white dark:bg-gray-800 rounded-b-lg">No hay cuotas en mora.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-filament::page>
