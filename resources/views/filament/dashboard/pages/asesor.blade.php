<x-filament-panels::page>
    {{-- KPI Strip (5 cells) --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-3 mb-4">
        @foreach([
            ['label' => 'Grupos',      'value' => $this->cartera['grupos_count'] ?? 0,    'color' => 'text-gray-900'],
            ['label' => 'Clientes',    'value' => $this->cartera['clientes_count'] ?? 0,  'color' => 'text-gray-900'],
            ['label' => 'Préstamos',   'value' => $this->cartera['prestamos_count'] ?? 0, 'color' => 'text-gray-900'],
            ['label' => 'Cartera',     'value' => 'S/ ' . number_format(($this->cartera['cartera_total'] ?? 0) / 1000, 1) . 'K', 'color' => 'text-gray-900'],
            ['label' => 'Cobrado hoy', 'value' => 'S/ ' . number_format($this->cobradoHoy, 2), 'color' => 'text-green-600'],
        ] as $kpi)
        <div class="bg-white rounded-lg border border-gray-200 px-4 py-3">
            <p class="text-xs text-gray-500 mb-0.5">{{ $kpi['label'] }}</p>
            <p class="text-lg font-bold {{ $kpi['color'] }}">{{ $kpi['value'] }}</p>
        </div>
        @endforeach
    </div>

    {{-- Tabbed card --}}
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        {{-- Tab bar --}}
        <div class="flex border-b border-gray-200">
            @foreach([
                ['key' => 'hoy',          'label' => 'Hoy',          'badge' => $this->cuotasHoyCount],
                ['key' => 'agenda',       'label' => 'Agenda',       'badge' => null],
                ['key' => 'seguimientos', 'label' => 'Seguimientos', 'badge' => null],
            ] as $tab)
            <button
                wire:click="setTab('{{ $tab['key'] }}')"
                class="px-5 py-3 text-sm font-medium border-b-2 transition-colors
                    {{ $this->activeTab === $tab['key']
                        ? 'border-primary-600 text-primary-600'
                        : 'border-transparent text-gray-500 hover:text-gray-700' }}"
            >
                {{ $tab['label'] }}
                @if($tab['badge'] !== null)
                    <span class="ml-1.5 bg-danger-100 text-danger-700 text-xs font-semibold px-1.5 py-0.5 rounded-full">
                        {{ $tab['badge'] }}
                    </span>
                @endif
            </button>
            @endforeach
        </div>

        {{-- Tab: Hoy --}}
        @if($this->activeTab === 'hoy')
        <div class="p-4">
            <p class="text-xs text-gray-500 mb-3">Cuotas del día · ordenadas por urgencia</p>
            <div class="overflow-x-auto">
                <table class="w-full text-xs">
                    <thead>
                        <tr class="border-b border-gray-100 text-gray-500">
                            <th class="text-left py-2 px-2 font-medium">Cliente</th>
                            <th class="text-left py-2 px-2 font-medium">Préstamo</th>
                            <th class="text-center py-2 px-2 font-medium">Cuota</th>
                            <th class="text-right py-2 px-2 font-medium">Monto</th>
                            <th class="text-center py-2 px-2 font-medium">Vence</th>
                            <th class="text-center py-2 px-2 font-medium">Ciclo</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @forelse(($this->cartera['cuotas_hoy'] ?? collect())->take(20) as $cuota)
                        @php
                            $cliente  = $cuota->prestamo?->cliente;
                            $persona  = $cliente?->persona;
                            $nombre   = $persona
                                ? ($persona->nombres . ' ' . $persona->apellido_paterno)
                                : 'N/A';
                            $parts    = explode(' ', $nombre, 2);
                            $initials = strtoupper(
                                substr($parts[0], 0, 1) .
                                (isset($parts[1]) ? substr($parts[1], 0, 1) : '')
                            );
                            $grupo    = $cuota->prestamo?->grupo?->nombre ?? '—';
                            $isToday  = $cuota->fecha_vencimiento->isToday();
                            $totalCuotas = $cuota->prestamo?->cuotasIndividuales?->count() ?? '?';
                        @endphp
                        <tr class="{{ $isToday ? 'bg-danger-50' : '' }} h-8">
                            <td class="py-1 px-2">
                                <div class="flex items-center gap-2">
                                    <div class="w-6 h-6 rounded-full bg-primary-100 text-primary-700 text-xs font-bold flex items-center justify-center flex-shrink-0">
                                        {{ $initials }}
                                    </div>
                                    <div>
                                        <p class="font-medium text-gray-900 leading-none">{{ $nombre }}</p>
                                        <p class="text-gray-400 text-xs leading-none mt-0.5">{{ $grupo }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="py-1 px-2 text-gray-600 font-mono">
                                #{{ $cuota->prestamo?->id ?? '—' }}
                            </td>
                            <td class="py-1 px-2 text-center text-gray-600">
                                {{ $cuota->numero_cuota }}/{{ $totalCuotas }}
                            </td>
                            <td class="py-1 px-2 text-right font-medium text-gray-900">
                                S/ {{ number_format($cuota->saldo_capital, 2) }}
                            </td>
                            <td class="py-1 px-2 text-center">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                                    {{ $isToday ? 'bg-danger-100 text-danger-700' : 'bg-warning-100 text-warning-700' }}">
                                    {{ $isToday ? 'Hoy' : $cuota->fecha_vencimiento->format('d/m') }}
                                </span>
                            </td>
                            <td class="py-1 px-2 text-center">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700">
                                    {{ $cliente?->ciclo_romano ?? $cliente?->ciclo ?? '—' }}
                                </span>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="py-8 text-center text-gray-400 text-sm">
                                No hay cuotas para hoy
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        {{-- Tab: Agenda (placeholder) --}}
        @if($this->activeTab === 'agenda')
        <div class="p-8 text-center">
            <div class="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-3">
                <x-heroicon-o-clock class="w-6 h-6 text-gray-400" />
            </div>
            <p class="text-sm font-medium text-gray-600">Próximamente</p>
            <p class="text-xs text-gray-400 mt-1">La agenda de actividades estará disponible en una próxima versión</p>
        </div>
        @endif

        {{-- Tab: Seguimientos (placeholder) --}}
        @if($this->activeTab === 'seguimientos')
        <div class="p-8 text-center">
            <div class="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-3">
                <x-heroicon-o-clock class="w-6 h-6 text-gray-400" />
            </div>
            <p class="text-sm font-medium text-gray-600">Próximamente</p>
            <p class="text-xs text-gray-400 mt-1">Los seguimientos de clientes estarán disponibles en una próxima versión</p>
        </div>
        @endif
    </div>
</x-filament-panels::page>
