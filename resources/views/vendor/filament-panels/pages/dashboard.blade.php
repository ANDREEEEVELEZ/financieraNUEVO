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
<span class="inline-block bg-white/20 backdrop-blur-sm text-indigo-900 dark:text-white text-sm font-bold px-4 py-2 rounded-full shadow-lg border border-white/30 animate__animated animate__bounceIn">
 {{ $rol }}
</span>
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

{{-- Bloque de Actividad Reciente Mejorado --}}
@php
$movimientos = collect([]);
$user = auth()->user();

// Verificar si el usuario puede ver toda la actividad o solo la suya
$canViewAllActivity = in_array($user->rol, ['Jefe de operaciones', 'Jefe de creditos', 'super_admin']);

// Obtener el asesor_id del usuario autenticado
$asesorId = $user->asesor_id ?? $user->id; // Usar asesor_id si existe, sino usar el id del usuario

// Clientes
$clientesQuery = \App\Models\Cliente::with('persona');
if (!$canViewAllActivity) {
    $clientesQuery->where('asesor_id', $asesorId);
}
$clientes = $clientesQuery->select('id', 'created_at', 'updated_at', 'asesor_id')->get()->map(function($c) {
    return [
        'modulo' => 'Cliente',
        'nombre' => optional($c->persona)->nombre . ' ' . optional($c->persona)->apellidos,
        'accion' => $c->created_at == $c->updated_at ? 'Creado' : 'Modificado',
        'fecha' => $c->created_at == $c->updated_at ? $c->created_at : $c->updated_at,
        'icono' => 'user',
        'color' => $c->created_at == $c->updated_at ? 'bg-emerald-50 text-emerald-600 border-emerald-200' : 'bg-amber-50 text-amber-600 border-amber-200',
        'bg_gradient' => $c->created_at == $c->updated_at ? 'from-emerald-50 to-emerald-100' : 'from-amber-50 to-amber-100',
    ];
});
$movimientos = $movimientos->concat($clientes);

// Grupos
$gruposQuery = \App\Models\Grupo::query();
if (!$canViewAllActivity) {
    $gruposQuery->where('asesor_id', $asesorId);
}
$grupos = $gruposQuery->select('id', 'nombre_grupo', 'created_at', 'updated_at', 'asesor_id')->get()->map(function($g) {
    return [
        'modulo' => 'Grupo',
        'nombre' => $g->nombre_grupo,
        'accion' => $g->created_at == $g->updated_at ? 'Creado' : 'Modificado',
        'fecha' => $g->created_at == $g->updated_at ? $g->created_at : $g->updated_at,
        'icono' => 'users',
        'color' => $g->created_at == $g->updated_at ? 'bg-blue-50 text-blue-600 border-blue-200' : 'bg-amber-50 text-amber-600 border-amber-200',
        'bg_gradient' => $g->created_at == $g->updated_at ? 'from-blue-50 to-blue-100' : 'from-amber-50 to-amber-100',
    ];
});
$movimientos = $movimientos->concat($grupos);

// Pagos - CORREGIDO: usar cuota_grupal_id y hacer joins necesarios
$pagosQuery = \App\Models\Pago::with(['cuotaGrupal.prestamo.grupo']);
if (!$canViewAllActivity) {
    // Para filtrar por asesor, necesitamos hacer join con las tablas relacionadas
    $pagosQuery->whereHas('cuotaGrupal.prestamo.grupo', function($query) use ($asesorId) {
        $query->where('asesor_id', $asesorId);
    });
}
$pagos = $pagosQuery->select('id', 'cuota_grupal_id', 'created_at', 'updated_at')->get()->map(function($p) {
    $nombreGrupo = optional($p->cuotaGrupal->prestamo->grupo ?? null)->nombre_grupo ?? 'Grupo no encontrado';
    return [
        'modulo' => 'Pago',
        'nombre' => 'Pago del grupo: ' . $nombreGrupo,
        'accion' => $p->created_at == $p->updated_at ? 'Creado' : 'Modificado',
        'fecha' => $p->created_at == $p->updated_at ? $p->created_at : $p->updated_at,
        'icono' => 'credit-card',
        'color' => $p->created_at == $p->updated_at ? 'bg-violet-50 text-violet-600 border-violet-200' : 'bg-amber-50 text-amber-600 border-amber-200',
        'bg_gradient' => $p->created_at == $p->updated_at ? 'from-violet-50 to-violet-100' : 'from-amber-50 to-amber-100',
    ];
});
$movimientos = $movimientos->concat($pagos);

