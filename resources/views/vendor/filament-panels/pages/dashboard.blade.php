<x-filament-panels::page class="fi-dashboard-page">
@if (method_exists($this, 'filtersForm'))
{{ $this->filtersForm }}
@endif

{{-- Botón flotante para cerrar sesión --}}
{{-- <link rel="stylesheet" href="/css/custom-btn.css">
<form method="POST" action="{{ route('logout') }}" style="position: fixed; bottom: 30px; right: 30px; z-index: 9999;">
    @csrf
    <button type="submit" class="Btn">
      <div class="sign"><svg viewBox="0 0 512 512"><path d="M377.9 105.9L500.7 228.7c7.2 7.2 11.3 17.1 11.3 27.3s-4.1 20.1-11.3 27.3L377.9 406.1c-6.4 6.4-15 9.9-24 9.9c-18.7 0-33.9-15.2-33.9-33.9l0-62.1-128 0c-17.7 0-32-14.3-32-32l0-64c0-17.7 14.3-32 32-32l128 0 0-62.1c0-18.7 15.2-33.9 33.9-33.9c9 0 17.6 3.6 24 9.9zM160 96L96 96c-17.7 0-32 14.3-32 32l0 256c0 17.7 14.3 32 32 32l64 0c17.7 0 32 14.3 32 32s-14.3 32-32 32l-64 0c-53 0-96-43-96-96L0 128C0 75 43 32 96 32l64 0c17.7 0 32 14.3 32 32s-14.3 32-32 32z"></path></svg></div>
      <div class="text">Salir</div>
    </button>
</form> --}}
{{-- FIN Botón flotante para cerrar sesión --}}

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



{{-- Bloque de Actividad Reciente Mejorado --}}
@php
$movimientos = collect([]);
$user = auth()->user();

// Verificar si el usuario puede ver toda la actividad o solo la suya
$canViewAllActivity = $user->hasAnyRole(['super_admin', 'Jefe de operaciones', 'Jefe de creditos']);

// Obtener el asesor_id del usuario autenticado si es asesor
$asesorId = null;
if ($user->hasRole('Asesor')) {
    $asesor = \App\Models\Asesor::where('user_id', $user->id)->first();
    $asesorId = $asesor ? $asesor->id : null;
}

// Filtro opcional por asesor (para jefes y super admin)
$filtroAsesorId = request('filtro_asesor_id');
if ($canViewAllActivity && $filtroAsesorId) {
    $asesorId = $filtroAsesorId;
}

try {
    // Clientes con más detalles
    $clientesQuery = \App\Models\Cliente::with(['persona', 'asesor.persona']);
    if (!$canViewAllActivity && $asesorId) {
        $clientesQuery->where('asesor_id', $asesorId);
    } elseif ($canViewAllActivity && $asesorId) {
        $clientesQuery->where('asesor_id', $asesorId);
    }
    $clientes = $clientesQuery->select('id', 'created_at', 'updated_at', 'asesor_id', 'estado_cliente', 'persona_id')
        ->orderBy('updated_at', 'desc')
        ->limit(15)
        ->get()->map(function($c) {
        $nombreCliente = optional($c->persona)->nombre . ' ' . optional($c->persona)->apellidos;
        $nombreAsesor = optional($c->asesor->persona ?? null)->nombre . ' ' . optional($c->asesor->persona ?? null)->apellidos;
        $estado = $c->estado_cliente ?? 'Sin estado';
        $dni = optional($c->persona)->DNI ?? 'Sin DNI';
        
        return [
            'modulo' => 'Cliente',
            'nombre' => $nombreCliente,
            'detalle' => "DNI: {$dni} | Estado: {$estado}" . ($nombreAsesor ? " | Asesor: {$nombreAsesor}" : ""),
            'accion' => $c->created_at == $c->updated_at ? 'Creado' : 'Modificado',
            'fecha' => $c->created_at == $c->updated_at ? $c->created_at : $c->updated_at,
            'icono' => 'user',
            'color' => $c->created_at == $c->updated_at ? 'bg-emerald-50 text-emerald-600 border-emerald-200' : 'bg-amber-50 text-amber-600 border-amber-200',
            'bg_gradient' => $c->created_at == $c->updated_at ? 'from-emerald-50 to-emerald-100' : 'from-amber-50 to-amber-100',
            'url' => route('filament.dashboard.resources.clientes.edit', $c->id),
        ];
    });
    $movimientos = $movimientos->concat($clientes);
} catch (\Exception $e) {
    \Log::error('Error loading clientes for dashboard: ' . $e->getMessage());
}

