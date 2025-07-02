<x-filament::page>
    <div class="w-full overflow-x-auto bg-gradient-to-br from-blue-50 via-white to-blue-100 dark:from-gray-900 dark:via-gray-800 dark:to-gray-900 rounded-xl px-4 sm:px-8 py-6 shadow-lg border border-blue-100 dark:border-gray-700">
        <!-- Botones de acción principales alineados a la derecha -->
        <div class="flex flex-wrap gap-3 items-center justify-end mb-8">
            <!-- Botón para abrir modal de filtros -->
            <button type="button" onclick="document.getElementById('filtrosModal').showModal()"
                class="inline-flex items-center gap-2 px-6 py-2 bg-gradient-to-r from-blue-200 to-blue-400 hover:from-blue-300 hover:to-blue-500 text-black text-base font-bold rounded-xl shadow-lg transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:ring-offset-2 dark:focus:ring-offset-gray-900 border border-blue-400 dark:border-blue-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.707A1 1 0 013 7z" />
                </svg>
                Filtros
            </button>

            <!-- Botón limpiar filtros (solo visible si hay filtros activos) -->
            @if(request()->hasAny(['grupo', 'estado_mora','desde', 'hasta', ]))
                <a href="{{ request()->url() }}"
                    class="inline-flex items-center gap-2 px-6 py-2 bg-gradient-to-r from-gray-200 to-gray-400 hover:from-gray-300 hover:to-gray-500 text-black text-base font-bold rounded-xl shadow-lg transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-gray-400 focus:ring-offset-2 dark:focus:ring-offset-gray-900 border border-gray-400 dark:border-gray-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    Limpiar Filtros
                </a>
            @endif

            <!-- Botón exportar PDF -->
            <button type="button" onclick="document.getElementById('exportarModal').showModal()"
                class="inline-flex items-center gap-2 px-6 py-2 bg-gradient-to-r from-green-200 to-green-400 hover:from-green-300 hover:to-green-500 text-black text-base font-bold rounded-xl shadow-lg transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-green-400 focus:ring-offset-2 dark:focus:ring-offset-gray-900 border border-green-400 dark:border-green-600">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M12 16v-8m0 8l-4-4m4 4l4-4M4 4h16v16H4V4z" />
            </svg>

                Exportar PDF
            </button>
        </div>

        <!-- Indicadores de filtros activos -->
        @if(request()->hasAny(['grupo', 'estado_mora','desde', 'hasta']))
            <div class="mb-6 p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-700">
                <div class="flex items-center gap-2 text-sm text-blue-700 dark:text-blue-300">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span class="font-semibold">Filtros activos:</span>
                    @if(request('grupo'))
                        <span class="px-2 py-1 bg-blue-100 dark:bg-blue-800 rounded text-xs">Grupo: {{ request('grupo') }}</span>
                    @endif
                                        @if(request('estado_mora'))
                        <span class="px-2 py-1 bg-blue-100 dark:bg-blue-800 rounded text-xs">Estado: {{ ucfirst(request('estado_mora')) }}</span>
                    @endif
                    @if(request('desde'))
                        <span class="px-2 py-1 bg-blue-100 dark:bg-blue-800 rounded text-xs">Desde: {{ request('desde') }}</span>
                    @endif
                    @if(request('hasta'))
                        <span class="px-2 py-1 bg-blue-100 dark:bg-blue-800 rounded text-xs">Hasta: {{ request('hasta') }}</span>
                    @endif

                </div>
            </div>
        @endif
@php
    $user = auth()->user();
    $gruposQuery = \App\Models\Grupo::query();

    if ($user->hasRole('Asesor')) {
        $asesor = \App\Models\Asesor::where('user_id', $user->id)->first();
        if ($asesor) {
            $gruposQuery->where('asesor_id', $asesor->id);
        }
    }

    $grupos = $gruposQuery->orderBy('nombre_grupo')->get();
@endphp

<!-- Modal de Filtros -->
<dialog id="filtrosModal" class="rounded-xl shadow-xl p-0 w-full max-w-lg bg-white dark:bg-gray-800 border border-blue-200 dark:border-gray-700">
    <form method="GET" class="p-6 flex flex-col gap-6" id="filtros-mora-form">
        <div class="flex items-center justify-between border-b border-gray-200 dark:border-gray-600 pb-4">
            <h3 class="text-xl font-bold text-blue-700 dark:text-blue-200 flex items-center gap-2">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.707A1 1 0 013 7z" />
                </svg>
                Filtros de Búsqueda
            </h3>
            <button type="button" onclick="document.getElementById('filtrosModal').close()"
                class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition-colors">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
<!-- Campo de búsqueda de grupo con datalist -->
<div class="flex flex-col">
    <label class="text-sm font-semibold mb-1 text-blue-700 dark:text-blue-200">Nombre del grupo</label>
    <input list="listaGrupos" name="grupo" value="{{ request('grupo') }}"
        class="rounded-lg border border-blue-200 dark:border-blue-500 px-3 py-2 text-sm focus:ring-2 focus:ring-blue-400 focus:border-blue-400 dark:bg-gray-900 dark:text-white transition"
        placeholder="Escribe el nombre del grupo...">
    <datalist id="listaGrupos">
        @foreach($grupos as $grupo)
            <option value="{{ $grupo->nombre_grupo }}">
        @endforeach
    </datalist>
