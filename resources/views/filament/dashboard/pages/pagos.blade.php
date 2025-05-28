<x-filament::page>
    <div class="w-full overflow-x-auto bg-gradient-to-br from-purple-50 via-white to-purple-100 dark:from-gray-900 dark:via-gray-800 dark:to-gray-900 rounded-xl px-4 sm:px-8 py-6 shadow-lg border border-purple-100 dark:border-gray-700">
        <form method="GET" action="{{ route('pagos.exportar.pdf') }}" class="mb-6 flex flex-wrap gap-4 items-end bg-white dark:bg-gray-800 rounded-xl shadow px-6 py-4 border border-purple-100 dark:border-gray-700" id="filtros-pagos-form">
            <div class="flex flex-col w-56 mr-2">
                <label class="block text-xs font-bold mb-1 text-purple-900 dark:text-purple-200">Filtrar por grupo <span class="text-xs text-purple-600 dark:text-purple-300 font-normal">(elige un grupo disponible)</span></label>
                <select name="grupo" class="rounded-lg border-2 border-purple-500 bg-purple-50 dark:bg-purple-900 px-3 py-2 text-base font-semibold focus:ring-2 focus:ring-purple-400 focus:border-purple-600 dark:text-white transition">
                    <option value="">Todos</option>
                    @foreach(\App\Models\Grupo::orderBy('nombre_grupo')->pluck('nombre_grupo') as $nombreGrupo)
                        <option value="{{ $nombreGrupo }}" {{ request('grupo') == $nombreGrupo ? 'selected' : '' }}>{{ $nombreGrupo }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex flex-col w-36">
                <label class="block text-xs font-semibold mb-1 text-purple-700 dark:text-purple-200">Desde</label>
                <input type="date" name="from" value="{{ request('from') }}" class="rounded-lg border border-purple-200 dark:border-purple-500 px-3 py-2 text-sm focus:ring-2 focus:ring-purple-400 focus:border-purple-400 dark:bg-gray-900 dark:text-white transition">
            </div>
            <div class="flex flex-col w-36">
                <label class="block text-xs font-semibold mb-1 text-purple-700 dark:text-purple-200">Hasta</label>
                <input type="date" name="until" value="{{ request('until') }}" class="rounded-lg border border-purple-200 dark:border-purple-500 px-3 py-2 text-sm focus:ring-2 focus:ring-purple-400 focus:border-purple-400 dark:bg-gray-900 dark:text-white transition">
            </div>
            <div class="flex flex-col w-40">
                <label class="block text-xs font-semibold mb-1 text-purple-700 dark:text-purple-200">Estado de pago</label>
                <select name="estado_pago" class="rounded-lg border border-purple-200 dark:border-purple-500 px-3 py-2 text-sm focus:ring-2 focus:ring-purple-400 focus:border-purple-400 dark:bg-gray-900 dark:text-white transition">
                    <option value="">Todos</option>
                    <option value="Pendiente" {{ request('estado_pago') == 'Pendiente' ? 'selected' : '' }}>Pendiente</option>
                    <option value="Aprobado" {{ request('estado_pago') == 'Aprobado' ? 'selected' : '' }}>Aprobado</option>
                    <option value="Rechazado" {{ request('estado_pago') == 'Rechazado' ? 'selected' : '' }}>Rechazado</option>
                </select>
            </div>
            <button type="submit" class="inline-flex items-center gap-2 px-6 py-2 bg-gradient-to-r from-purple-200 to-purple-400 hover:from-purple-300 hover:to-purple-500 text-black text-base font-bold rounded-xl shadow-lg transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-purple-400 focus:ring-offset-2 dark:focus:ring-offset-gray-900 border border-purple-400 dark:border-purple-600 ml-4">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
                Exportar PDF
            </button>
        </form>
        <!-- Aquí podrías mostrar la tabla de pagos si lo deseas -->
    </div>
</x-filament::page>