try {
    // Grupos con más detalles
    $gruposQuery = \App\Models\Grupo::with(['asesor.persona']);
    if (!$canViewAllActivity && $asesorId) {
        $gruposQuery->where('asesor_id', $asesorId);
    } elseif ($canViewAllActivity && $asesorId) {
        $gruposQuery->where('asesor_id', $asesorId);
    }
    $grupos = $gruposQuery->select('id', 'nombre_grupo', 'created_at', 'updated_at', 'asesor_id', 'numero_integrantes', 'estado_grupo')
        ->orderBy('updated_at', 'desc')
        ->limit(10)
        ->get()->map(function($g) {
        $nombreAsesor = optional($g->asesor->persona ?? null)->nombre . ' ' . optional($g->asesor->persona ?? null)->apellidos;
        $integrantes = $g->numero_integrantes ?? 0;
        $estado = $g->estado_grupo ?? 'Sin estado';
        
        return [
            'modulo' => 'Grupo',
            'nombre' => $g->nombre_grupo,
            'detalle' => "{$integrantes} integrantes | Estado: {$estado}" . ($nombreAsesor ? " | Asesor: {$nombreAsesor}" : ""),
            'accion' => $g->created_at == $g->updated_at ? 'Creado' : 'Modificado',
            'fecha' => $g->created_at == $g->updated_at ? $g->created_at : $g->updated_at,
            'icono' => 'users',
            'color' => $g->created_at == $g->updated_at ? 'bg-blue-50 text-blue-600 border-blue-200' : 'bg-amber-50 text-amber-600 border-amber-200',
            'bg_gradient' => $g->created_at == $g->updated_at ? 'from-blue-50 to-blue-100' : 'from-amber-50 to-amber-100',
            'url' => route('filament.dashboard.resources.grupos.edit', $g->id),
        ];
    });
    $movimientos = $movimientos->concat($grupos);
} catch (\Exception $e) {
    \Log::error('Error loading grupos for dashboard: ' . $e->getMessage());
}

try {
    // Pagos con más detalles
    $pagosQuery = \App\Models\Pago::with(['cuotaGrupal.prestamo.grupo.asesor.persona']);
    if (!$canViewAllActivity && $asesorId) {
        $pagosQuery->whereHas('cuotaGrupal.prestamo.grupo', function($query) use ($asesorId) {
            $query->where('asesor_id', $asesorId);
        });
    } elseif ($canViewAllActivity && $asesorId) {
        $pagosQuery->whereHas('cuotaGrupal.prestamo.grupo', function($query) use ($asesorId) {
            $query->where('asesor_id', $asesorId);
        });
    }
    $pagos = $pagosQuery->select('id', 'cuota_grupal_id', 'created_at', 'updated_at', 'monto_pagado', 'monto_mora_pagada', 'estado_pago', 'tipo_pago', 'fecha_pago')
        ->orderBy('updated_at', 'desc')
        ->limit(15)
        ->get()->map(function($p) {
        $nombreGrupo = optional($p->cuotaGrupal->prestamo->grupo ?? null)->nombre_grupo ?? 'Grupo no encontrado';
        $nombreAsesor = optional($p->cuotaGrupal->prestamo->grupo->asesor->persona ?? null)->nombre . ' ' . optional($p->cuotaGrupal->prestamo->grupo->asesor->persona ?? null)->apellidos;
        $monto = $p->monto_pagado ? 'S/ ' . number_format($p->monto_pagado, 2) : 'Sin monto';
        $monteMora = $p->monto_mora_pagada ? ' (Mora: S/ ' . number_format($p->monto_mora_pagada, 2) . ')' : '';
        $estado = $p->estado_pago ?? 'Sin estado';
        $tipo = $p->tipo_pago ?? 'Sin tipo';
        $fecha_pago = $p->fecha_pago ? ' | Fecha: ' . \Carbon\Carbon::parse($p->fecha_pago)->format('d/m/Y') : '';
        
        return [
            'modulo' => 'Pago',
            'nombre' => "Pago - {$nombreGrupo}",
            'detalle' => "{$monto}{$monteMora} | {$tipo} | Estado: {$estado}{$fecha_pago}" . ($nombreAsesor ? " | Asesor: {$nombreAsesor}" : ""),
            'accion' => $p->created_at == $p->updated_at ? 'Creado' : 'Modificado',
            'fecha' => $p->created_at == $p->updated_at ? $p->created_at : $p->updated_at,
            'icono' => 'credit-card',
            'color' => $p->created_at == $p->updated_at ? 'bg-violet-50 text-violet-600 border-violet-200' : 'bg-amber-50 text-amber-600 border-amber-200',
            'bg_gradient' => $p->created_at == $p->updated_at ? 'from-violet-50 to-violet-100' : 'from-amber-50 to-amber-100',
            'url' => route('filament.dashboard.resources.pagos.edit', $p->id),
        ];
    });
    $movimientos = $movimientos->concat($pagos);
} catch (\Exception $e) {
    \Log::error('Error loading pagos for dashboard: ' . $e->getMessage());
}

