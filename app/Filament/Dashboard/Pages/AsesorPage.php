<?php

namespace App\Filament\Dashboard\Pages;

use Filament\Pages\Page;
use App\Models\Grupo;
use App\Models\Mora;
use App\Models\Pago;
use App\Models\Cliente;
use App\Models\Prestamo;
use App\Models\Retanqueo;
use App\Models\CuotasGrupales;

use App\Models\Asesor as AsesorModel;

class AsesorPage  extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    protected static string $view = 'filament.dashboard.pages.asesor';
    protected static ?string $navigationLabel = 'Panel asesor';
    protected static ?string $title = 'PANEL ASESOR';


    public function getViewData(): array
    {
        $desde = request('desde');
        $hasta = request('hasta');
        $user = request()->user();

        $clientesQuery = Cliente::query();
        $prestamosQuery = Prestamo::query();
        $gruposQuery = Grupo::query();

        // Si es un asesor, filtrar por su ID
        if ($user->hasRole('Asesor')) {
            $asesor = AsesorModel::where('user_id', $user->id)->first();
            if ($asesor) {
                $clientesQuery->where('asesor_id', $asesor->id);
                $gruposQuery->whereHas('clientes', function ($q) use ($asesor) {
                    $q->where('asesor_id', $asesor->id);
                });
                $prestamosQuery->whereHas('grupo.clientes', function ($q) use ($asesor) {
                    $q->where('asesor_id', $asesor->id);
                });
            }
        }

        // Aplicar filtros de fecha si existen
        if ($desde) {
            $prestamosQuery->whereDate('created_at', '>=', $desde);
        }
        if ($hasta) {
            $prestamosQuery->whereDate('created_at', '<=', $hasta);
        }

        // Obtener IDs de préstamos una sola vez para reutilizar
        $prestamoIds = $prestamosQuery->pluck('id')->toArray();

        // Conteos básicos
        $totalClientes = $clientesQuery->count();
        $totalGrupos = $gruposQuery->count();
        $totalPrestamos = count($prestamoIds);
        $totalRetanqueos = Retanqueo::whereIn('prestamo_id', $prestamoIds)->count();

        // Estadísticas de moras - optimizado
        $cuotasQuery = CuotasGrupales::whereIn('prestamo_id', $prestamoIds);

        $cuotasEnMora = (clone $cuotasQuery)->whereHas('mora', function ($q) {
            $q->where('estado_mora', 'pendiente');
        })->count();

        // Calcular monto total de mora de forma más eficiente
        $cuotasConMora = (clone $cuotasQuery)->with('mora')
            ->whereHas('mora', function ($q) {
                $q->where('estado_mora', 'pendiente');
            })->get();

        $montoTotalMora = $cuotasConMora->sum(function ($cuota) {
            return $cuota->mora ? $cuota->mora->getMontoMoraCalculadoAttribute() : 0;
        });

        $totalMorasHistoricas = Mora::whereHas('cuotaGrupal', function ($q) use ($prestamoIds) {
            $q->whereIn('prestamo_id', $prestamoIds);
        })->count();

        // Estadísticas de pagos - optimizado
        $pagosQuery = Pago::whereHas('cuotaGrupal', function ($q) use ($prestamoIds) {
            $q->whereIn('prestamo_id', $prestamoIds);
        });

        $totalPagosRegistrados = $pagosQuery->count();
        $pagosAprobados = (clone $pagosQuery)->where('estado_pago', 'Aprobado')->count();
        $pagosPendientes = (clone $pagosQuery)->where('estado_pago', 'Pendiente')->count();
        $pagosRechazados = (clone $pagosQuery)->where('estado_pago', 'Rechazado')->count();

        // Datos para gráficos
        $cuotasEstadosBar = [
            'Pendientes' => $pagosPendientes,
            'En Mora' => $cuotasEnMora,
            'Pagadas' => $pagosAprobados,
        ];

        $pagosPie = [
            'Aprobados' => $pagosAprobados,
            'Pendientes' => $pagosPendientes,
            'Rechazados' => $pagosRechazados,
        ];

        // CORREGIDO: Obtener pagos por fecha con MONTO TOTAL en lugar de COUNT
        $pagosPorFecha = (clone $pagosQuery)
            ->where('estado_pago', 'Aprobado') // Solo pagos aprobados para el monto
            ->selectRaw('DATE(fecha_pago) as fecha, SUM(monto_pagado) as total')
            ->groupBy('fecha')
            ->orderBy('fecha')
            ->pluck('total', 'fecha')
            ->map(function($monto) {
                return (float) $monto; // Convertir a float para evitar problemas con decimales
            });

        // Optimizar consulta de grupos con eager loading - SOLO GRUPOS EN MORA
        $gruposEnMora = $gruposQuery->with([
            'clientes',
            'prestamos' => function($query) {
                $query->orderByDesc('id');
            },
            'prestamos.cuotasGrupales.mora'
        ])->get()->filter(function($grupo) {
            $prestamoPrincipal = $grupo->prestamos->first();

            if ($prestamoPrincipal) {
                return $prestamoPrincipal->cuotasGrupales->contains(function($cuota) {
                    return $cuota->mora && $cuota->mora->estado_mora === 'pendiente';
                });
            }

            return false;
        })->map(function($grupo) {
            $numeroIntegrantes = $grupo->clientes->count();
            $prestamoPrincipal = $grupo->prestamos->first();

            // Calcular el monto total de mora para este grupo
            $montoMoraGrupo = 0;
            if ($prestamoPrincipal) {
                foreach ($prestamoPrincipal->cuotasGrupales as $cuota) {
                    if ($cuota->mora && $cuota->mora->estado_mora === 'pendiente') {
                        $montoMoraGrupo += $cuota->mora->getMontoMoraCalculadoAttribute();
                    }
                }
            }

            return [
                'nombre' => $grupo->nombre_grupo,
                'numero_integrantes' => $numeroIntegrantes,
                'estado' => 'En mora',
                'monto_mora' => $montoMoraGrupo,
            ];
        })->sortByDesc('monto_mora'); // Ordenar por monto de mora descendente

        // Calcular mora por grupo - optimizado
        $moraPorGrupo = $gruposQuery->with(['prestamos.cuotasGrupales.mora'])
            ->get()
            ->mapWithKeys(function($grupo) {
                $mora = 0;
                foreach ($grupo->prestamos as $prestamo) {
                    foreach ($prestamo->cuotasGrupales as $cuota) {
                        if ($cuota->mora && $cuota->mora->estado_mora === 'pendiente') {
                            $mora += $cuota->mora->getMontoMoraCalculadoAttribute();
                        }
                    }
                }
                return [$grupo->nombre_grupo => $mora];
            })
            ->filter(function($mora) {
                return $mora > 0; // Solo grupos con mora
            })
            ->sortByDesc(function($value) {
                return $value;
            })
            ->take(10);

        return [
            'totalGrupos' => $totalGrupos,
            'totalClientes' => $totalClientes,
            'totalPrestamos' => $totalPrestamos,
            'totalRetanqueos' => $totalRetanqueos,
            'totalPagosRegistrados' => $totalPagosRegistrados,
            'totalMorasHistoricas' => $totalMorasHistoricas,
            'cuotasEnMora' => $cuotasEnMora,
            'montoTotalMora' => $montoTotalMora,
            'pagosAprobados' => $pagosAprobados,
            'pagosPendientes' => $pagosPendientes,
            'pagosRechazados' => $pagosRechazados,
            'cuotasEstadosBar' => $cuotasEstadosBar,
            'pagosPorFecha' => $pagosPorFecha,
            'pagosPie' => $pagosPie,
            'grupos' => $gruposEnMora, // Cambiado a grupos en mora únicamente
            'moraPorGrupo' => $moraPorGrupo,
        ];
    }
}
