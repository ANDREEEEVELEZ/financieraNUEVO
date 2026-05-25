<x-filament-panels::page>
    {{-- MetaHeroStrip: sticky 44px bar with cobrado / meta / progress / % chip --}}
    <div style="position:sticky; top:64px; z-index:10; height:44px;"
         class="bg-primary-50 border-b border-primary-200 flex items-center px-4 gap-4 mb-4">
        <span class="font-mono font-bold text-gray-900">
            S/ {{ number_format($meta['cobrado'] ?? 0, 0, '.', ',') }}
        </span>
        <span class="text-gray-400">/</span>
        <span class="text-gray-500">
            S/ {{ number_format($meta['meta'] ?? 0, 0, '.', ',') }}
        </span>
        <div class="flex-1 bg-gray-200 rounded-full h-1.5 mx-2 max-w-xs">
            <div class="h-1.5 rounded-full {{ ($meta['pct'] ?? 0) >= 100 ? 'bg-green-500' : (($meta['pct'] ?? 0) >= 70 ? 'bg-yellow-500' : 'bg-red-500') }}"
                 style="width: {{ min($meta['pct'] ?? 0, 100) }}%"></div>
        </div>
        <span class="text-xs font-semibold px-2 py-0.5 rounded-full
            {{ ($meta['pct'] ?? 0) >= 100 ? 'bg-green-100 text-green-700' : (($meta['pct'] ?? 0) >= 70 ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700') }}">
            {{ $meta['pct'] ?? 0 }}%
        </span>
        <span class="text-xs text-gray-500 hidden sm:inline">
            Meta cobranza · {{ now()->translatedFormat('F Y') }}
        </span>
    </div>

    {{-- Period selector pills --}}
    <div class="flex gap-2 mb-4">
        @foreach(['hoy' => 'Hoy', 'semana' => 'Semana', 'mes' => 'Mes'] as $key => $label)
        <button wire:click="setPeriod('{{ $key }}')"
            class="px-3 py-1.5 text-xs font-medium rounded-full transition-colors
                {{ $period === $key
                    ? 'bg-primary-600 text-white'
                    : 'bg-white text-gray-600 border border-gray-200 hover:border-primary-300' }}">
            {{ $label }}
        </button>
        @endforeach
        @foreach(['trim' => 'Trim.', 'anio' => 'Año'] as $key => $label)
        <button title="Próximamente"
            class="px-3 py-1.5 text-xs font-medium rounded-full bg-gray-50 text-gray-400 border border-gray-100 cursor-not-allowed"
            disabled>
            {{ $label }}
        </button>
        @endforeach
    </div>

    {{-- 5-tab card --}}
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">

        {{-- Tab navigation bar — order driven by role via $tabOrder --}}
        @php
            $tabLabels = [
                'cobranza'    => 'Cobranza',
                'colocacion'  => 'Colocación',
                'cartera'     => 'Cartera',
                'pagos'       => 'Pagos',
                'operaciones' => 'Operaciones',
            ];
        @endphp
        <div class="flex border-b border-gray-200 overflow-x-auto">
            @foreach($tabOrder as $tabKey)
            <button wire:click="setTab('{{ $tabKey }}')"
                class="px-4 py-3 text-sm font-medium border-b-2 transition-colors whitespace-nowrap
                    {{ $activeTab === $tabKey
                        ? 'border-primary-600 text-primary-600'
                        : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                {{ $tabLabels[$tabKey] }}
            </button>
            @endforeach
        </div>

        {{-- COBRANZA TAB --}}
        @if($activeTab === 'cobranza')
        @php
            $d   = $this->getCobranzaData();
            $pct = $meta['pct'] ?? 0;
        @endphp
        <div class="p-4 space-y-4">
            {{-- 5 KPI cells --}}
            <div class="grid grid-cols-5 gap-3">
                <div class="bg-gray-50 rounded-lg px-3 py-2">
                    <p class="text-xs text-gray-500">Cobrado</p>
                    <p class="text-base font-bold text-gray-900">S/ {{ number_format(($meta['cobrado'] ?? 0) / 1000, 1) }}K</p>
                </div>
                <div class="bg-gray-50 rounded-lg px-3 py-2">
                    <p class="text-xs text-gray-500">Meta</p>
                    <p class="text-base font-bold text-gray-900">S/ {{ number_format(($meta['meta'] ?? 0) / 1000, 1) }}K</p>
                </div>
                <div class="bg-gray-50 rounded-lg px-3 py-2">
                    <p class="text-xs text-gray-500">% Recuperación</p>
                    <p class="text-base font-bold text-gray-900">{{ $pct }}%</p>
                </div>
                <div class="bg-gray-50 rounded-lg px-3 py-2">
                    <p class="text-xs text-gray-500">% Eficiencia</p>
                    <p class="text-base font-bold text-gray-900">{{ $d['eficiencia'] ?? 0 }}%</p>
                </div>
                <div class="bg-gray-50 rounded-lg px-3 py-2">
                    <p class="text-xs text-gray-500">PAR 30</p>
                    <p class="text-base font-bold {{ ($d['par30'] ?? 0) > 5 ? 'text-warning-600' : 'text-gray-900' }}">
                        {{ $d['par30'] ?? 0 }}%
                    </p>
                </div>
            </div>

            {{-- Semáforo de mora --}}
            <div class="border border-gray-200 rounded-lg p-3">
                <p class="text-xs font-medium text-gray-600 mb-2">Semáforo de mora</p>
                <div class="grid grid-cols-5 gap-2">
                    @php
                        $bucketColors = [
                            'green'    => 'bg-green-50 text-green-700 border-green-200',
                            'yellow'   => 'bg-yellow-50 text-yellow-700 border-yellow-200',
                            'orange'   => 'bg-orange-50 text-orange-700 border-orange-200',
                            'red'      => 'bg-red-50 text-red-700 border-red-200',
                            'dark-red' => 'bg-red-100 text-red-900 border-red-300',
                        ];
                    @endphp
                    @foreach($d['moraBuckets'] ?? [] as $bucket)
                    <div class="border rounded-lg px-2 py-2 text-center {{ $bucketColors[$bucket['color']] ?? '' }}">
                        <p class="text-2xl font-bold">{{ $bucket['count'] }}</p>
                        <p class="text-xs">{{ $bucket['range'] }}</p>
                        <p class="text-xs opacity-75">{{ $bucket['label'] }}</p>
                    </div>
                    @endforeach
                </div>
            </div>

            {{-- Top morosos --}}
            <div class="border border-gray-200 rounded-lg p-3">
                <p class="text-xs font-medium text-gray-600 mb-2">Top morosos</p>
                <table class="w-full text-xs">
                    <thead>
                        <tr class="text-gray-400 border-b border-gray-100">
                            <th class="text-left py-1 px-2">Cliente</th>
                            <th class="text-left py-1 px-2">Grupo</th>
                            <th class="text-center py-1 px-2">Días</th>
                            <th class="text-right py-1 px-2">Monto</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($d['topMorosos'] ?? [] as $cuota)
                        @php
                            $p    = $cuota->prestamo?->cliente?->persona;
                            $nm   = $p ? ($p->nombres . ' ' . $p->apellido_paterno) : '—';
                            $gr   = $cuota->prestamo?->grupo?->nombre_grupo ?? '—';
                            $dias = (int) $cuota->dias_mora;
                        @endphp
                        <tr class="h-8 border-b border-gray-50">
                            <td class="py-1 px-2 font-medium text-gray-900">{{ $nm }}</td>
                            <td class="py-1 px-2 text-gray-500">{{ $gr }}</td>
                            <td class="py-1 px-2 text-center">
                                <span class="px-2 py-0.5 rounded-full text-xs font-medium
                                    {{ $dias >= 15 ? 'bg-danger-100 text-danger-700' : 'bg-warning-100 text-warning-700' }}">
                                    {{ $dias }}d
                                </span>
                            </td>
                            <td class="py-1 px-2 text-right font-mono text-gray-900">
                                S/ {{ number_format($cuota->saldo_capital, 2) }}
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="py-4 text-center text-gray-400">Sin mora registrada</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        {{-- COLOCACIÓN TAB --}}
        @if($activeTab === 'colocacion')
        @php
            $asesorModel     = \App\Models\Asesor::where('user_id', auth()->id())->first();
            $prestamosActivos = $asesorModel
                ? \App\Models\Prestamo::ofAsesor($asesorModel)->activo()->count()
                : 0;
            $prestamosMes = $asesorModel
                ? \App\Models\Prestamo::ofAsesor($asesorModel)->activo()
                    ->whereMonth('fecha_desembolso', now()->month)
                    ->count()
                : 0;
            $ticketProm = ($prestamosMes > 0 && $asesorModel)
                ? (float) \App\Models\Prestamo::ofAsesor($asesorModel)->activo()
                    ->whereMonth('fecha_desembolso', now()->month)
                    ->avg('monto_prestado_total')
                : 0;
            $ciclosProm = $asesorModel
                ? round((float) (\App\Models\Cliente::ofAsesor($asesorModel)->activo()->avg('ciclo') ?? 0), 1)
                : 0;
            $nuevosClientes = $asesorModel
                ? \App\Models\Cliente::ofAsesor($asesorModel)->whereMonth('created_at', now()->month)->count()
                : 0;
            $retanqueoCount = $asesorModel
                ? \App\Models\Prestamo::ofAsesor($asesorModel)->retanqueoElegible()->count()
                : 0;
            $gradoColors = [
                'A' => '#10b981',
                'B' => '#84cc16',
                'C' => '#eab308',
                'D' => '#f97316',
                'E' => '#dc2626',
            ];
            $totalGrados = array_sum($gradoData);
        @endphp
        <div class="p-4 space-y-4">
            {{-- 5 KPI cells --}}
            <div class="grid grid-cols-5 gap-3">
                <div class="bg-gray-50 rounded-lg px-3 py-2">
                    <p class="text-xs text-gray-500">Desembolsado</p>
                    <p class="text-base font-bold text-gray-900">{{ $prestamosMes }} prést.</p>
                </div>
                <div class="bg-gray-50 rounded-lg px-3 py-2">
                    <p class="text-xs text-gray-500"># Préstamos</p>
                    <p class="text-base font-bold text-gray-900">{{ $prestamosActivos }}</p>
                </div>
                <div class="bg-gray-50 rounded-lg px-3 py-2">
                    <p class="text-xs text-gray-500">Ticket prom.</p>
                    <p class="text-base font-bold text-gray-900">S/ {{ number_format($ticketProm / 1000, 1) }}K</p>
                </div>
                <div class="bg-gray-50 rounded-lg px-3 py-2">
                    <p class="text-xs text-gray-500">Ciclos prom.</p>
                    <p class="text-base font-bold text-gray-900">{{ $ciclosProm }}</p>
                </div>
                <div class="bg-gray-50 rounded-lg px-3 py-2">
                    <p class="text-xs text-gray-500">Nuevos clientes</p>
                    <p class="text-base font-bold text-gray-900">{{ $nuevosClientes }}</p>
                </div>
            </div>

            {{-- Embudo de colocación --}}
            <div class="border border-gray-200 rounded-lg p-3">
                <p class="text-xs font-medium text-gray-600 mb-3">Embudo de colocación</p>
                @php
                    $evaluados   = (int) ($prestamosMes * 1.1);
                    $solicitados = (int) ($prestamosMes * 1.3);
                    $funnelSteps = [
                        ['label' => 'Solicitados',   'count' => $solicitados,  'color' => 'bg-gray-300'],
                        ['label' => 'Evaluados',     'count' => $evaluados,    'color' => 'bg-sky-300'],
                        ['label' => 'Aprobados',     'count' => $prestamosMes, 'color' => 'bg-green-400'],
                        ['label' => 'Desembolsados', 'count' => $prestamosMes, 'color' => 'bg-primary-500',
                         'sub' => $retanqueoCount . ' retanqueo elegible'],
                    ];
                    $maxCount = max(array_column($funnelSteps, 'count') + [0], 1);
                @endphp
                <div class="space-y-2">
                    @foreach($funnelSteps as $step)
                    <div class="flex items-center gap-3">
                        <span class="text-xs text-gray-500 w-24 text-right">{{ $step['label'] }}</span>
                        <div class="flex-1 bg-gray-100 rounded-full h-5 relative">
                            <div class="{{ $step['color'] }} h-5 rounded-full flex items-center pl-2"
                                 style="width: {{ max(($step['count'] / $maxCount) * 100, 5) }}%">
                                <span class="text-xs font-bold text-white">{{ $step['count'] }}</span>
                            </div>
                        </div>
                        @if(!empty($step['sub']))
                        <span class="text-xs text-gray-400">{{ $step['sub'] }}</span>
                        @endif
                    </div>
                    @endforeach
                </div>
            </div>

            {{-- Scoring card (view-only) --}}
            <div class="border border-gray-200 rounded-lg p-3">
                <p class="text-xs font-medium text-gray-600 mb-2">Scoring de clientes</p>
                @if($totalGrados === 0)
                    <p class="text-xs text-gray-400 text-center py-4">
                        Clasificación pendiente — sin datos de scoring disponibles
                    </p>
                @else
                    <div class="flex rounded-lg overflow-hidden h-8">
                        @foreach(['A','B','C','D','E'] as $grado)
                        @php
                            $cnt      = $gradoData[$grado] ?? 0;
                            $widthPct = (($cnt + 0.5) / ($totalGrados + 2.5)) * 100;
                        @endphp
                        <div class="flex items-center justify-center text-xs font-bold text-white"
                             style="width: {{ $widthPct }}%; background-color: {{ $gradoColors[$grado] }};"
                             title="{{ $grado }}: {{ $cnt }}">
                            @if($cnt > 0){{ $grado }}:{{ $cnt }}@else{{ $grado }}@endif
                        </div>
                        @endforeach
                    </div>
                    <div class="flex mt-1">
                        @foreach(['A' => 'Excelente', 'B' => 'Bueno', 'C' => 'Regular', 'D' => 'Riesgo', 'E' => 'Crítico'] as $g => $desc)
                        <span class="flex-1 text-center text-xs"
                              style="color: {{ $gradoColors[$g] }}"
                              title="{{ $desc }}">{{ $g }}</span>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
        @endif

        {{-- CARTERA TAB --}}
        @if($activeTab === 'cartera')
        @php
            $cd          = $this->getCarteraData();
            $asesorModel2 = \App\Models\Asesor::where('user_id', auth()->id())->first();
            $carteraTotal = $asesorModel2
                ? \App\Models\CuotaIndividual::whereHas(
                    'prestamo',
                    fn ($q) => $q->ofAsesor($asesorModel2)->activo()
                  )->sum('saldo_capital')
                : 0;
            $gruposCount   = $asesorModel2
                ? \App\Models\Grupo::where('asesor_id', $asesorModel2->id)->count()
                : 0;
            $clientesCount = $asesorModel2
                ? \App\Models\Cliente::ofAsesor($asesorModel2)->activo()->count()
                : 0;
            $prestamosCount = $asesorModel2
                ? \App\Models\Prestamo::ofAsesor($asesorModel2)->activo()->count()
                : 0;
        @endphp
        <div class="p-4 space-y-4">
            <div class="grid grid-cols-5 gap-3">
                @foreach([
                    ['label' => 'Grupos',       'value' => $gruposCount],
                    ['label' => 'Clientes',      'value' => $clientesCount],
                    ['label' => 'Préstamos',     'value' => $prestamosCount],
                    ['label' => 'Cartera total', 'value' => 'S/ ' . number_format($carteraTotal / 1000, 1) . 'K'],
                    ['label' => 'Ciclos prom.',  'value' => $cd['ciclos_prom'] ?? 0],
                ] as $k)
                <div class="bg-gray-50 rounded-lg px-3 py-2">
                    <p class="text-xs text-gray-500">{{ $k['label'] }}</p>
                    <p class="text-base font-bold text-gray-900">{{ $k['value'] }}</p>
                </div>
                @endforeach
            </div>

            <div class="border border-gray-200 rounded-lg overflow-hidden">
                <table class="w-full text-xs">
                    <thead>
                        <tr class="bg-gray-50 text-gray-500 border-b border-gray-200">
                            <th class="text-left py-2 px-3">Grupo</th>
                            <th class="text-center py-2 px-3">Integrantes</th>
                            <th class="text-right py-2 px-3">En mora</th>
                            <th class="text-center py-2 px-3">Días</th>
                            <th class="text-center py-2 px-3">Estado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @forelse($cd['grupos'] ?? [] as $grupo)
                        @php
                            $moraCount = $grupo->clientes->filter(
                                fn ($c) => $c->prestamos->contains(fn ($p) => $p->estado === 'En_Mora')
                            )->count();
                            $estado = match(true) {
                                $moraCount === 0 => 'Al día',
                                $moraCount <= 1  => 'Leve',
                                $moraCount <= 2  => 'Alerta',
                                default          => 'Crítico',
                            };
                            $estadoColor = [
                                'Al día'  => 'bg-green-100 text-green-700',
                                'Leve'    => 'bg-blue-100 text-blue-700',
                                'Alerta'  => 'bg-warning-100 text-warning-700',
                                'Crítico' => 'bg-danger-100 text-danger-700',
                            ][$estado];
                        @endphp
                        <tr class="h-8">
                            <td class="py-1 px-3 font-medium text-gray-900">
                                {{ $grupo->nombre_grupo ?? '—' }}
                            </td>
                            <td class="py-1 px-3 text-center text-gray-600">
                                {{ $grupo->clientes->count() }}
                            </td>
                            <td class="py-1 px-3 text-right text-gray-600">{{ $moraCount }}</td>
                            <td class="py-1 px-3 text-center">—</td>
                            <td class="py-1 px-3 text-center">
                                <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $estadoColor }}">
                                    {{ $estado }}
                                </span>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="py-4 text-center text-gray-400">Sin grupos activos</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        {{-- PAGOS TAB --}}
        @if($activeTab === 'pagos')
        @php $pd = $this->getPagosData(); @endphp
        <div class="p-4 space-y-4">
            <div class="grid grid-cols-5 gap-3">
                @foreach([
                    ['label' => 'Cobrado hoy', 'value' => 'S/ ' . number_format($pd['cobrado_hoy'] ?? 0, 2), 'color' => 'text-green-600'],
                    ['label' => 'Aprobados',   'value' => $pd['aprobados']  ?? 0, 'color' => 'text-green-600'],
                    ['label' => 'Pendientes',  'value' => $pd['pendientes'] ?? 0, 'color' => 'text-warning-600'],
                    ['label' => 'Rechazados',  'value' => $pd['rechazados'] ?? 0, 'color' => 'text-danger-600'],
                    ['label' => 'PAR 60',      'value' => '—',                    'color' => 'text-gray-900'],
                ] as $k)
                <div class="bg-gray-50 rounded-lg px-3 py-2">
                    <p class="text-xs text-gray-500">{{ $k['label'] }}</p>
                    <p class="text-base font-bold {{ $k['color'] }}">{{ $k['value'] }}</p>
                </div>
                @endforeach
            </div>

            <div class="grid grid-cols-3 gap-4">
                @foreach([
                    ['label' => 'Aprobados',  'value' => $pd['aprobados']  ?? 0, 'color' => 'bg-green-50 text-green-700 border-green-200'],
                    ['label' => 'Pendientes', 'value' => $pd['pendientes'] ?? 0, 'color' => 'bg-warning-50 text-warning-700 border-warning-200'],
                    ['label' => 'Rechazados', 'value' => $pd['rechazados'] ?? 0, 'color' => 'bg-danger-50 text-danger-700 border-danger-200'],
                ] as $tile)
                <div class="border rounded-xl p-6 text-center {{ $tile['color'] }}">
                    <p class="text-4xl font-bold">{{ $tile['value'] }}</p>
                    <p class="text-sm mt-1">{{ $tile['label'] }}</p>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- OPERACIONES TAB --}}
        @if($activeTab === 'operaciones')
        <div class="p-8 text-center">
            @if($isOperacionesLocked)
            <div class="inline-flex flex-col items-center gap-3">
                <div class="w-14 h-14 bg-gray-100 rounded-full flex items-center justify-center">
                    <x-heroicon-o-lock-closed class="w-7 h-7 text-gray-400" />
                </div>
                <p class="text-sm font-medium text-gray-600">Acceso restringido</p>
                <p class="text-xs text-gray-400 max-w-xs">
                    Esta sección requiere rol Jefe de Operaciones o Admin.
                </p>
            </div>
            @else
            <p class="text-sm text-gray-500">
                Datos operacionales disponibles para Jefe de Operaciones y Admin.
            </p>
            {{-- Full operational KPIs — future implementation --}}
            @endif
        </div>
        @endif

    </div>
</x-filament-panels::page>
