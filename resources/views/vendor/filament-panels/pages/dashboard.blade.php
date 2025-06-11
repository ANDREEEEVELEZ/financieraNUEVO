<x-filament-panels::page class="fi-dashboard-page">
@if (method_exists($this, 'filtersForm'))
{{ $this->filtersForm }}
@endif

<div class="mb-8 animate__animated animate__fadeInDown">
<div class="flex flex-col md:flex-row items-center gap-6 p-6 bg-gradient-to-r from-blue-100 via-indigo-100 to-purple-100 dark:from-blue-900 dark:via-indigo-900 dark:to-purple-900 rounded-2xl shadow-xl border border-blue-200 dark:border-blue-700">
<div class="flex-shrink-0">
<img src="https://ui-avatars.com/api/?name={{ urlencode(auth()->user()->name) }}&background=4f46e5&color=fff&size=96" alt="Avatar" class="rounded-full shadow-lg ring-4 ring-blue-300 dark:ring-blue-700 animate__animated animate__pulse animate__infinite" style="width:96px;height:96px;">
</div>
<div class="flex-1">
<h2 class="text-2xl md:text-3xl font-extrabold text-blue-900 dark:text-blue-100 mb-1 animate__animated animate__fadeInLeft">¡Bienvenido, <span class="text-indigo-600 dark:text-indigo-300">{{ auth()->user()->name }}</span>!</h2>
<p class="text-lg text-gray-700 dark:text-gray-200 animate__animated animate__fadeInLeft animate__delay-1s">Correo: <span class="font-semibold text-indigo-700 dark:text-indigo-300">{{ auth()->user()->email }}</span></p>

<div class="mt-2 flex flex-wrap gap-2 animate__animated animate__fadeInLeft animate__delay-2s">
@php
$roles = auth()->user()->getRoleNames();
@endphp
@foreach($roles as $rol)
<span class="inline-block bg-gradient-to-r from-indigo-400 to-blue-500 text-white text-xs font-bold px-3 py-1 rounded-full shadow animate__animated animate__bounceIn">{{ $rol }}</span>
@endforeach
</div>
</div>
<div class="flex flex-col items-center animate__animated animate__fadeInRight animate__delay-1s">
<svg class="w-10 h-10 text-indigo-400 dark:text-indigo-200 mb-2 animate__animated animate__heartBeat animate__infinite" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21C12 21 4 13.5 4 8.5C4 5.42 6.42 3 9.5 3C11.24 3 12.91 4.01 13.44 5.61C13.97 4.01 15.64 3 17.38 3C20.46 3 22.88 5.42 22.88 8.5C22.88 13.5 15 21 15 21H12Z" /></svg>
<span class="text-xs text-indigo-700 dark:text-indigo-300 font-semibold">Usuario activo</span>
</div>
</div>
</div>

<x-filament-widgets::widgets
:columns="$this->getColumns()"
:data="
    [
        ...(property_exists($this, 'filters') ? ['filters' => $this->filters] : []),
        ...$this->getWidgetData(),
    ]
"
:widgets="$this->getVisibleWidgets()"
/>