</div>


            <!-- Estado -->
            <div class="flex flex-col">
                <label class="block text-sm font-semibold mb-1 text-blue-700 dark:text-blue-200">Estado</label>
                <select name="estado_mora"
                    class="rounded-lg border border-blue-200 dark:border-blue-500 px-3 py-2 text-sm focus:ring-2 focus:ring-blue-400 focus:border-blue-400 dark:bg-gray-900 dark:text-white transition">
                    <option value="">Todos</option>
                       <option value="pagada" {{ request('estado_mora') == 'pagada' ? 'selected' : '' }}>Pagada</option>
                        <option value="parcial" {{ request('estado_mora') == 'parcial' ? 'selected' : '' }}>Parcial</option>
                    <option value="pendiente" {{ request('estado_mora') == 'pendiente' ? 'selected' : '' }}>Pendiente</option>

                </select>
            </div>

            <!-- Desde -->
            <div class="flex flex-col">
                <label class="block text-sm font-semibold mb-1 text-blue-700 dark:text-blue-200">Desde</label>
                <input type="date" name="desde" value="{{ request('desde') }}"
                    class="rounded-lg border border-blue-200 dark:border-blue-500 px-3 py-2 text-sm focus:ring-2 focus:ring-blue-400 focus:border-blue-400 dark:bg-gray-900 dark:text-white transition">
            </div>

            <!-- Hasta -->
            <div class="flex flex-col">
                <label class="block text-sm font-semibold mb-1 text-blue-700 dark:text-blue-200">Hasta</label>
                <input type="date" name="hasta" value="{{ request('hasta') }}"
                    class="rounded-lg border border-blue-200 dark:border-blue-500 px-3 py-2 text-sm focus:ring-2 focus:ring-blue-400 focus:border-blue-400 dark:bg-gray-900 dark:text-white transition">
            </div>
        </div>

        <!-- Botones del modal -->
        <div class="flex justify-end gap-3 pt-4 border-t border-gray-200 dark:border-gray-600">
            <button type="button" onclick="limpiarFiltrosModal()"
                class="px-6 py-3 rounded-lg bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 font-semibold hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                Limpiar
            </button>
            <button type="submit"
                class="px-6 py-3 rounded-lg bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 font-semibold hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                Aplicar Filtros
            </button>
        </div>
    </form>
