<x-filament-panels::page>

<link href="https://cdn.jsdelivr.net/npm/flowbite@3.1.2/dist/flowbite.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/flowbite@3.1.2/dist/flowbite.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
    /* Definimos colores para modo claro y oscuro */
    .text-adaptive {
        color: #111; /* color oscuro para modo claro */
    }
    .dark .text-adaptive {
        color: #e5e7eb; /* color claro para modo oscuro (gray-200) */
    }
</style>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

    <!-- Tarjeta de Asesor -->
    <div class="p-6 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl shadow transition hover:shadow-lg">
        <div class="flex items-center justify-between mb-4">
            <h5 class="text-2xl font-bold text-adaptive">Resumen del Asesor</h5>
            <span class="inline-flex items-center px-2 py-1 text-xs font-medium text-white bg-blue-600 rounded-full">ID #123</span>
        </div>
        <ul class="space-y-1">
            <li><strong class="text-adaptive">Nombre:</strong> Tania Cardoza</li>
            <li><strong class="text-adaptive">Zona:</strong> Marcavelica</li>
            <li><strong class="text-adaptive">Total de Grupos:</strong> 5</li>
        </ul>
        <div class="mt-5">
            <a href="#"
               class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-blue-700 hover:bg-blue-800 rounded-lg focus:ring-4 focus:outline-none focus:ring-blue-300 dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800 transition">
                Ver detalles
                <svg class="w-4 h-4 ml-2 rtl:rotate-180" fill="none" stroke="currentColor" stroke-width="2"
                     viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"></path>
                </svg>
            </a>
        </div>
    </div>

    <!-- Tarjeta del Gráfico -->
    <div class="p-6 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl shadow transition hover:shadow-lg">
        <h5 class="mb-4 text-xl font-semibold text-adaptive">Estado de Préstamos por Grupo</h5>
        <canvas id="loanStatusChart" class="w-full h-auto"></canvas>
    </div>

    <!-- Tabla de Grupos -->
    <div class="md:col-span-2">
        <div class="overflow-x-auto rounded-xl shadow">
            <table class="min-w-full text-sm text-left bg-white dark:bg-gray-900 text-adaptive">
                <thead class="bg-gray-100 dark:bg-gray-800">
                    <tr>
                        <th class="px-6 py-3 font-semibold text-adaptive">#</th>
                        <th class="px-6 py-3 font-semibold text-adaptive">Nombre del Grupo</th>
                        <th class="px-6 py-3 font-semibold text-adaptive">Clientes</th>
                        <th class="px-6 py-3 font-semibold text-adaptive">Estado</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ([
                        ['id' => 1, 'nombre' => 'Grupo Esperanza', 'clientes' => 5, 'estado' => 'Activo'],
                        ['id' => 2, 'nombre' => 'Los Emprendedores', 'clientes' => 4, 'estado' => 'Mora'],
                        ['id' => 3, 'nombre' => 'Mujeres Unidas', 'clientes' => 3, 'estado' => 'Activo'],
                        ['id' => 4, 'nombre' => 'Fuerza Andina', 'clientes' => 5, 'estado' => 'Finalizado'],
                        ['id' => 5, 'nombre' => 'Avance Seguro', 'clientes' => 4, 'estado' => 'Activo'],
                    ] as $grupo)
                    <tr class="border-b border-gray-200 dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-700 transition">
                        <td class="px-6 py-4 text-adaptive">{{ $grupo['id'] }}</td>
                        <td class="px-6 py-4 text-adaptive">{{ $grupo['nombre'] }}</td>
                        <td class="px-6 py-4 text-adaptive">{{ $grupo['clientes'] }}</td>
                        <td class="px-6 py-4">
                            <span class="inline-flex items-center px-2 py-1 text-xs font-semibold rounded-full
                                @if($grupo['estado'] === 'Activo') bg-green-600 text-white
                                @elseif($grupo['estado'] === 'Mora') bg-red-600 text-white
                                @else bg-gray-500 text-white @endif">
                                {{ $grupo['estado'] }}
                            </span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

</div>

<script>
    const ctx = document.getElementById('loanStatusChart').getContext('2d');
    const isDark = document.documentElement.classList.contains('dark');

    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Activo', 'Mora', 'Finalizado'],
            datasets: [{
                label: 'Estado de préstamos',
                data: [3, 1, 1],
                backgroundColor: [
                    'rgb(34,197,94)',   // verde
                    'rgb(239,68,68)',   // rojo
                    'rgb(107,114,128)'  // gris
                ],
                borderColor: ['#fff'],
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    labels: {
                        color: isDark ? '#fff' : '#000',
                        padding: 20,
                        usePointStyle: true
                    },
                    position: 'bottom'
                }
            }
        }
    });
</script>

</x-filament-panels::page>