try {
    // Préstamos con más detalles
    $prestamosQuery = \App\Models\Prestamo::with(['grupo.asesor.persona']);
    if (!$canViewAllActivity && $asesorId) {
        $prestamosQuery->whereHas('grupo', function($query) use ($asesorId) {
            $query->where('asesor_id', $asesorId);
        });
    } elseif ($canViewAllActivity && $asesorId) {
        $prestamosQuery->whereHas('grupo', function($query) use ($asesorId) {
            $query->where('asesor_id', $asesorId);
        });
    }
    $prestamos = $prestamosQuery->select('id', 'grupo_id', 'created_at', 'updated_at', 'monto_prestado_total', 'monto_devolver', 'tasa_interes', 'cantidad_cuotas', 'estado', 'fecha_prestamo')
        ->orderBy('updated_at', 'desc')
        ->limit(10)
        ->get()->map(function($pr) {
        $nombreGrupo = optional($pr->grupo)->nombre_grupo ?? 'Grupo no encontrado';
        $nombreAsesor = optional($pr->grupo->asesor->persona ?? null)->nombre . ' ' . optional($pr->grupo->asesor->persona ?? null)->apellidos;
        $monto = $pr->monto_prestado_total ? 'S/ ' . number_format($pr->monto_prestado_total, 2) : 'Sin monto';
        $montoDevolver = $pr->monto_devolver ? ' (Devolver: S/ ' . number_format($pr->monto_devolver, 2) . ')' : '';
        $tasa = $pr->tasa_interes ? $pr->tasa_interes . '%' : 'Sin tasa';
        $cuotas = $pr->cantidad_cuotas ? $pr->cantidad_cuotas . ' cuotas' : 'Sin cuotas';
        $estado = $pr->estado ?? 'Sin estado';
        $fechaPrestamo = $pr->fecha_prestamo ? ' | Fecha: ' . \Carbon\Carbon::parse($pr->fecha_prestamo)->format('d/m/Y') : '';
        
        return [
            'modulo' => 'Préstamo',
            'nombre' => "Préstamo - {$nombreGrupo}",
            'detalle' => "{$monto}{$montoDevolver} | Tasa: {$tasa} | {$cuotas} | Estado: {$estado}{$fechaPrestamo}" . ($nombreAsesor ? " | Asesor: {$nombreAsesor}" : ""),
            'accion' => $pr->created_at == $pr->updated_at ? 'Creado' : 'Modificado',
            'fecha' => $pr->created_at == $pr->updated_at ? $pr->created_at : $pr->updated_at,
            'icono' => 'banknotes',
            'color' => $pr->created_at == $pr->updated_at ? 'bg-purple-50 text-purple-600 border-purple-200' : 'bg-amber-50 text-amber-600 border-amber-200',
            'bg_gradient' => $pr->created_at == $pr->updated_at ? 'from-purple-50 to-purple-100' : 'from-amber-50 to-amber-100',
            'url' => route('filament.dashboard.resources.prestamos.edit', $pr->id),
        ];
    });
    $movimientos = $movimientos->concat($prestamos);
} catch (\Exception $e) {
    \Log::error('Error loading prestamos for dashboard: ' . $e->getMessage());
}

