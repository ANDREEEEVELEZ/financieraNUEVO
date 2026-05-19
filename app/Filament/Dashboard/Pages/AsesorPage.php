<?php

namespace App\Filament\Dashboard\Pages;

use Filament\Pages\Page;
use App\Models\Grupo;
use App\Models\Mora;
use App\Models\Pago;
use App\Models\Cliente;
use App\Models\Prestamo;
use App\Models\CuotasGrupales;

use App\Models\Asesor as AsesorModel;

class AsesorPage extends Page
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

        // Cachear asesor al inicio para evitar queries repetidas
        $asesor = null;
        if ($user->hasRole('Asesor')) {
            $asesor = AsesorModel::where('user_id', $user->id)->first();
        }

        if ($desde) {
            $clientesQuery->whereDate('created_at', '>=', $desde);
        }
        if ($hasta) {
            $clientesQuery->whereDate('created_at', '<=', $hasta);
        }

        $gruposQueryBase = Grupo::query();

        $gruposQuery = (clone $gruposQueryBase);

        if ($desde) {
            $gruposQuery->whereDate('created_at', '>=', $desde);
        }
        if ($hasta) {
            $gruposQuery->whereDate('created_at', '<=', $hasta);
        }




        if ($asesor) {
            $clientesQuery->where('asesor_id', $asesor->id);
            $gruposQuery->whereHas('clientes', function ($q) use ($asesor) {
                $q->where('asesor_id', $asesor->id);
            });
            $prestamosQuery->whereHas('grupo.clientes', function ($q) use ($asesor) {
                $q->where('asesor_id', $asesor->id);
            });
        }



        if ($desde) {
            $prestamosQuery->whereDate('created_at', '>=', $desde);
        }
        if ($hasta) {
            $prestamosQuery->whereDate('created_at', '<=', $hasta);
        }

        $prestamoIds = $prestamosQuery->pluck('id')->toArray();


        $totalClientes = $clientesQuery->count();
        $totalGrupos = $gruposQuery->count();
        $totalPrestamos = count($prestamoIds);


        $cuotasQuery = CuotasGrupales::whereIn('prestamo_id', $prestamoIds);


        if ($desde) {
            $cuotasQuery->whereDate('fecha_vencimiento', '>=', $desde);
        }
        if ($hasta) {
            $cuotasQuery->whereDate('fecha_vencimiento', '<=', $hasta);
        }


        // Optimización: una sola query para contar estados de cuotas
        $cuotasEstados = (clone $cuotasQuery)
            ->selectRaw('estado_cuota_grupal, COUNT(*) as total')
            ->groupBy('estado_cuota_grupal')
            ->pluck('total', 'estado_cuota_grupal');

        $cuotasVigentes = $cuotasEstados['vigente'] ?? 0;
        $cuotasEnMora = $cuotasEstados['mora'] ?? 0;
        $cuotasCanceladas = $cuotasEstados['cancelada'] ?? 0;


        $cuotasConMora = (clone $cuotasQuery)->with('mora')
            ->where('estado_cuota_grupal', 'mora')
            ->whereHas('mora', function ($q) {
                $q->where('estado_mora', 'pendiente');
            })->get();

        $montoTotalMora = $cuotasConMora->sum(function ($cuota) {
            return $cuota->mora ? abs($cuota->mora->monto_mora_calculado) : 0;
        });

        $totalMorasHistoricas = Mora::whereHas('cuotaGrupal', function ($q) use ($prestamoIds) {
            $q->whereIn('prestamo_id', $prestamoIds);
        })->count();


        $pagosQuery = Pago::whereHas('cuotaGrupal', function ($q) use ($prestamoIds) {
            $q->whereIn('prestamo_id', $prestamoIds);
        });

        // Optimización: una sola query para contar estados de pagos
        $pagosEstados = (clone $pagosQuery)
            ->selectRaw('estado_pago, COUNT(*) as total')
            ->groupBy('estado_pago')
            ->pluck('total', 'estado_pago');

        $totalPagosRegistrados = $pagosEstados->sum();
        $pagosAprobados = $pagosEstados['Aprobado'] ?? 0;
        $pagosPendientes = $pagosEstados['Pendiente'] ?? 0;
        $pagosRechazados = $pagosEstados['Rechazado'] ?? 0;


        $cuotasEstadosBar = [
            'Vigentes' => $cuotasVigentes,
            'En Mora' => $cuotasEnMora,
            'Canceladas' => $cuotasCanceladas,
        ];


        $pagosPie = [
            'Aprobados' => $pagosAprobados,
            'Pendientes' => $pagosPendientes,
            'Rechazados' => $pagosRechazados,
        ];

        $pagosPorFecha = (clone $pagosQuery)
            ->where('estado_pago', 'Aprobado')
            ->selectRaw('DATE(fecha_pago) as fecha, SUM(monto_pagado) as total')
            ->groupBy('fecha')
            ->orderBy('fecha')
            ->pluck('total', 'fecha')
            ->map(function ($monto) {
                return (float) $monto;
            });



        // Filtrar grupos en mora por asesor si corresponde
        $gruposEnMoraQuery = Grupo::query();
        if ($asesor) {
            $gruposEnMoraQuery->whereHas('clientes', function ($q) use ($asesor) {
                $q->where('asesor_id', $asesor->id);
            });
        }
        $gruposEnMoraQuery = $gruposEnMoraQuery->with([
            'clientes',
            'prestamos' => function ($query) {
                $query->orderByDesc('id');
            },
            'prestamos.cuotasGrupales' => function ($query) use ($desde, $hasta) {
                if ($desde) {
                    $query->whereDate('fecha_vencimiento', '>=', $desde);
                }
                if ($hasta) {
                    $query->whereDate('fecha_vencimiento', '<=', $hasta);
                }
            },
            'prestamos.cuotasGrupales.mora'
        ]);

        $gruposEnMora = $gruposEnMoraQuery->get()->filter(function ($grupo) use ($desde, $hasta) {
            $prestamoPrincipal = $grupo->prestamos->first();
            if ($prestamoPrincipal) {
                $tieneCuotaEnMora = $prestamoPrincipal->cuotasGrupales->contains(function ($cuota) use ($desde, $hasta) {
                    if ($cuota->estado_cuota_grupal !== 'mora') {
                        return false;
                    }
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
        })->map(function ($grupo) use ($desde, $hasta) {
            $numeroIntegrantes = $grupo->clientes->count();
            $prestamoPrincipal = $grupo->prestamos->first();
            $montoMoraGrupo = 0;
            if ($prestamoPrincipal) {
                foreach ($prestamoPrincipal->cuotasGrupales as $cuota) {
                    if ($cuota->estado_cuota_grupal === 'mora' && $cuota->mora && $cuota->mora->estado_mora === 'pendiente') {
                        $incluirCuota = true;
                        if ($desde && $cuota->fecha_vencimiento < $desde) {
                            $incluirCuota = false;
                        }
                        if ($hasta && $cuota->fecha_vencimiento > $hasta) {
                            $incluirCuota = false;
                        }
                        if ($incluirCuota) {
                            $montoMoraGrupo += abs($cuota->mora->monto_mora_calculado);
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
        })->filter(function ($grupo) {
            return $grupo['monto_mora'] > 0;
        })->sortByDesc('monto_mora');


        $moraPorGrupoQuery = Grupo::query();
        if ($asesor) {
            $moraPorGrupoQuery->whereHas('clientes', function ($q) use ($asesor) {
                $q->where('asesor_id', $asesor->id);
            });
        }

        $moraPorGrupo = $moraPorGrupoQuery->with([
            'prestamos.cuotasGrupales' => function ($query) use ($desde, $hasta) {
                if ($desde) {
                    $query->whereDate('fecha_vencimiento', '>=', $desde);
                }
                if ($hasta) {
                    $query->whereDate('fecha_vencimiento', '<=', $hasta);
                }
            },
            'prestamos.cuotasGrupales.mora'
        ])
            ->get()
            ->mapWithKeys(function ($grupo) use ($desde, $hasta) {
                $mora = 0;
                foreach ($grupo->prestamos as $prestamo) {
                    foreach ($prestamo->cuotasGrupales as $cuota) {
                        if ($cuota->estado_cuota_grupal === 'mora' && $cuota->mora && $cuota->mora->estado_mora === 'pendiente') {

                            $incluirCuota = true;
                            if ($desde && $cuota->fecha_vencimiento < $desde) {
                                $incluirCuota = false;
                            }
                            if ($hasta && $cuota->fecha_vencimiento > $hasta) {
                                $incluirCuota = false;
                            }

                            if ($incluirCuota) {
                                $mora += abs($cuota->mora->monto_mora_calculado);
                            }
                        }
                    }
                }
                return [$grupo->nombre_grupo => $mora];
            })
            ->filter(function ($mora) {
                return $mora > 0;
            })
            ->sortByDesc(function ($value) {
                return $value;
            })
            ->take(10);



        return [
            'totalGrupos' => $totalGrupos,
            'totalClientes' => $totalClientes,
            'totalPrestamos' => $totalPrestamos,
            'totalPagosRegistrados' => $totalPagosRegistrados,
            'totalMorasHistoricas' => $totalMorasHistoricas,
            'cuotasVigentes' => $cuotasVigentes,
            'cuotasEnMora' => $cuotasEnMora,
            'cuotasCanceladas' => $cuotasCanceladas,
            'montoTotalMora' => $montoTotalMora,
            'pagosAprobados' => $pagosAprobados,
            'pagosPendientes' => $pagosPendientes,
            'pagosRechazados' => $pagosRechazados,
            'cuotasEstadosBar' => $cuotasEstadosBar,
            'pagosPorFecha' => $pagosPorFecha,
            'pagosPie' => $pagosPie,
            'grupos' => $gruposEnMora,
            'moraPorGrupo' => $moraPorGrupo,

        ];
    }
}