{{-- Bloque de Actividad Reciente --}}
@php
$movimientos = collect([]);
$clientes = \App\Models\Cliente::with('persona')->select('id', 'created_at', 'updated_at')->get()->map(function($c) {
return [
'modulo' => 'Cliente',
'nombre' => optional($c->persona)->nombre . ' ' . optional($c->persona)->apellidos,
'accion' => $c->created_at == $c->updated_at ? 'Creado' : 'Modificado',
'fecha' => $c->created_at == $c->updated_at ? $c->created_at : $c->updated_at,
'icono' => 'user',
'color' => $c->created_at == $c->updated_at ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700',
];
});
$movimientos = $movimientos->concat($clientes);
$grupos = \App\Models\Grupo::select('id', 'nombre_grupo', 'created_at', 'updated_at')->get()->map(function($g) {
return [
'modulo' => 'Grupo',
'nombre' => $g->nombre_grupo,
'accion' => $g->created_at == $g->updated_at ? 'Creado' : 'Modificado',
'fecha' => $g->created_at == $g->updated_at ? $g->created_at : $g->updated_at,
'icono' => 'users',
'color' => $g->created_at == $g->updated_at ? 'bg-blue-100 text-blue-700' : 'bg-yellow-100 text-yellow-700',
];
});
$movimientos = $movimientos->concat($grupos);
$pagos = \App\Models\Pago::select('id', 'created_at', 'updated_at')->get()->map(function($p) {
return [
'modulo' => 'Pago',
'nombre' => 'Pago ID ' . $p->id,
'accion' => $p->created_at == $p->updated_at ? 'Creado' : 'Modificado',
'fecha' => $p->created_at == $p->updated_at ? $p->created_at : $p->updated_at,
'icono' => 'credit-card',
'color' => $p->created_at == $p->updated_at ? 'bg-indigo-100 text-indigo-700' : 'bg-yellow-100 text-yellow-700',
];
});
$movimientos = $movimientos->concat($pagos);
$prestamos = \App\Models\Prestamo::select('id', 'created_at', 'updated_at')->get()->map(function($pr) {
return [
'modulo' => 'Préstamo',
'nombre' => 'Préstamo ID ' . $pr->id,
'accion' => $pr->created_at == $pr->updated_at ? 'Creado' : 'Modificado',
'fecha' => $pr->created_at == $pr->updated_at ? $pr->created_at : $pr->updated_at,
'icono' => 'banknotes',
'color' => $pr->created_at == $pr->updated_at ? 'bg-purple-100 text-purple-700' : 'bg-yellow-100 text-yellow-700',
];
});
$movimientos = $movimientos->concat($prestamos);
$personas = \App\Models\Persona::select('id', 'nombre', 'apellidos', 'created_at', 'updated_at')->get()->map(function($p) {
return [
'modulo' => 'Persona',
'nombre' => trim($p->nombre . ' ' . $p->apellidos),
'accion' => $p->created_at == $p->updated_at ? 'Creado' : 'Modificado',
'fecha' => $p->created_at == $p->updated_at ? $p->created_at : $p->updated_at,
'icono' => 'id-card',
'color' => $p->created_at == $p->updated_at ? 'bg-orange-100 text-orange-700' : 'bg-yellow-100 text-yellow-700',
];
});
$movimientos = $movimientos->concat($personas);
$retanqueos = \App\Models\Retanqueo::select('id', 'created_at', 'updated_at')->get()->map(function($r) {
return [
'modulo' => 'Retanqueo',
'nombre' => 'Retanqueo ID ' . $r->id,
'accion' => $r->created_at == $r->updated_at ? 'Creado' : 'Modificado',
'fecha' => $r->created_at == $r->updated_at ? $r->created_at : $r->updated_at,
'icono' => 'refresh',
'color' => $r->created_at == $r->updated_at ? 'bg-pink-100 text-pink-700' : 'bg-yellow-100 text-yellow-700',
];
});
$movimientos = $movimientos->concat($retanqueos);
$movimientos = $movimientos->sortByDesc('fecha')->take(5);
@endphp