try {
    // Personas - Solo para admins y cuando no haya filtro específico de asesor
    if ($canViewAllActivity && !$asesorId) {
        $personasQuery = \App\Models\Persona::query();
        $personas = $personasQuery->select('id', 'nombre', 'apellidos', 'created_at', 'updated_at', 'DNI', 'celular')
            ->orderBy('updated_at', 'desc')
            ->limit(5)
            ->get()->map(function($p) {
            $dni = $p->DNI ?? 'Sin DNI';
            $celular = $p->celular ?? 'Sin celular';
            
            return [
                'modulo' => 'Persona',
                'nombre' => trim($p->nombre . ' ' . $p->apellidos),
                'detalle' => "DNI: {$dni} | Celular: {$celular}",
                'accion' => $p->created_at == $p->updated_at ? 'Creado' : 'Modificado',
                'fecha' => $p->created_at == $p->updated_at ? $p->created_at : $p->updated_at,
                'icono' => 'id-card',
                'color' => $p->created_at == $p->updated_at ? 'bg-orange-50 text-orange-600 border-orange-200' : 'bg-amber-50 text-amber-600 border-amber-200',
                'bg_gradient' => $p->created_at == $p->updated_at ? 'from-orange-50 to-orange-100' : 'from-amber-50 to-amber-100',
                'url' => '#',
            ];
        });
        $movimientos = $movimientos->concat($personas);
    }
} catch (\Exception $e) {
    \Log::error('Error loading personas for dashboard: ' . $e->getMessage());
}

try {
    // Retanqueos con más detalles
    $retanqueosQuery = \App\Models\Retanqueo::with(['grupo.asesor.persona']);
    if (!$canViewAllActivity && $asesorId) {
        $retanqueosQuery->where('asesor_id', $asesorId);
    } elseif ($canViewAllActivity && $asesorId) {
        $retanqueosQuery->where('asesor_id', $asesorId);
    }
    $retanqueos = $retanqueosQuery->select('id', 'grupo_id', 'created_at', 'updated_at', 'asesor_id', 'monto_retanqueado', 'estado')
        ->orderBy('updated_at', 'desc')
        ->limit(10)
        ->get()->map(function($r) {
        $nombreGrupo = optional($r->grupo)->nombre_grupo ?? 'Grupo no encontrado';
        $nombreAsesor = optional($r->grupo->asesor->persona ?? null)->nombre . ' ' . optional($r->grupo->asesor->persona ?? null)->apellidos;
        $monto = $r->monto_retanqueado ? 'S/ ' . number_format($r->monto_retanqueado, 2) : 'Sin monto';
        $estado = $r->estado ?? 'Sin estado';
        
        return [
            'modulo' => 'Retanqueo',
            'nombre' => "Retanqueo - {$nombreGrupo}",
            'detalle' => "{$monto} | Estado: {$estado}" . ($nombreAsesor ? " | Asesor: {$nombreAsesor}" : ""),
            'accion' => $r->created_at == $r->updated_at ? 'Creado' : 'Modificado',
            'fecha' => $r->created_at == $r->updated_at ? $r->created_at : $r->updated_at,
            'icono' => 'refresh',
            'color' => $r->created_at == $r->updated_at ? 'bg-pink-50 text-pink-600 border-pink-200' : 'bg-amber-50 text-amber-600 border-amber-200',
            'bg_gradient' => $r->created_at == $r->updated_at ? 'from-pink-50 to-pink-100' : 'from-amber-50 to-amber-100',
            'url' => route('filament.dashboard.resources.retanqueos.edit', $r->id),
        ];
    });
    $movimientos = $movimientos->concat($retanqueos);
} catch (\Exception $e) {
    \Log::error('Error loading retanqueos for dashboard: ' . $e->getMessage());
}

