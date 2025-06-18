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

        // CORREGIDO: Estadísticas de cuotas basadas en estado_cuota_grupal
        $cuotasQuery = CuotasGrupales::whereIn('prestamo_id', $prestamoIds);

        // Aplicar filtros de fecha a las cuotas si existen
        if ($desde) {
            $cuotasQuery->whereDate('fecha_vencimiento', '>=', $desde);
        }
        if ($hasta) {
            $cuotasQuery->whereDate('fecha_vencimiento', '<=', $hasta);
        }

        // Contar cuotas por estado real de la cuota
        $cuotasVigentes = (clone $cuotasQuery)->where('estado_cuota_grupal', 'vigente')->count();
        $cuotasEnMora = (clone $cuotasQuery)->where('estado_cuota_grupal', 'mora')->count();
        $cuotasCanceladas = (clone $cuotasQuery)->where('estado_cuota_grupal', 'cancelada')->count();

        // Calcular monto total de mora de forma más eficiente
        $cuotasConMora = (clone $cuotasQuery)->with('mora')
            ->where('estado_cuota_grupal', 'mora')
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

        // CORREGIDO: Datos para gráfico de estado de cuotas (basado en estado_cuota_grupal)
        $cuotasEstadosBar = [
            'Vigentes' => $cuotasVigentes,
            'En Mora' => $cuotasEnMora,
            'Canceladas' => $cuotasCanceladas,
        ];

        // Datos para gráfico de pagos (separado del anterior)
        $pagosPie = [
            'Aprobados' => $pagosAprobados,
            'Pendientes' => $pagosPendientes,
            'Rechazados' => $pagosRechazados,
        ];

        // Obtener pagos por fecha con MONTO TOTAL
        $pagosPorFecha = (clone $pagosQuery)
            ->where('estado_pago', 'Aprobado')
            ->selectRaw('DATE(fecha_pago) as fecha, SUM(monto_pagado) as total')
            ->groupBy('fecha')
            ->orderBy('fecha')
            ->pluck('total', 'fecha')
            ->map(function($monto) {
                return (float) $monto;
            });

        // Optimizar consulta de grupos con filtro de fechas en cuotas vencidas
        $gruposEnMoraQuery = $gruposQuery->with([
            'clientes',
            'prestamos' => function($query) {
                $query->orderByDesc('id');
            },
            'prestamos.cuotasGrupales' => function($query) use ($desde, $hasta) {
                if ($desde) {
                    $query->whereDate('fecha_vencimiento', '>=', $desde);
                }
                if ($hasta) {
                    $query->whereDate('fecha_vencimiento', '<=', $hasta);
                }
            },
            'prestamos.cuotasGrupales.mora'
        ]);

        $gruposEnMora = $gruposEnMoraQuery->get()->filter(function($grupo) use ($desde, $hasta) {
            $prestamoPrincipal = $grupo->prestamos->first();

            if ($prestamoPrincipal) {
                // CORREGIDO: Verificar que tiene cuotas con estado_cuota_grupal = 'mora'
                $tieneCuotaEnMora = $prestamoPrincipal->cuotasGrupales->contains(function($cuota) use ($desde, $hasta) {
                    // Verificar que la cuota está en mora según estado_cuota_grupal
                    if ($cuota->estado_cuota_grupal !== 'mora') {
                        return false;
                    }

                    // Si hay filtros de fecha, verificar que la fecha de vencimiento esté en el rango
                    if ($desde && $cuota->fecha_vencimiento < $desde) {
                        return false;
                    }
                    if ($hasta && $cuota->fecha_vencimiento > $hasta) {
                        return false;
                    }

                    return true;
                });

                return $tieneCuotaEnMora;
            }

            return false;
        })->map(function($grupo) use ($desde, $hasta) {
            $numeroIntegrantes = $grupo->clientes->count();
            $prestamoPrincipal = $grupo->prestamos->first();

            // Calcular el monto total de mora para este grupo
            $montoMoraGrupo = 0;
            if ($prestamoPrincipal) {
                foreach ($prestamoPrincipal->cuotasGrupales as $cuota) {
                    if ($cuota->estado_cuota_grupal === 'mora' && $cuota->mora && $cuota->mora->estado_mora === 'pendiente') {
                        // Verificar filtros de fecha en fecha de vencimiento
                        $incluirCuota = true;
                        if ($desde && $cuota->fecha_vencimiento < $desde) {
                            $incluirCuota = false;
                        }
                        if ($hasta && $cuota->fecha_vencimiento > $hasta) {
                            $incluirCuota = false;
                        }

                        if ($incluirCuota) {
                            $montoMoraGrupo += $cuota->mora->getMontoMoraCalculadoAttribute();
                        }
                    }
                }
            }

            return [
                'nombre' => $grupo->nombre_grupo,
                'numero_integrantes' => $numeroIntegrantes,
                'estado' => 'En mora',
                'monto_mora' => $montoMoraGrupo,
            ];
        })->filter(function($grupo) {
            return $grupo['monto_mora'] > 0;
        })->sortByDesc('monto_mora');

        // Calcular mora por grupo con filtro de fechas
        $moraPorGrupo = $gruposQuery->with(['prestamos.cuotasGrupales' => function($query) use ($desde, $hasta) {
                if ($desde) {
                    $query->whereDate('fecha_vencimiento', '>=', $desde);
                }
                if ($hasta) {
                    $query->whereDate('fecha_vencimiento', '<=', $hasta);
                }
            }, 'prestamos.cuotasGrupales.mora'])
            ->get()
            ->mapWithKeys(function($grupo) use ($desde, $hasta) {
                $mora = 0;
                foreach ($grupo->prestamos as $prestamo) {
                    foreach ($prestamo->cuotasGrupales as $cuota) {
                        if ($cuota->estado_cuota_grupal === 'mora' && $cuota->mora && $cuota->mora->estado_mora === 'pendiente') {
                            // Verificar filtros de fecha en fecha de vencimiento
                            $incluirCuota = true;
                            if ($desde && $cuota->fecha_vencimiento < $desde) {
                                $incluirCuota = false;
                            }
                            if ($hasta && $cuota->fecha_vencimiento > $hasta) {
                                $incluirCuota = false;
                            }

                            if ($incluirCuota) {
                                $mora += $cuota->mora->getMontoMoraCalculadoAttribute();
                            }
                        }
                    }
                }
                return [$grupo->nombre_grupo => $mora];
            })
            ->filter(function($mora) {
                return $mora > 0;
            })
            ->sortByDesc(function($value) {
                return $value;
            })
            ->take(10);

        return [
            'totalGrupos' => $totalGrupos,
            'totalClientes' => $totalClientes,
            'totalPrestamos' => $totalPrestamos,
            'totalPagosRegistrados' => $totalPagosRegistrados,
            'totalMorasHistoricas' => $totalMorasHistoricas,
            // CORREGIDO: Variables separadas para cuotas y pagos
            'cuotasVigentes' => $cuotasVigentes,
            'cuotasEnMora' => $cuotasEnMora,
            'cuotasCanceladas' => $cuotasCanceladas,
            'montoTotalMora' => $montoTotalMora,
            'pagosAprobados' => $pagosAprobados,
            'pagosPendientes' => $pagosPendientes,
            'pagosRechazados' => $pagosRechazados,
            'cuotasEstadosBar' => $cuotasEstadosBar, // Ahora basado en estado_cuota_grupal
            'pagosPorFecha' => $pagosPorFecha,
            'pagosPie' => $pagosPie, // Separado para pagos
            'grupos' => $gruposEnMora,
            'moraPorGrupo' => $moraPorGrupo,
        ];
    }
}