// Préstamos - Con nombre del grupo que recibió el préstamo
$prestamosQuery = \App\Models\Prestamo::with(['grupo:id,nombre_grupo']);
if (!$canViewAllActivity) {
    $prestamosQuery->whereHas('grupo', function($query) use ($asesorId) {
        $query->where('asesor_id', $asesorId);
    });
}
$prestamos = $prestamosQuery->select('id', 'grupo_id', 'created_at', 'updated_at')->get()->map(function($pr) {
    $nombreGrupo = optional($pr->grupo)->nombre_grupo ?? 'Grupo no encontrado';
    return [
        'modulo' => 'Préstamo',
        'nombre' => 'Préstamo al grupo: ' . $nombreGrupo,
        'accion' => $pr->created_at == $pr->updated_at ? 'Creado' : 'Modificado',
        'fecha' => $pr->created_at == $pr->updated_at ? $pr->created_at : $pr->updated_at,
        'icono' => 'banknotes',
        'color' => $pr->created_at == $pr->updated_at ? 'bg-purple-50 text-purple-600 border-purple-200' : 'bg-amber-50 text-amber-600 border-amber-200',
        'bg_gradient' => $pr->created_at == $pr->updated_at ? 'from-purple-50 to-purple-100' : 'from-amber-50 to-amber-100',
    ];
});
$movimientos = $movimientos->concat($prestamos);

// Personas - CORREGIDO: La tabla personas no tiene asesor_id según la estructura
$personasQuery = \App\Models\Persona::query();
// Si necesitas filtrar personas por asesor, tendrías que hacerlo a través de clientes
if (!$canViewAllActivity) {
    // Opcional: filtrar solo personas que son clientes del asesor
    $personasQuery->whereHas('clientes', function($query) use ($asesorId) {
        $query->where('asesor_id', $asesorId);
    });
}
$personas = $personasQuery->select('id', 'nombre', 'apellidos', 'created_at', 'updated_at')->get()->map(function($p) {
    return [
        'modulo' => 'Persona',
        'nombre' => trim($p->nombre . ' ' . $p->apellidos),
        'accion' => $p->created_at == $p->updated_at ? 'Creado' : 'Modificado',
        'fecha' => $p->created_at == $p->updated_at ? $p->created_at : $p->updated_at,
        'icono' => 'id-card',
        'color' => $p->created_at == $p->updated_at ? 'bg-orange-50 text-orange-600 border-orange-200' : 'bg-amber-50 text-amber-600 border-amber-200',
        'bg_gradient' => $p->created_at == $p->updated_at ? 'from-orange-50 to-orange-100' : 'from-amber-50 to-amber-100',
    ];
});
$movimientos = $movimientos->concat($personas);

// Retanqueos - CORREGIDO: usar asesore_id (parece ser un typo en la tabla, debería ser asesor_id)
$retanqueosQuery = \App\Models\Retanqueo::with(['grupo:id,nombre_grupo']);
if (!$canViewAllActivity) {
    // Usar 'asesore_id' como está en la estructura de la tabla (aunque parece un typo)
    $retanqueosQuery->where('asesore_id', $asesorId);
}
$retanqueos = $retanqueosQuery->select('id', 'grupo_id', 'created_at', 'updated_at', 'asesore_id')->get()->map(function($r) {
    $nombreGrupo = optional($r->grupo)->nombre_grupo ?? 'Grupo no encontrado';
    return [
        'modulo' => 'Retanqueo',
        'nombre' => 'Retanqueo del grupo: ' . $nombreGrupo,
        'accion' => $r->created_at == $r->updated_at ? 'Creado' : 'Modificado',
        'fecha' => $r->created_at == $r->updated_at ? $r->created_at : $r->updated_at,
        'icono' => 'refresh',
        'color' => $r->created_at == $r->updated_at ? 'bg-pink-50 text-pink-600 border-pink-200' : 'bg-amber-50 text-amber-600 border-amber-200',
        'bg_gradient' => $r->created_at == $r->updated_at ? 'from-pink-50 to-pink-100' : 'from-amber-50 to-amber-100',
    ];
});
$movimientos = $movimientos->concat($retanqueos);

// Ordenar por fecha descendente y tomar solo las últimas 10
$movimientos = $movimientos->sortByDesc('fecha')->take(10);
@endphp

<div class="mb-8 transform transition-all duration-300 hover:scale-[1.01]">
    <div class="bg-white dark:bg-gray-800/50 backdrop-blur-sm rounded-3xl shadow-2xl border border-gray-100 dark:border-gray-700/50 overflow-hidden">

{{-- Header con gradiente --}}
<div class="relative bg-gradient-to-r from-indigo-500 via-purple-500 to-pink-500 px-6 py-4">
    <!-- Overlay oscuro para mejorar contraste -->
    <div class="absolute inset-0 bg-black/30"></div>

    <div class="relative z-10 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <div class="p-2 bg-white/20 rounded-xl backdrop-blur-sm">
                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                </svg>
            </div>
            <h3 class="text-xl font-bold text-black drop-shadow-lg">
                {{ $canViewAllActivity ? 'Actividad Reciente del Sistema' : 'Mi Actividad Reciente' }}
            </h3>
        </div>
        <div class="flex items-center gap-2">
            <span class="text-black text-sm drop-shadow-md">Últimas {{ $movimientos->count() }} actividades</span>
            <div class="w-2 h-2 bg-green-400 rounded-full animate-pulse"></div>
        </div>
    </div>