try {
    // Préstamos Individuales con más detalles
    $prestamosIndividualesQuery = \App\Models\PrestamoIndividual::with(['cliente.persona', 'cliente.asesor.persona', 'prestamo.grupo']);
    if (!$canViewAllActivity && $asesorId) {
        $prestamosIndividualesQuery->whereHas('cliente', function($query) use ($asesorId) {
            $query->where('asesor_id', $asesorId);
        });
    } elseif ($canViewAllActivity && $asesorId) {
        $prestamosIndividualesQuery->whereHas('cliente', function($query) use ($asesorId) {
            $query->where('asesor_id', $asesorId);
        });
    }
    $prestamosIndividuales = $prestamosIndividualesQuery->select('id', 'prestamo_id', 'cliente_id', 'created_at', 'updated_at', 'monto_prestado_individual', 'monto_devolver_individual', 'estado')
        ->orderBy('updated_at', 'desc')
        ->limit(10)
        ->get()->map(function($pi) {
        $nombreCliente = optional($pi->cliente->persona ?? null)->nombre . ' ' . optional($pi->cliente->persona ?? null)->apellidos;
        $nombreGrupo = optional($pi->prestamo->grupo ?? null)->nombre_grupo ?? 'Grupo no encontrado';
        $nombreAsesor = optional($pi->cliente->asesor->persona ?? null)->nombre . ' ' . optional($pi->cliente->asesor->persona ?? null)->apellidos;
        $monto = $pi->monto_prestado_individual ? 'S/ ' . number_format($pi->monto_prestado_individual, 2) : 'Sin monto';
        $montoDevolver = $pi->monto_devolver_individual ? ' (Devolver: S/ ' . number_format($pi->monto_devolver_individual, 2) . ')' : '';
        $estado = $pi->estado ?? 'Sin estado';
        
        return [
            'modulo' => 'Préstamo Individual',
            'nombre' => "{$nombreCliente} - {$nombreGrupo}",
            'detalle' => "{$monto}{$montoDevolver} | Estado: {$estado}" . ($nombreAsesor ? " | Asesor: {$nombreAsesor}" : ""),
            'accion' => $pi->created_at == $pi->updated_at ? 'Creado' : 'Modificado',
            'fecha' => $pi->created_at == $pi->updated_at ? $pi->created_at : $pi->updated_at,
            'icono' => 'user-circle',
            'color' => $pi->created_at == $pi->updated_at ? 'bg-cyan-50 text-cyan-600 border-cyan-200' : 'bg-amber-50 text-amber-600 border-amber-200',
            'bg_gradient' => $pi->created_at == $pi->updated_at ? 'from-cyan-50 to-cyan-100' : 'from-amber-50 to-amber-100',
            'url' => '#', // Aquí puedes agregar la URL si tienes un recurso para préstamos individuales
        ];
    });
    $movimientos = $movimientos->concat($prestamosIndividuales);
} catch (\Exception $e) {
    \Log::error('Error loading prestamos individuales for dashboard: ' . $e->getMessage());
}

try {
    // Retanqueos Individuales con más detalles
    $retanqueosIndividualesQuery = \App\Models\RetanqueoIndividual::with(['cliente.persona', 'cliente.asesor.persona', 'retanqueo.grupo']);
    if (!$canViewAllActivity && $asesorId) {
        $retanqueosIndividualesQuery->whereHas('cliente', function($query) use ($asesorId) {
            $query->where('asesor_id', $asesorId);
        });
    } elseif ($canViewAllActivity && $asesorId) {
        $retanqueosIndividualesQuery->whereHas('cliente', function($query) use ($asesorId) {
            $query->where('asesor_id', $asesorId);
        });
    }
    $retanqueosIndividuales = $retanqueosIndividualesQuery->select('id', 'retanqueo_id', 'cliente_id', 'created_at', 'updated_at', 'monto_solicitado', 'monto_desembolsar', 'estado_retanqueo_individual')
        ->orderBy('updated_at', 'desc')
        ->limit(10)
        ->get()->map(function($ri) {
        $nombreCliente = optional($ri->cliente->persona ?? null)->nombre . ' ' . optional($ri->cliente->persona ?? null)->apellidos;
        $nombreGrupo = optional($ri->retanqueo->grupo ?? null)->nombre_grupo ?? 'Grupo no encontrado';
        $nombreAsesor = optional($ri->cliente->asesor->persona ?? null)->nombre . ' ' . optional($ri->cliente->asesor->persona ?? null)->apellidos;
        $montoSolicitado = $ri->monto_solicitado ? 'Solicitado: S/ ' . number_format($ri->monto_solicitado, 2) : 'Sin monto solicitado';
        $montoDesembolsar = $ri->monto_desembolsar ? ' | Desembolsar: S/ ' . number_format($ri->monto_desembolsar, 2) : '';
        $estado = $ri->estado_retanqueo_individual ?? 'Sin estado';
        
        return [
            'modulo' => 'Retanqueo Individual',
            'nombre' => "{$nombreCliente} - {$nombreGrupo}",
            'detalle' => "{$montoSolicitado}{$montoDesembolsar} | Estado: {$estado}" . ($nombreAsesor ? " | Asesor: {$nombreAsesor}" : ""),
            'accion' => $ri->created_at == $ri->updated_at ? 'Creado' : 'Modificado',
            'fecha' => $ri->created_at == $ri->updated_at ? $ri->created_at : $ri->updated_at,
            'icono' => 'arrow-path',
            'color' => $ri->created_at == $ri->updated_at ? 'bg-teal-50 text-teal-600 border-teal-200' : 'bg-amber-50 text-amber-600 border-amber-200',
            'bg_gradient' => $ri->created_at == $ri->updated_at ? 'from-teal-50 to-teal-100' : 'from-amber-50 to-amber-100',
            'url' => '#', // Aquí puedes agregar la URL si tienes un recurso para retanqueos individuales
        ];
    });
    $movimientos = $movimientos->concat($retanqueosIndividuales);
} catch (\Exception $e) {
    \Log::error('Error loading retanqueos individuales for dashboard: ' . $e->getMessage());
}

