<x-filament::page>
    <div class="w-full overflow-x-auto bg-gradient-to-br from-blue-50 via-white to-blue-100 dark:from-gray-900 dark:via-gray-800 dark:to-gray-900 rounded-xl px-4 sm:px-8 py-6 shadow-lg border border-blue-100 dark:border-gray-700">
        <form method="GET" class="mb-6 flex flex-wrap gap-4 items-end bg-white dark:bg-gray-800 rounded-xl shadow px-6 py-4 border border-blue-100 dark:border-gray-700" id="filtros-mora-form">
            <div class="flex flex-col w-56 mr-2">
                <label class="block text-xs font-semibold mb-1 text-blue-700 dark:text-blue-200">Filtrar por</label>
                <select name="filtro" id="filtro-select" class="rounded-lg border border-blue-200 dark:border-blue-500 px-3 py-2 text-sm focus:ring-2 focus:ring-blue-400 focus:border-blue-400 dark:bg-gray-900 dark:text-white transition">
                    <option value="grupo" {{ request('filtro') == 'grupo' ? 'selected' : '' }}>Nombre del grupo</option>
                    <option value="fecha" {{ request('filtro') == 'fecha' ? 'selected' : '' }}>Rango de fechas</option>
                    <option value="monto" {{ request('filtro') == 'monto' ? 'selected' : '' }}>Monto mínimo</option>
                    <option value="estado" {{ request('filtro') == 'estado' ? 'selected' : '' }}>Estado de mora</option>
                </select>
            </div>
            <div class="flex flex-col w-40 filtro-campo" id="filtro-grupo">
                <label class="block text-xs font-semibold mb-1 text-blue-700 dark:text-blue-200">Grupo</label>
                <input type="text" name="grupo" value="{{ request('grupo') }}" placeholder="Nombre del grupo" class="rounded-lg border border-blue-200 dark:border-blue-500 px-3 py-2 text-sm focus:ring-2 focus:ring-blue-400 focus:border-blue-400 dark:bg-gray-900 dark:text-white transition placeholder:text-gray-400 dark:placeholder:text-gray-500">
            </div>
            <div class="flex flex-col w-36 filtro-campo" id="filtro-desde">
                <label class="block text-xs font-semibold mb-1 text-blue-700 dark:text-blue-200">Desde</label>
                <input type="date" name="desde" value="{{ request('desde') }}" class="rounded-lg border border-blue-200 dark:border-blue-500 px-3 py-2 text-sm focus:ring-2 focus:ring-blue-400 focus:border-blue-400 dark:bg-gray-900 dark:text-white transition">
            </div>
            <div class="flex flex-col w-36 filtro-campo" id="filtro-hasta">
                <label class="block text-xs font-semibold mb-1 text-blue-700 dark:text-blue-200">Hasta</label>
                <input type="date" name="hasta" value="{{ request('hasta') }}" class="rounded-lg border border-blue-200 dark:border-blue-500 px-3 py-2 text-sm focus:ring-2 focus:ring-blue-400 focus:border-blue-400 dark:bg-gray-900 dark:text-white transition">
            </div>
            <div class="flex flex-col w-40 filtro-campo" id="filtro-monto">
                <label class="block text-xs font-semibold mb-1 text-blue-700 dark:text-blue-200">Monto (mínimo)</label>
                <input type="number" step="0.01" name="monto" value="{{ request('monto') }}" placeholder="S/" class="rounded-lg border border-blue-200 dark:border-blue-500 px-3 py-2 text-sm focus:ring-2 focus:ring-blue-400 focus:border-blue-400 dark:bg-gray-900 dark:text-white transition placeholder:text-gray-400 dark:placeholder:text-gray-500">
            </div>
            <div class="flex flex-col w-40 filtro-campo" id="filtro-estado" style="display:none;">
                <label class="block text-xs font-semibold mb-1 text-blue-700 dark:text-blue-200">Estado de mora</label>
                <select name="estado_mora" class="rounded-lg border border-blue-200 dark:border-blue-500 px-3 py-2 text-sm focus:ring-2 focus:ring-blue-400 focus:border-blue-400 dark:bg-gray-900 dark:text-white transition">
                    <option value="">Todos</option>
                    <option value="pendiente" {{ request('estado_mora') == 'pendiente' ? 'selected' : '' }}>Pendiente</option>
                    <option value="pagada" {{ request('estado_mora') == 'pagada' ? 'selected' : '' }}>Pagada</option>
                    <option value="parcial" {{ request('estado_mora') == 'parcial' ? 'selected' : '' }}>Parcial</option>
                </select>
            </div>

<button type="submit" 
    class="inline-flex items-center gap-2 px-6 py-2 bg-gradient-to-r from-blue-200 to-blue-400 hover:from-blue-300 hover:to-blue-500 text-black text-base font-bold rounded-xl shadow-lg transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:ring-offset-2 dark:focus:ring-offset-gray-900 border border-blue-400 dark:border-blue-600"
>
    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
    </svg>
    Aplicar filtro
</button>