</div>


        {{-- Lista de actividades --}}
        <div class="max-h-96 overflow-y-auto scrollbar-thin scrollbar-thumb-gray-300 dark:scrollbar-thumb-gray-600 scrollbar-track-transparent">
            @forelse($movimientos as $index => $mov)
                <div class="group relative border-b border-gray-100 dark:border-gray-700/50 last:border-b-0 hover:bg-gradient-to-r hover:{{ $mov['bg_gradient'] }} transition-all duration-300"
                     style="animation-delay: {{ $index * 100 }}ms">

                    {{-- Línea de tiempo vertical --}}
                    @if(!$loop->last)
                        <div class="absolute left-8 top-16 w-0.5 h-6 bg-gradient-to-b from-gray-200 to-transparent dark:from-gray-600"></div>
                    @endif

                    <div class="flex items-center gap-4 p-4 relative">
                        {{-- Icono con animación --}}
                        <div class="relative flex-shrink-0">
                            <div class="w-12 h-12 rounded-2xl border-2 {{ $mov['color'] }} flex items-center justify-center group-hover:scale-110 transition-transform duration-300 shadow-lg">
                                @switch($mov['icono'])
                                    @case('user')
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                        </svg>
                                        @break
                                    @case('users')
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                        </svg>
                                        @break
                                    @case('credit-card')
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                                        </svg>
                                        @break
                                    @case('banknotes')
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        @break
                                    @case('id-card')
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2" />
                                        </svg>
                                        @break
                                    @case('refresh')
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                        </svg>
                                        @break
                                @endswitch
                            </div>
                            {{-- Punto de estado --}}
                            @if($mov['accion'] === 'Creado')
                                <div class="absolute -top-1 -right-1 w-4 h-4 bg-green-500 rounded-full border-2 border-white dark:border-gray-800 animate-pulse"></div>
                            @endif
                        </div>

                        {{-- Contenido principal --}}
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-3 mb-1">
                                <h4 class="font-bold text-gray-900 dark:text-gray-100 text-base">{{ $mov['modulo'] }}</h4>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold border {{ $mov['color'] }} group-hover:scale-105 transition-transform">
                                    @if($mov['accion'] === 'Creado')
                                        <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                        </svg>
                                    @else
                                        <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" />
                                        </svg>
                                    @endif
                                    {{ $mov['accion'] }}
                                </span>
                            </div>
                            <p class="text-sm text-gray-600 dark:text-gray-400 truncate font-medium">{{ $mov['nombre'] }}</p>
                        </div>

                        {{-- Fecha con formato mejorado --}}
                        <div class="text-right flex-shrink-0">
                            <div class="text-xs text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-700/50 px-3 py-1 rounded-full font-medium">
                                {{ \Carbon\Carbon::parse($mov['fecha'])->diffForHumans() }}
                            </div>
                            <div class="text-xs text-gray-400 dark:text-gray-500 mt-1">
                                {{ \Carbon\Carbon::parse($mov['fecha'])->format('d/m H:i') }}
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="flex flex-col items-center justify-center py-12 px-6">
                    <div class="w-24 h-24 bg-gray-100 dark:bg-gray-700 rounded-full flex items-center justify-center mb-4">
                        <svg class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </div>
                    <h4 class="text-lg font-semibold text-gray-600 dark:text-gray-400 mb-2">Sin actividad reciente</h4>
                    <p class="text-sm text-gray-500 dark:text-gray-500 text-center">
                        {{ $canViewAllActivity ? 'No hay movimientos registrados en el sistema.' : 'No tienes movimientos registrados recientemente.' }}
                    </p>
                </div>
            @endforelse
        </div>

        {{-- Footer con estadísticas rápidas --}}
        @if($movimientos->count() > 0)
            <div class="bg-gray-50 dark:bg-gray-800/50 px-6 py-3 border-t border-gray-100 dark:border-gray-700/50">
                <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                    <div class="flex items-center gap-4">
                        <span class="flex items-center gap-1">
                            <div class="w-2 h-2 bg-green-500 rounded-full"></div>
                            {{ $movimientos->where('accion', 'Creado')->count() }} Creados
                        </span>
                        <span class="flex items-center gap-1">
                            <div class="w-2 h-2 bg-amber-500 rounded-full"></div>
                            {{ $movimientos->where('accion', 'Modificado')->count() }} Modificados
                        </span>
                    </div>
                    <div class="text-indigo-600 dark:text-indigo-400 font-medium">
                        Actualizándose automáticamente
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>

{{-- CSS personalizado adicional --}}
<style>
.scrollbar-thin::-webkit-scrollbar {
    width: 4px;
}
.scrollbar-thin::-webkit-scrollbar-track {
    background: transparent;
}
.scrollbar-thin::-webkit-scrollbar-thumb {
    background-color: rgba(156, 163, 175, 0.5);
    border-radius: 20px;
}
.scrollbar-thin::-webkit-scrollbar-thumb:hover {
    background-color: rgba(156, 163, 175, 0.8);
}
</style>
{{-- Bloque de Mapa/Distribución Geográfica de Clientes por Distrito
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
--}}
</x-filament-panels::page>