// Ordenar por fecha descendente y tomar solo las últimas 20 (aumentamos a 20)
$movimientos = $movimientos->sortByDesc('fecha')->take(20)->values(); // Agregamos values() para reindexar

// Obtener lista de asesores para el filtro (solo para jefes y super admin)
$asesores = collect([]);
if ($canViewAllActivity) {
    $asesores = \App\Models\Asesor::with('persona')
        ->where('estado_asesor', 'Activo')
        ->get()
        ->map(function($asesor) {
            return [
                'id' => $asesor->id,
                'nombre' => optional($asesor->persona)->nombre . ' ' . optional($asesor->persona)->apellidos
            ];
        });
}

// Debug mejorado: Verificar que el código se ejecuta y los datos están disponibles
if (config('app.debug')) {
    \Log::info('Dashboard: Actividad reciente cargada', [
        'user_id' => $user->id,
        'user_name' => $user->name,
        'can_view_all' => $canViewAllActivity,
        'asesor_id' => $asesorId,
        'filtro_asesor_id' => $filtroAsesorId,
        'movimientos_count' => $movimientos->count(),
        'movimientos_sample' => $movimientos->take(3)->toArray(), // Muestra de datos
        'movimientos_types' => $movimientos->groupBy('modulo')->map(fn($items) => $items->count())->toArray()
    ]);
}

// Verificar que todos los elementos tienen los campos requeridos
$movimientos = $movimientos->map(function($mov) {
    return array_merge([
        'modulo' => 'Sin definir',
        'nombre' => 'Sin nombre',
        'detalle' => 'Sin detalle',
        'accion' => 'Sin acción',
        'fecha' => now(),
        'icono' => 'user',
        'color' => 'bg-gray-50 text-gray-600 border-gray-200',
        'bg_gradient' => 'from-gray-50 to-gray-100',
        'url' => '#',
    ], $mov);
});
@endphp

<div class="mb-8 transform transition-all duration-300 hover:scale-[1.01]">
    <div class="bg-white dark:bg-gray-800/50 backdrop-blur-sm rounded-3xl shadow-2xl border border-gray-100 dark:border-gray-700/50 overflow-hidden">        {{-- Header con gradiente y filtro --}}
