<?php

namespace App\Filament\Dashboard\Pages;

use Illuminate\Support\Facades\Log;

use Filament\Pages\Page;
use App\Models\Mora;
use App\Models\CuotasGrupales;

class Moras extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-exclamation-circle';
    protected static ?string $navigationGroup = 'Operaciones';
    protected static string $view = 'filament.dashboard.pages.mora';
    protected static ?string $title = 'Moras';
    protected static ?int $navigationSort = 5;

    public function getViewData(): array
    {
        // Actualizar automáticamente el estado de cuotas vencidas
        // SOLO marcamos como mora las cuotas que realmente están vencidas (1 día después de fecha_vencimiento)
        $fechaVencimientoLimite = now()->subDay(); // Un día antes de hoy para dar 1 día de gracia

        // Agregar logs para debugging
        Log::info('Moras: Verificando cuotas vencidas', [
            'fecha_limite' => $fechaVencimientoLimite->toDateString(),
            'fecha_actual' => now()->toDateString()
        ]);

        $cuotasActualizadas = CuotasGrupales::where('estado_cuota_grupal', 'vigente')
            ->where('estado_pago', '!=', 'pagado')
            ->whereDate('fecha_vencimiento', '<=', $fechaVencimientoLimite)
            ->update(['estado_cuota_grupal' => 'mora']);

        Log::info('Moras: Cuotas marcadas como mora', [
            'cantidad' => $cuotasActualizadas
        ]);

        // Obtener cuotas que están realmente en mora (con al menos 1 día de gracia)
        $cuotasEnMora = CuotasGrupales::with('prestamo.grupo')
            ->where('estado_cuota_grupal', 'mora')
            ->get();

        /** @var CuotasGrupales $cuota */
        foreach ($cuotasEnMora as $cuota) {
            // Calcular días de atraso usando el método seguro del modelo Mora
            $diasAtraso = 0;
            if ($cuota->mora) {
                $diasAtraso = $cuota->mora->dias_atraso;
            } else {
                // Si no hay mora creada, calcular manualmente
                $fechaVencimiento = \Carbon\Carbon::parse($cuota->getRawOriginal('fecha_vencimiento'));
                $diasAtraso = now()->isAfter($fechaVencimiento->addDay())
                    ? now()->diffInDays($fechaVencimiento->addDay())
                    : 0;
            }


            $moraExistente = Mora::where('cuota_grupal_id', $cuota->id)->first();


            if (!$moraExistente || $moraExistente->estado_mora !== 'pagada') {
                $montoMora = Mora::calcularMontoMora($cuota, now(), $moraExistente->estado_mora ?? 'pendiente');

                Mora::updateOrCreate(
                    ['cuota_grupal_id' => $cuota->id],
                    [
                        'fecha_atraso' => now(),
                        'monto_mora' => $montoMora,
                        'estado_mora' => $moraExistente->estado_mora ?? 'pendiente',
                    ]
                );
            }
        }

        $user = request()->user();
        $query = CuotasGrupales::with(['mora', 'prestamo.grupo'])
            ->whereHas('mora', function ($moraQuery) use ($user) {
                $moraQuery->where(function ($subQuery) use ($user) {
                    $subQuery->visiblePorUsuario($user);
                });
            });


        $query = $this->aplicarFiltros($query);

        // Implementar paginación idéntica a Filament con selector de elementos por página
        $perPage = (int) request('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50, 100]) ? $perPage : 10; // Validar valores permitidos

        $cuotasMoraPaginadas = $query
            ->join('moras', 'cuotas_grupales.id', '=', 'moras.cuota_grupal_id')
            ->orderByRaw("CASE WHEN moras.estado_mora = 'pagada' THEN 1 ELSE 0 END ASC")
            ->orderBy('fecha_vencimiento', 'asc')
            ->select('cuotas_grupales.*')
            ->paginate($perPage)
            ->withQueryString(); // Mantener los filtros en la paginación

        return [
            'cuotas_mora' => $cuotasMoraPaginadas,
            'filtros_activos' => $this->obtenerFiltrosActivos()
        ];
    }

    private function aplicarFiltros($query)
    {

        if (request('grupo')) {
            $query->whereHas('prestamo.grupo', function ($q) {
                $q->where('nombre_grupo', 'like', '%' . request('grupo') . '%');
            });
        }


        if (request('desde')) {
            $query->whereDate('fecha_vencimiento', '>=', request('desde'));
        }
        if (request('hasta')) {
            $query->whereDate('fecha_vencimiento', '<=', request('hasta'));
        }


        if (request('monto') && is_numeric(request('monto'))) {
            $query->whereHas('mora', function ($q) {
                $q->whereRaw('ABS(monto_mora_calculado) >= ?', [floatval(request('monto'))]);
            });
        }

        if (request('estado_mora') && request('estado_mora') !== '') {
            $estado = request('estado_mora');
            // Mapear 'parcial' a 'parcialmente_pagada' para consistencia
            if ($estado === 'parcial') {
                $estado = 'parcialmente_pagada';
            }
            $estadosValidos = ['pendiente', 'pagada', 'parcialmente_pagada'];
            Log::info('Filtro estado_mora aplicado', ['estado' => $estado]);
            if (in_array($estado, $estadosValidos)) {
                // Solo mostrar cuotas que tengan una mora con el estado exacto
                $query->whereHas('mora', function ($q) use ($estado) {
                    $q->where('estado_mora', $estado);
                });
            } else {
                // Si el estado no es válido, forzar que no retorne nada
                $query->whereRaw('1 = 0');
            }
        }

        return $query;
    }

    private function obtenerFiltrosActivos()
    {
        $filtros = [];

        if (request('grupo')) {
            $filtros[] = 'Grupo: ' . request('grupo');
        }

        if (request('desde') || request('hasta')) {
            $fechas = [];
            if (request('desde'))
                $fechas[] = 'desde ' . request('desde');
            if (request('hasta'))
                $fechas[] = 'hasta ' . request('hasta');
            $filtros[] = 'Fechas: ' . implode(' ', $fechas);
        }

        if (request('monto')) {
            $filtros[] = 'Monto mínimo: S/ ' . number_format(request('monto'), 2);
        }

        if (request('estado_mora')) {
            $estados = [
                'pendiente' => 'Pendiente',
                'pagada' => 'Pagada',
                'parcial' => 'Parcial',
                'parcialmente_pagada' => 'Parcialmente Pagada'
            ];
            $filtros[] = 'Estado: ' . ($estados[request('estado_mora')] ?? request('estado_mora'));
        }

        return $filtros;
    }

    public function limpiarFiltros()
    {
        return redirect()->route('filament.dashboard.pages.moras');
    }
}