</dialog>

        <!-- Modal de Exportar PDF (sin cambios) -->
     <dialog id="exportarModal" class="rounded-xl shadow-xl p-0 w-full max-w-lg bg-white dark:bg-gray-800 border border-blue-200 dark:border-gray-700">
    <form method="GET" action="{{ route('moras.exportar.pdf') }}" class="p-6 flex flex-col gap-6">
        <h3 class="text-lg font-bold text-blue-700 dark:text-blue-200 mb-2">Exportar Moras a PDF</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="flex flex-col">
                <label class="text-xs font-semibold mb-1 text-blue-700 dark:text-blue-200">Nombre del grupo</label>
                <input list="gruposExportar" name="grupo" value="{{ request('grupo') }}"
                    class="rounded-lg border border-blue-200 dark:border-blue-500 px-3 py-2 text-sm focus:ring-2 focus:ring-blue-400 focus:border-blue-400 dark:bg-gray-900 dark:text-white transition"
                    placeholder="Escribe el nombre del grupo...">
                <datalist id="gruposExportar">
                    @php
                        $user = auth()->user();
                        $gruposQuery = \App\Models\Grupo::query();

                        if ($user->hasRole('Asesor')) {
                            $asesor = \App\Models\Asesor::where('user_id', $user->id)->first();
                            if ($asesor) {
                                $gruposQuery->where('asesor_id', $asesor->id);
                            }
                        }

                        $grupos = $gruposQuery->orderBy('nombre_grupo')->get();
                    @endphp
                    @foreach($grupos as $grupo)
                        <option value="{{ $grupo->nombre_grupo }}">
                    @endforeach
                </datalist>
            </div>

                <div class="flex flex-col">
                    <label class="text-xs font-semibold mb-1 text-blue-700 dark:text-blue-200">Estado de mora</label>
                    <select name="estado_mora" class="rounded-lg border border-blue-200 dark:border-blue-500 px-3 py-2 text-sm focus:ring-2 focus:ring-blue-400 focus:border-blue-400 dark:bg-gray-900 dark:text-white transition">
                        <option value="">Todos</option>
                         <option value="pagada">Pagado</option>
                         <option value="parcial">Parcial</option>
                        <option value="pendiente">Pendiente</option>

                    </select>
                </div>

                    <div class="flex flex-col">
                        <label class="text-xs font-semibold mb-1 text-blue-700 dark:text-blue-200">Fecha inicio</label>
                        <input type="date" name="desde" class="rounded-lg border border-blue-200 dark:border-blue-500 px-3 py-2 text-sm focus:ring-2 focus:ring-blue-400 focus:border-blue-400 dark:bg-gray-900 dark:text-white transition" />
                    </div>
                    <div class="flex flex-col">
                        <label class="text-xs font-semibold mb-1 text-blue-700 dark:text-blue-200">Fecha fin</label>
                        <input type="date" name="hasta" class="rounded-lg border border-blue-200 dark:border-blue-500 px-3 py-2 text-sm focus:ring-2 focus:ring-blue-400 focus:border-blue-400 dark:bg-gray-900 dark:text-white transition" />
                    </div>

                </div>
                <div class="flex justify-end gap-3 mt-4">
                    <button type="button" onclick="document.getElementById('exportarModal').close()" class="px-4 py-2 rounded bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-white font-semibold hover:bg-gray-300 dark:hover:bg-gray-600 transition">Cancelar</button>
                    <button type="submit" class="px-4 py-2 rounded bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-white font-semibold hover:bg-gray-300 dark:hover:bg-gray-600 transition">Exportar PDF</button>
                </div>
            </form>
        </dialog>

        <!-- JavaScript para manejar el modal de filtros -->
        <script>
            // Función para limpiar los filtros dentro del modal
            function limpiarFiltrosModal() {
                const form = document.getElementById('filtros-mora-form');
                const inputs = form.querySelectorAll('input, select');
                inputs.forEach(input => {
                    if (input.type === 'text' || input.type === 'number' || input.type === 'date') {
                        input.value = '';
                    } else if (input.type === 'select-one') {
                        input.selectedIndex = 0;
                    }
                });
            }

            // Cerrar modal automáticamente después de aplicar filtros
            document.getElementById('filtros-mora-form').addEventListener('submit', function() {
                setTimeout(() => {
                    document.getElementById('filtrosModal').close();
                }, 100);
            });

            // Cerrar modal al hacer clic fuera de él
            document.getElementById('filtrosModal').addEventListener('click', function(e) {
                if (e.target === this) {
                    this.close();
                }
            });
        </script>

        <!-- Tabla (sin cambios) -->
        <table class="w-full mt-6 bg-white dark:bg-gray-800 rounded-lg overflow-hidden shadow text-sm leading-tight">
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
                    <tr class="transition duration-150 text-left border-b border-blue-100 dark:border-gray-700 bg-white dark:bg-gray-800 hover:bg-blue-50 dark:hover:bg-gray-800">
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
                            @if($cuota->mora)
                                {{ $cuota->mora->dias_atraso }}
                            @else
                                {{ max(0, floor(\Carbon\Carbon::parse($cuota->fecha_vencimiento)->addDay()->diffInDays(\Carbon\Carbon::now(), false))) }}
                            @endif
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
                        <!-- Reemplaza la columna de Acciones en tu vista -->
                <td class="px-4 py-2 text-gray-800 dark:text-white">
                    @if($cuota->mora && in_array($cuota->mora->estado_mora, ['pendiente', 'parcial']))
                        @if(auth()->user()->hasRole('Jefe de creditos'))
                            <!-- Mensaje para Jefe de Crédito -->
                            <div class="inline-flex items-center px-4 py-2 text-sm font-semibold rounded-lg shadow
                                        bg-yellow-100 text-yellow-800
                                        dark:bg-yellow-600 dark:text-yellow-100
                                        border border-yellow-300 dark:border-yellow-500">
                                <svg class="w-4 h-4 mr-2 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.866-.833-2.598 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z" />
                                </svg>
                                <span class="align-middle">Solo visualización</span>
                            </div>
                        @else
                            <!-- Botón normal para otros roles -->
                            @php
                                // Validación: solo permitir registrar pago si no hay cuotas anteriores con saldo pendiente
                                $puedeRegistrarPago = true;
                                if ($cuota->prestamo) {
                                    $cuotasAnteriores = $cuota->prestamo->cuotasGrupales
                                        ->where('numero_cuota', '<', $cuota->numero_cuota)
                                        ->sortBy('numero_cuota');
                                    foreach ($cuotasAnteriores as $anterior) {
                                        if ($anterior->saldoPendiente() > 0) {
                                            $puedeRegistrarPago = false;
                                            break;
                                        }
                                    }
                                }
                            @endphp
                            @if($puedeRegistrarPago)
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
                            @else
                                <button type="button"
                                    onclick="alert('No puedes registrar el pago de esta cuota porque existen cuotas anteriores con saldo pendiente. Debes pagar primero la cuota anterior.')"
                                    class="inline-flex items-center px-4 py-2 text-sm font-semibold rounded-lg shadow
                                        bg-gray-200 text-gray-500 cursor-not-allowed
                                        border border-gray-400 dark:border-gray-600
                                        transition duration-150 ease-in-out"
                                    disabled>
                                    <svg class="w-4 h-4 mr-2 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <span class="text-black dark:text-white align-middle">Registrar pago</span>
                                </button>
                            @endif
                        @endif
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