<div class="relative bg-gradient-to-r from-blue-50 via-indigo-50 to-purple-50 dark:from-blue-900 dark:via-indigo-900 dark:to-purple-900 px-6 py-4 border-b border-gray-200 dark:border-gray-700">
    <!-- Overlay para mejorar contraste -->
    <div class="absolute inset-0 bg-white/80 dark:bg-black/30"></div>

    <div class="relative z-10 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <div class="p-2 bg-indigo-100 dark:bg-white/20 rounded-xl backdrop-blur-sm border border-indigo-200 dark:border-white/30">
                <svg class="w-6 h-6 text-indigo-600 dark:text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                </svg>
            </div>
            <h3 class="text-xl font-bold text-gray-900 drop-shadow-lg">
                {{ $canViewAllActivity ? 'Actividad Reciente del Sistema' : 'Mi Actividad Reciente' }}
            </h3>
        </div>
        <div class="flex items-center gap-4">
            {{-- Filtro por asesor (solo para jefes y super admin) --}}
            @if($canViewAllActivity && $asesores->count() > 0)
                <form method="GET" class="flex items-center gap-2">
                    <select name="filtro_asesor_id" onchange="this.form.submit()" 
                            class="text-sm bg-white border border-gray-300 text-gray-800 rounded-lg px-3 py-1 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 font-medium shadow-sm">
                        <option value="" {{ !request('filtro_asesor_id') ? 'selected' : '' }} style="color: #374151; background-color: #ffffff;">Todos los asesores</option>
                        @foreach($asesores as $asesorOption)
                            <option value="{{ $asesorOption['id'] }}" 
                                    {{ request('filtro_asesor_id') == $asesorOption['id'] ? 'selected' : '' }}
                                    style="color: #1f2937; background-color: #ffffff;">
                                {{ $asesorOption['nombre'] }}
                            </option>
                        @endforeach
                    </select>
                    @if(request('filtro_asesor_id'))
                        <a href="{{ request()->url() }}" 
                           class="text-gray-600 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200 transition-colors text-sm bg-gray-100 dark:bg-gray-700 rounded-full p-1 shadow-sm">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </a>
                    @endif
                </form>
            @endif
            
            <div class="flex items-center gap-2">
                <span class="text-gray-900 text-sm drop-shadow-md font-semibold">Últimas {{ $movimientos->count() }} actividades</span>
                <div class="w-2 h-2 bg-green-400 rounded-full animate-pulse"></div>
            </div>
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
                                    @case('user-circle')
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M17.982 18.725A7.488 7.488 0 0012 15.75a7.488 7.488 0 00-5.982 2.975m11.963 0a9 9 0 10-11.963 0m11.963 0A8.966 8.966 0 0112 21a8.966 8.966 0 01-5.982-2.275M15 9.75a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                        @break
                                    @case('arrow-path')
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
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
                            {{-- Nombre del registro --}}
                            <p class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1">{{ $mov['nombre'] }}</p>
                            {{-- Detalles adicionales --}}
                            <p class="text-xs text-gray-500 dark:text-gray-400 line-clamp-2">{{ $mov['detalle'] ?? '' }}</p>
                        </div>

                        {{-- Fecha con formato mejorado y enlace --}}
                        <div class="text-right flex-shrink-0 flex flex-col items-end gap-2">
                            <div class="text-xs text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-700/50 px-3 py-1 rounded-full font-medium">
                                {{ \Carbon\Carbon::parse($mov['fecha'])->diffForHumans() }}
                            </div>
                            <div class="text-xs text-gray-400 dark:text-gray-500">
                                {{ \Carbon\Carbon::parse($mov['fecha'])->format('d/m H:i') }}
                            </div>
                            {{-- Enlace para ver detalles --}}
                            @if(isset($mov['url']) && $mov['url'] !== '#')
                                <a href="{{ $mov['url'] }}" 
                                   class="inline-flex items-center gap-1 text-xs text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300 transition-colors">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                    </svg>
                                    Ver
                                </a>
                            @endif
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

/* Mejora para el select de filtro de asesores */
select[name="filtro_asesor_id"] option {
    color: #1f2937 !important;
    background-color: #ffffff !important;
    padding: 8px !important;
}

select[name="filtro_asesor_id"] option:hover {
    background-color: #f3f4f6 !important;
    color: #1a357e !important;
}

select[name="filtro_asesor_id"] option:checked {
    background-color: #247fa8 !important;
    color: #ffffff !important;
}

/* Mejora para textos largos en detalles */
.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    line-height: 1.4;
    max-height: 2.8em;
}

/* Efecto hover mejorado */
.group:hover .line-clamp-2 {
    -webkit-line-clamp: unset;
    max-height: none;
    overflow: visible;
}

/* Animación suave para elementos de actividad reciente */
@keyframes slideInLeft {
    from {
        opacity: 0;
        transform: translateX(-20px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

.group {
    animation: slideInLeft 0.3s ease-out;
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