<button type="button" onclick="document.getElementById('exportarModal').showModal()" 
    class="inline-flex items-center gap-2 px-6 py-2 bg-gradient-to-r from-green-200 to-green-400 hover:from-green-300 hover:to-green-500 text-black text-base font-bold rounded-xl shadow-lg transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-green-400 focus:ring-offset-2 dark:focus:ring-offset-gray-900 border border-green-400 dark:border-green-600 ml-4"
>
    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
    </svg>
    Exportar PDF
</button>

        </form>
        <dialog id="exportarModal" class="rounded-xl shadow-xl p-0 w-full max-w-lg bg-white dark:bg-gray-800 border border-blue-200 dark:border-gray-700">
            <form method="GET" action="{{ route('moras.exportar.pdf') }}" class="p-6 flex flex-col gap-6">
                <h3 class="text-lg font-bold text-blue-700 dark:text-blue-200 mb-2">Exportar Moras a PDF</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="flex flex-col">
                        <label class="text-xs font-semibold mb-1 text-blue-700 dark:text-blue-200">Nombre del grupo</label>
                        <select name="grupo" class="rounded-lg border border-blue-200 dark:border-blue-500 px-3 py-2 text-sm focus:ring-2 focus:ring-blue-400 focus:border-blue-400 dark:bg-gray-900 dark:text-white transition">
                            <option value="">Todos</option>
                            @foreach(\App\Models\Grupo::orderBy('nombre_grupo')->pluck('nombre_grupo') as $nombreGrupo)
                                <option value="{{ $nombreGrupo }}" {{ request('grupo') == $nombreGrupo ? 'selected' : '' }}>{{ $nombreGrupo }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex flex-col">
                        <label class="text-xs font-semibold mb-1 text-blue-700 dark:text-blue-200">Monto mínimo (S/)</label>
                        <input type="number" step="0.01" name="monto" placeholder="Ej: 100.00" class="rounded-lg border border-blue-200 dark:border-blue-500 px-3 py-2 text-sm focus:ring-2 focus:ring-blue-400 focus:border-blue-400 dark:bg-gray-900 dark:text-white transition" />
                    </div>
                    <div class="flex flex-col">
                        <label class="text-xs font-semibold mb-1 text-blue-700 dark:text-blue-200">Fecha inicio</label>
                        <input type="date" name="desde" class="rounded-lg border border-blue-200 dark:border-blue-500 px-3 py-2 text-sm focus:ring-2 focus:ring-blue-400 focus:border-blue-400 dark:bg-gray-900 dark:text-white transition" />
                    </div>
                    <div class="flex flex-col">
                        <label class="text-xs font-semibold mb-1 text-blue-700 dark:text-blue-200">Fecha fin</label>
                        <input type="date" name="hasta" class="rounded-lg border border-blue-200 dark:border-blue-500 px-3 py-2 text-sm focus:ring-2 focus:ring-blue-400 focus:border-blue-400 dark:bg-gray-900 dark:text-white transition" />
                    </div>
                    <div class="flex flex-col md:col-span-2">
                        <label class="text-xs font-semibold mb-1 text-blue-700 dark:text-blue-200">Estado de mora</label>
                        <select name="estado_mora" class="rounded-lg border border-blue-200 dark:border-blue-500 px-3 py-2 text-sm focus:ring-2 focus:ring-blue-400 focus:border-blue-400 dark:bg-gray-900 dark:text-white transition">
                            <option value="">Todos</option>
                            <option value="pendiente">Pendiente</option>
                            <option value="pagada">Pagado</option>
                            <option value="parcial">Parcial</option>
                        </select>
                    </div>
                </div>
                <div class="flex justify-end gap-3 mt-4">
                    <button type="button" onclick="document.getElementById('exportarModal').close()" class="px-4 py-2 rounded bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-white font-semibold hover:bg-gray-300 dark:hover:bg-gray-600 transition">Cancelar</button>
                    <button type="submit" class="px-4 py-2 rounded bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-white font-semibold hover:bg-gray-300 dark:hover:bg-gray-600 transition">Exportar PDF</button>
                </div>
            </form>
        </dialog>
        <script>
            function mostrarCamposFiltro() {
                const filtro = document.getElementById('filtro-select').value;
                document.getElementById('filtro-grupo').style.display = (filtro === 'grupo') ? 'flex' : 'none';
                document.getElementById('filtro-desde').style.display = (filtro === 'fecha') ? 'flex' : 'none';
                document.getElementById('filtro-hasta').style.display = (filtro === 'fecha') ? 'flex' : 'none';
                document.getElementById('filtro-monto').style.display = (filtro === 'monto') ? 'flex' : 'none';
                document.getElementById('filtro-estado').style.display = (filtro === 'estado') ? 'flex' : 'none';
            }
            document.addEventListener('DOMContentLoaded', mostrarCamposFiltro);
            document.getElementById('filtro-select').addEventListener('change', mostrarCamposFiltro);
        </script>
        <table class="w-full bg-white dark:bg-gray-800 rounded-lg overflow-hidden shadow text-sm leading-tight">
            <thead class="bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-white text-center">
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
                           {{ max(0, floor(\Carbon\Carbon::parse($cuota->fecha_vencimiento)->addDay()->diffInDays(\Carbon\Carbon::now(), false))) }}
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
