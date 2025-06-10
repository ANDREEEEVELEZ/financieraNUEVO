<x-filament-panels::page>
<link href="https://cdn.jsdelivr.net/npm/flowbite@3.1.2/dist/flowbite.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/flowbite@3.1.2/dist/flowbite.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
@php
    // Solo usar las variables realmente definidas en getViewData()
    $cuotasEstadosBarArr = $cuotasEstadosBar;
    $pagosPorFechaArr = $pagosPorFecha->toArray();
    $pagosPorFechaLabels = array_keys($pagosPorFechaArr);
    $pagosPorFechaVals = array_values($pagosPorFechaArr);
    $pagosPieArr = $pagosPie;
    $moraPorGrupoArr = $moraPorGrupo->toArray();
@endphp
<div class="w-full max-w-6xl mx-auto">
    <!-- Filtro de rango de fechas -->
    <form method="GET" class="mb-6 flex flex-wrap gap-4 items-end bg-white dark:bg-gray-800 rounded-xl shadow px-6 py-4 border border-blue-100 dark:border-gray-700" id="filtros-asesor-form">
        <div class="flex flex-col w-36">
            <label class="text-sm text-gray-700 dark:text-gray-300 font-semibold mt-1">Desde</label>
            <input type="date" name="desde" value="{{ request('desde') }}" class="rounded-lg border border-blue-200 dark:border-blue-500 px-3 py-2 text-sm focus:ring-2 focus:ring-blue-400 focus:border-blue-400 dark:bg-gray-900 dark:text-white transition">
        </div>
        <div class="flex flex-col w-36">
            <label class="text-sm text-gray-700 dark:text-gray-300 font-semibold mt-1">Hasta</label>
            <input type="date" name="hasta" value="{{ request('hasta') }}" class="rounded-lg border border-blue-200 dark:border-blue-500 px-3 py-2 text-sm focus:ring-2 focus:ring-blue-400 focus:border-blue-400 dark:bg-gray-900 dark:text-white transition">
        </div>
        <button type="submit" class="inline-flex items-center gap-2 px-6 py-2 bg-gradient-to-r from-blue-200 to-blue-400 hover:from-blue-300 hover:to-blue-500 text-black text-base font-bold rounded-xl shadow-lg transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:ring-offset-2 dark:focus:ring-offset-gray-900 border border-blue-400 dark:border-blue-600 ml-4">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
            </svg>
            Aplicar filtro
        </button>
    </form>
    <div class="mb-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- General -->
            <div>
                <h2 class="text-2xl font-extrabold text-black mb-6">Resumen General</h2>
                <!-- Nuevo diseño de widgets con Tailwind, sombras, bordes y animación -->
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
                    <!-- Total de Grupos (ya existente) -->
                    <div class="relative bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl shadow-lg p-4 flex flex-col items-center group transition-transform hover:-translate-y-1 hover:shadow-2xl duration-200">
                        <div class="absolute top-2 right-2 opacity-10 group-hover:opacity-20 text-5xl pointer-events-none select-none">
                            <svg class="w-10 h-10 text-blue-400 dark:text-blue-700" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6 5.87V4m0 0a4 4 0 11-8 0 4 4 0 018 0z" /></svg>
                        </div>
                        <div class="flex items-center justify-center w-12 h-12 rounded-full bg-blue-100 dark:bg-blue-800 mb-2">
                            <svg class="w-7 h-7 text-blue-600 dark:text-blue-200" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6 5.87V4m0 0a4 4 0 11-8 0 4 4 0 018 0z" /></svg>
                        </div>
                        <div class="text-3xl font-black text-blue-900 dark:text-blue-200">{{ $totalGrupos }}</div>
                        <div class="text-sm text-blue-700 dark:text-blue-300 font-semibold mt-1">Total de Grupos</div>
                    </div>
                    <!-- Total de Clientes -->
                    <div class="relative bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl shadow-lg p-4 flex flex-col items-center group transition-transform hover:-translate-y-1 hover:shadow-2xl duration-200">
                        <div class="absolute top-2 right-2 opacity-10 group-hover:opacity-20 text-5xl pointer-events-none select-none">
                            <svg class="w-10 h-10 text-indigo-400 dark:text-indigo-700" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6 5.87V4m0 0a4 4 0 11-8 0 4 4 0 018 0z" /></svg>
                        </div>
                        <div class="flex items-center justify-center w-12 h-12 rounded-full bg-indigo-100 dark:bg-indigo-800 mb-2">
                            <svg class="w-7 h-7 text-indigo-600 dark:text-indigo-200" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6 5.87V4m0 0a4 4 0 11-8 0 4 4 0 018 0z" /></svg>
                        </div>
                        <div class="text-3xl font-black text-indigo-900 dark:text-indigo-200">{{ $totalClientes ?? '0' }}</div>
                        <div class="text-sm text-indigo-700 dark:text-indigo-300 font-semibold mt-1">Total de Clientes</div>
                    </div>
                    <!-- Total de Préstamos -->
                    <div class="relative bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl shadow-lg p-4 flex flex-col items-center group transition-transform hover:-translate-y-1 hover:shadow-2xl duration-200">
                        <div class="absolute top-2 right-2 opacity-10 group-hover:opacity-20 text-5xl pointer-events-none select-none">
                            <svg class="w-10 h-10 text-green-400 dark:text-green-700" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                        </div>
                        <div class="flex items-center justify-center w-12 h-12 rounded-full bg-green-100 dark:bg-green-800 mb-2">
                            <svg class="w-7 h-7 text-green-600 dark:text-green-200" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                        </div>
                        <div class="text-3xl font-black text-amber-900 dark:text-amber-200">{{ $totalPrestamos ?? '0' }}</div>
                        <div class="text-sm text-green-700 dark:text-green-300 font-semibold mt-1">Total de Préstamos</div>
                    </div>
                    <!-- Total de Retanqueos -->
                    <div class="relative bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl shadow-lg p-4 flex flex-col items-center group transition-transform hover:-translate-y-1 hover:shadow-2xl duration-200">
                        <div class="absolute top-2 right-2 opacity-10 group-hover:opacity-20 text-5xl pointer-events-none select-none">
                            <svg class="w-10 h-10 text-yellow-400 dark:text-yellow-700" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3" /></svg>
                        </div>
                        <div class="flex items-center justify-center w-12 h-12 rounded-full bg-yellow-100 dark:bg-yellow-800 mb-2">
                            <svg class="w-7 h-7 text-yellow-600 dark:text-yellow-200" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3" /></svg>
                        </div>
                        <div class="text-3xl font-black text-yellow-900 dark:text-yellow-200">{{ $totalRetanqueos ?? '0' }}</div>
                        <div class="text-sm text-yellow-700 dark:text-yellow-300 font-semibold mt-1">Total de Retanqueos</div>
                    </div>
                    <!-- Total de Pagos Registrados -->
                    <div class="relative bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl shadow-lg p-4 flex flex-col items-center group transition-transform hover:-translate-y-1 hover:shadow-2xl duration-200">
                        <div class="absolute top-2 right-2 opacity-10 group-hover:opacity-20 text-5xl pointer-events-none select-none">
                            <svg class="w-10 h-10 text-gray-400 dark:text-gray-700" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                        </div>
                        <div class="flex items-center justify-center w-12 h-12 rounded-full bg-gray-100 dark:bg-gray-800 mb-2">
                            <svg class="w-7 h-7 text-gray-600 dark:text-gray-200" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                        </div>
                        <div class="text-3xl font-black text-yellow-900 dark:text-yellow-200">{{ $totalPagosRegistrados ?? '0' }}</div>
                        <div class="text-sm text-gray-700 dark:text-gray-300 font-semibold mt-1">Total de Pagos Registrados</div>
                    </div>
                    <!-- Total de Moras Históricas -->
                    <div class="relative bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl shadow-lg p-4 flex flex-col items-center group transition-transform hover:-translate-y-1 hover:shadow-2xl duration-200">
                        <div class="absolute top-2 right-2 opacity-10 group-hover:opacity-20 text-5xl pointer-events-none select-none">
                            <svg class="w-10 h-10 text-amber-400 dark:text-amber-700" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3" /></svg>
                        </div>
                        <div class="flex items-center justify-center w-12 h-12 rounded-full bg-amber-100 dark:bg-amber-800 mb-2">
                            <svg class="w-7 h-7 text-amber-600 dark:text-amber-200" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3" /></svg>
                        </div>
                        <div class="text-3xl font-black text-amber-900 dark:text-amber-200">{{ $totalMorasHistoricas ?? '0' }}</div>
                        <div class="text-sm text-amber-700 dark:text-amber-300 font-semibold mt-1">Total de Moras Históricas</div>
                    </div>
                </div>
            </div>
            <!-- Moras y Pagos juntos -->
            <div>
                <div class="grid grid-cols-2 gap-2">
                    <!-- Moras -->
                    <div>
                        <h2 class="text-2xl font-extrabold text-black mb-6">Moras</h2>
                        <div class="grid grid-cols-1 gap-2">
                            <div class="bg-gradient-to-br from-red-100 to-red-300 dark:from-red-900 dark:to-red-700 rounded-lg shadow p-2 flex flex-col items-center justify-center min-h-0">
                                <div class="flex items-center justify-center w-8 h-8 rounded-full bg-white/80 dark:bg-red-800 mb-1">
                                    <svg class="w-5 h-5 text-red-600 dark:text-red-200" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M18.364 5.636l-1.414 1.414M6.343 17.657l-1.414 1.414M12 8v4l3 3m6 0a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                </div>
                                <div class="text-xs text-yellow-900 dark:text-yellow-100 font-semibold mt-0.5">{{ $cuotasEnMora }}</div>
                                <div class="text-xs text-red-900 dark:text-red-100 font-semibold mt-0.5">Cuotas en Mora</div>
                            </div>
                            <div class="bg-gradient-to-br from-pink-100 to-pink-300 dark:from-pink-900 dark:to-pink-700 rounded-lg shadow p-2 flex flex-col items-center justify-center min-h-0">
                                <div class="flex items-center justify-center w-8 h-8 rounded-full bg-white/80 dark:bg-pink-800 mb-1">
                                    <svg class="w-5 h-5 text-pink-600 dark:text-pink-200" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 1.343-3 3s1.343 3 3 3 3-1.343 3-3-1.343-3-3-3zm0 0V4m0 8v8m8-8h-8" /></svg>
                                </div>
                                <div class="text-lg font-extrabold text-pink-700 dark:text-pink-200">S/ {{ number_format($montoTotalMora, 2) }}</div>
                                <div class="text-xs text-pink-900 dark:text-pink-100 font-semibold mt-0.5">Monto Total en Mora</div>
                            </div>
                        </div>
                    </div>
                    <!-- Pagos -->
                    <div>
                        <h2 class="text-2xl font-extrabold text-black mb-6">Pagos</h2>
                        <div class="grid grid-cols-1 gap-2">
                            <div class="bg-gradient-to-br from-green-100 to-green-300 dark:from-green-900 dark:to-green-700 rounded-lg shadow p-2 flex flex-col items-center justify-center min-h-0">
                                <div class="flex items-center justify-center w-8 h-8 rounded-full bg-white/80 dark:bg-green-800 mb-1">
                                    <svg class="w-5 h-5 text-green-600 dark:text-green-200" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                                </div>
                                <div class="text-xs text-yellow-900 dark:text-yellow-100 font-semibold mt-0.5">{{ $pagosAprobados }}</div>
                                <div class="text-xs text-yellow-900 dark:text-yellow-100 font-semibold mt-0.5">Aprobados</div>
                            </div>
                            <div class="bg-gradient-to-br from-yellow-100 to-yellow-300 dark:from-yellow-900 dark:to-yellow-700 rounded-lg shadow p-2 flex flex-col items-center justify-center min-h-0">
                                <div class="flex items-center justify-center w-8 h-8 rounded-full bg-white/80 dark:bg-yellow-800 mb-1">
                                    <svg class="w-5 h-5 text-yellow-600 dark:text-yellow-200" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3" /></svg>
                                </div>
                                <div class="text-xs text-yellow-900 dark:text-yellow-100 font-semibold mt-0.5">{{ $pagosPendientes }}</div>
                                <div class="text-xs text-yellow-900 dark:text-yellow-100 font-semibold mt-0.5">Pendientes</div>
                            </div>
                            <div class="bg-gradient-to-br from-gray-100 to-gray-300 dark:from-gray-900 dark:to-gray-700 rounded-lg shadow p-2 flex flex-col items-center justify-center min-h-0">
                                <div class="flex items-center justify-center w-8 h-8 rounded-full bg-white/80 dark:bg-gray-800 mb-1">
                                    <svg class="w-5 h-5 text-gray-600 dark:text-gray-200" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                                </div>
                                <div class="text-xs text-yellow-900 dark:text-yellow-100 font-semibold mt-0.5">{{ $pagosRechazados }}</div>
                                <div class="text-xs text-yellow-900 dark:text-yellow-100 font-semibold mt-0.5">Rechazados</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-6">
    <!-- Columna 1: Lista de grupos y gráficos -->
    <div class="grid grid-cols-1 gap-4">
        <!-- Lista de grupos en mora -->
        <div class="bg-white rounded-xl shadow p-4">
            <h2 class="text-lg font-extrabold text-black mb-6">Lista de grupos en mora</h2>
            <div class="overflow-x-auto tabla-con-scroll">
                <table class="w-full bg-white rounded-lg overflow-hidden shadow text-sm leading-tight">
                    <thead class="bg-gray-200 text-black text-center">
                        <tr>
                            <th class="px-4 py-3 font-semibold border-b border-gray-300">Grupo</th>
                            <th class="px-4 py-3 font-semibold border-b border-gray-300">Integrantes</th>
                            <th class="px-4 py-3 font-semibold border-b border-gray-300">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($grupos as $grupo)
                            <tr class="text-center border-b border-gray-200 hover:bg-gray-100">
                                <td class="px-4 py-3 text-black">{{ $grupo['nombre'] }}</td>
                                <td class="px-4 py-3 text-black">{{ $grupo['numero_integrantes'] }}</td>
                                <td class="px-4 py-3 text-black">{{ $grupo['estado'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Estado de Cuotas -->
        <div class="bg-white dark:bg-gray-900 rounded-xl shadow p-4">
            <h2 class="text-lg font-extrabold text-black mb-6">Estado de Cuotas</h2>
            <div class="h-64">
                <canvas id="barCuotasEstados" height="250"></canvas>
            </div>
        </div>
    </div>

    <!-- Columna 2: Evolución de Pagos Registrados y Distribución de Pagos -->
    <div class="grid grid-cols-1 gap-4">
        <!-- Evolución de Pagos Registrados -->
        <div class="bg-white dark:bg-gray-900 rounded-xl shadow p-4">
            <h2 class="text-lg font-extrabold text-black mb-6">Evolución de Pagos Registrados</h2>
            <div class="h-64">
                <canvas id="linePagosEvolucion" height="250"></canvas>
            </div>
        </div>

        <!-- Distribución de Pagos por Estado -->
        <div class="bg-white dark:bg-gray-900 rounded-xl shadow p-4">
            <h2 class="text-lg font-extrabold text-black mb-6">Distribución de Pagos por Estado</h2>
            <div class="h-64">
                <canvas id="piePagosEstado" height="250"></canvas>
            </div>
        </div>
    </div>
</div>

<style>
    .tabla-con-scroll {
        max-height: 400px; /* Ajusta la altura máxima según sea necesario */
        overflow-y: auto;
    }
</style>


   <script>
        // Función para detectar el tema actual
        function getTheme() {
            return document.documentElement.classList.contains('dark') ? 'dark' : 'light';
        }

        // Configuración común para los gráficos con mejor contraste
        const chartOptions = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    labels: {
                        color: getTheme() === 'dark' ? '#f3f4f6' : '#111827',
                        font: {
                            size: 12,
                            weight: 'bold'
                        }
                    }
                },
                tooltip: {
                    enabled: true,
                    mode: 'index',
                    intersect: false,
                    backgroundColor: getTheme() === 'dark' ? '#1f2937' : '#ffffff',
                    titleColor: getTheme() === 'dark' ? '#f3f4f6' : '#111827',
                    bodyColor: getTheme() === 'dark' ? '#f3f4f6' : '#111827',
                    borderColor: getTheme() === 'dark' ? '#4b5563' : '#d1d5db',
                    borderWidth: 1,
                    titleFont: {
                        size: 12,
                        weight: 'bold'
                    },
                    bodyFont: {
                        size: 12
                    }
                }
            },
            scales: {
                x: {
                    grid: {
                        color: getTheme() === 'dark' ? 'rgba(255, 255, 255, 0.1)' : 'rgba(0, 0, 0, 0.05)',
                        drawBorder: false
                    },
                    ticks: {
                        color: getTheme() === 'dark' ? '#d1d5db' : '#4b5563',
                        font: {
                            size: 11,
                            weight: 'bold'
                        }
                    }
                },
                y: {
                    grid: {
                        color: getTheme() === 'dark' ? 'rgba(255, 255, 255, 0.1)' : 'rgba(0, 0, 0, 0.05)',
                        drawBorder: false
                    },
                    ticks: {
                        color: getTheme() === 'dark' ? '#d1d5db' : '#4b5563',
                        font: {
                            size: 11,
                            weight: 'bold'
                        },
                        // CORREGIDO: Forzar números enteros en el eje Y
                        stepSize: 1,
                        callback: function(value) {
                            return Number.isInteger(value) ? value : '';
                        }
                    }
                }
            }
        };

        // 1. Gráfico de barras: Estado de cuotas
        const barChart = new Chart(document.getElementById('barCuotasEstados'), {
            type: 'bar',
            data: {
                labels: @json(array_keys($cuotasEstadosBarArr)),
                datasets: [{
                    label: 'Cantidad',
                    data: @json(array_values($cuotasEstadosBarArr)),
                        backgroundColor: [
                            'rgba(253, 224, 71, 0.8)',
                             'rgba(239, 68, 68, 0.8)',
                            'rgba(34, 197, 94, 0.8)',
                        ],
                        borderColor: [
                            'rgba(253, 224, 71, 1)',
                             'rgba(239, 68, 68, 1)',
                            'rgba(34, 197, 94, 1)'

                        ],


                    borderWidth: 1
                }]
            },
            options: {
                ...chartOptions,
                plugins: {
                    ...chartOptions.plugins,
                    legend: {
                        display: false
                    },
                    title: {
                        display: true,
                        color: getTheme() === 'dark' ? '#f3f4f6' : '#111827',
                        font: {
                            size: 14,
                            weight: 'bold'
                        }
                    }
                }
            }
        });

        // 2. CORREGIDO: Gráfico de línea con configuración específica para montos
        const lineChart = new Chart(document.getElementById('linePagosEvolucion'), {
            type: 'line',
            data: {
                labels: @json($pagosPorFechaLabels),
                datasets: [{
                    label: 'Monto total pagado (S/)', // CORREGIDO: Label más claro
                    data: @json($pagosPorFechaVals),
                    fill: false,
                    borderColor: 'rgba(34, 197, 94, 1)',
                    backgroundColor: 'rgba(34, 197, 94, 0.2)',
                    tension: 0.3,
                    borderWidth: 3,
                    pointBackgroundColor: 'rgba(34, 197, 94, 1)',
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    pointBorderColor: '#fff'
                }]
            },
            options: {
                ...chartOptions,
                scales: {
                    ...chartOptions.scales,
                    y: {
                        ...chartOptions.scales.y,
                        ticks: {
                            ...chartOptions.scales.y.ticks,
                            // CORREGIDO: Configuración específica para montos (permitir decimales pero formatear)
                            callback: function(value) {
                                return 'S/ ' + value.toLocaleString('es-PE', {
                                    minimumFractionDigits: 0,
                                    maximumFractionDigits: 2
                                });
                            }
                        }
                    }
                },
                plugins: {
                    ...chartOptions.plugins,
                    legend: {
                        ...chartOptions.plugins.legend,
                        position: 'top',
                        labels: {
                            ...chartOptions.plugins.legend.labels,
                            usePointStyle: true,
                            pointStyle: 'circle',
                            padding: 20
                        }
                    },
                    tooltip: {
                        ...chartOptions.plugins.tooltip,
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': S/ ' + context.parsed.y.toLocaleString('es-PE', {
                                    minimumFractionDigits: 2,
                                    maximumFractionDigits: 2
                                });
                            }
                        }
                    }
                }
            }
        });

        // 3. CORREGIDO: Gráfico circular con números enteros
        const pieChart = new Chart(document.getElementById('piePagosEstado'), {
            type: 'pie',
            data: {
                labels: @json(array_keys($pagosPieArr)),
                datasets: [{
                    data: @json(array_values($pagosPieArr)),
                    backgroundColor: [
                        'rgba(34, 197, 94, 0.8)',  // Aprobados - verde
                        'rgba(253, 224, 71, 0.8)', // Pendientes - amarillo
                        'rgba(107, 114, 128, 0.8)' // Rechazados - gris
                    ],
                    borderColor: getTheme() === 'dark' ? 'rgba(75, 85, 99, 0.5)' : 'rgba(209, 213, 219, 0.5)',
                    borderWidth: 1
                }]
            },
            options: {
                ...chartOptions,
                plugins: {
                    ...chartOptions.plugins,
                    legend: {
                        ...chartOptions.plugins.legend,
                        position: 'bottom',
                        labels: {
                            ...chartOptions.plugins.legend.labels,
                            padding: 15,
                            boxWidth: 12
                        }
                    },
                    tooltip: {
                        ...chartOptions.plugins.tooltip,
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.parsed;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((value / total) * 100).toFixed(1);
                                return label + ': ' + value + ' (' + percentage + '%)';
                            }
                        }
                    }
                }
            }
        });

        // Escuchar cambios de tema y actualizar gráficos
        const observer = new MutationObserver(() => {
            const theme = getTheme();
            const textColor = theme === 'dark' ? '#f3f4f6' : '#111827';
            const gridColor = theme === 'dark' ? 'rgba(255, 255, 255, 0.1)' : 'rgba(0, 0, 0, 0.05)';

            // Actualizar opciones de todos los gráficos
            [barChart, lineChart, pieChart].forEach(chart => {
                if (chart.options.scales) {
                    chart.options.scales.x.ticks.color = textColor;
                    chart.options.scales.y.ticks.color = textColor;
                    chart.options.scales.x.grid.color = gridColor;
                    chart.options.scales.y.grid.color = gridColor;
                }
                chart.options.plugins.legend.labels.color = textColor;
                chart.update();
            });
        });

        observer.observe(document.documentElement, {
            attributes: true,
            attributeFilter: ['class']
        });
    </script>
</div>
</x-filament-panels::page>