<div class="mb-8 animate__animated animate__fadeInUp">
<div class="bg-white dark:bg-gray-900 rounded-2xl shadow-xl border border-gray-200 dark:border-gray-700 p-6">
<h3 class="text-xl font-bold text-gray-800 dark:text-gray-100 mb-4 flex items-center gap-2">
<svg class="w-6 h-6 text-indigo-500 animate__animated animate__bounceIn" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" /></svg>
Actividad Reciente
</h3>
<ul class="divide-y divide-gray-200 dark:divide-gray-700">
@forelse($movimientos as $mov)
<li class="flex items-center gap-4 py-3 animate__animated animate__fadeInLeft">
<span class="inline-flex items-center justify-center w-10 h-10 rounded-full {{ $mov['color'] }}">
@if($mov['icono'] === 'user')
<svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5.121 17.804A13.937 13.937 0 0112 15c2.5 0 4.847.655 6.879 1.804M15 11a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
@elseif($mov['icono'] === 'users')
<svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6 5.87V4m0 0a4 4 0 11-8 0 4 4 0 018 0z" /></svg>
@elseif($mov['icono'] === 'credit-card')
<svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H9a2 2 0 00-2 2v2m10 0v2a2 2 0 01-2 2H9a2 2 0 01-2-2v-2m10 0V7a2 2 0 00-2-2H9a2 2 0 00-2 2v2m10 0v2a2 2 0 01-2 2H9a2 2 0 01-2-2v-2" /></svg>
@elseif($mov['icono'] === 'banknotes')
<svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H9a2 2 0 00-2 2v2m10 0v2a2 2 0 01-2 2H9a2 2 0 01-2-2v-2m10 0V7a2 2 0 00-2-2H9a2 2 0 00-2 2v2m10 0v2a2 2 0 01-2 2H9a2 2 0 01-2-2v-2" /></svg>
@elseif($mov['icono'] === 'id-card')
<svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="7" width="18" height="10" rx="2"/><path d="M7 10h.01M7 14h.01M10 14h4M17 10h.01"/></svg>
@elseif($mov['icono'] === 'refresh')
<svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582M20 20v-5h-.581M5.582 9A7.974 7.974 0 014 12c0 4.418 3.582 8 8 8a7.974 7.974 0 006.418-3" /></svg>
@endif
</span>
<div class="flex-1 min-w-0">
<div class="flex items-center gap-2">
<span class="font-bold text-gray-800 dark:text-gray-100">{{ $mov['modulo'] }}</span>
<span class="text-xs px-2 py-0.5 rounded-full {{ $mov['color'] }} font-semibold">{{ $mov['accion'] }}</span>
</div>
<div class="text-sm text-gray-600 dark:text-gray-300 truncate">{{ $mov['nombre'] }}</div>
</div>
<div class="text-xs text-gray-500 dark:text-gray-400 text-right">
{{ \Carbon\Carbon::parse($mov['fecha'])->diffForHumans() }}
</div>
</li>
@empty
<li class="text-gray-500 dark:text-gray-400 py-4 text-center">No hay actividad reciente.</li>
@endforelse
</ul>
</div>
</div>

{{-- Bloque de Mapa/Distribución Geográfica de Clientes por Distrito --}}
@php
// Agrupar clientes por distrito (usando la relación con persona)
$clientesPorDistrito = \App\Models\Cliente::with('persona')
->get()
->groupBy(function($cliente) {
return optional($cliente->persona)->distrito ?: 'Sin distrito';
})
->map(function($group) {
return $group->count();
});
$labelsDistritos = $clientesPorDistrito->keys()->toArray();
$dataDistritos = $clientesPorDistrito->values()->toArray();
@endphp

<div class="mb-8 animate__animated animate__fadeInUp">
<div class="bg-white dark:bg-gray-900 rounded-2xl shadow-xl border border-gray-200 dark:border-gray-700 p-6">
<h3 class="text-xl font-bold text-gray-800 dark:text-gray-100 mb-4 flex items-center gap-2">
<svg class="w-6 h-6 text-green-500 animate__animated animate__bounceIn" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 12.414a2 2 0 00-2.828 0l-4.243 4.243a8 8 0 1111.314 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
Distribución Geográfica de Clientes
</h3>
<div class="h-96 w-full flex items-center justify-center">
<canvas id="distritosChart" height="350"></canvas>
</div>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
const ctx = document.getElementById('distritosChart').getContext('2d');
const distritosChart = new Chart(ctx, {
type: 'doughnut',
data: {
labels: @json($labelsDistritos),
datasets: [{
label: 'Clientes por distrito',
data: @json($dataDistritos),
backgroundColor: [
'#6366f1', '#22d3ee', '#f59e42', '#10b981', '#f43f5e', '#a78bfa', '#fbbf24', '#14b8a6', '#eab308', '#f87171', '#818cf8', '#34d399', '#f472b6', '#facc15', '#60a5fa', '#c026d3', '#fcd34d', '#4ade80', '#fca5a5', '#a3e635', '#f472b6', '#f87171', '#fbbf24', '#818cf8', '#f59e42', '#6366f1', '#22d3ee', '#10b981', '#f43f5e', '#a78bfa'
],
borderWidth: 2,
borderColor: '#fff',
hoverOffset: 16,
}]
},
options: {
responsive: true,
cutout: '70%',
plugins: {
legend: {
display: true,
position: 'bottom',
labels: {
color: document.documentElement.classList.contains('dark') ? '#f3f4f6' : '#111827',
font: { size: 13, weight: 'bold' },
padding: 18,
},
},
},
});
});
</script>
</x-filament-panels::page>
