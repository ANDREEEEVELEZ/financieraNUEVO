<?php

namespace App\Filament\Dashboard\Pages;

use Filament\Pages\Page;
use App\Models\Grupo;
use App\Models\Mora;
use App\Models\Pago;
use App\Models\Cliente;
use App\Models\Prestamo;
use App\Models\Retanqueo;

class Asesor extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    protected static string $view = 'filament.dashboard.pages.asesor';
    protected static ?string $navigationLabel = 'Panel asesor';

    public function getViewData(): array
    {
        $desde = request('desde');
        $hasta = request('hasta');

        $pagosQuery = Pago::query();
        $morasQuery = Mora::query();
        $cuotasQuery = \App\Models\CuotasGrupales::query();
        $retanqueosQuery = Retanqueo::query();
        $prestamosQuery = Prestamo::query();
        $clientesQuery = Cliente::query();
        $gruposQuery = Grupo::query();

        if ($desde) {
            $pagosQuery->whereDate('fecha_pago', '>=', $desde);
            $morasQuery->whereHas('cuotaGrupal', function($q) use ($desde) {
                $q->whereDate('fecha_vencimiento', '>=', $desde);
            });
            $cuotasQuery->whereDate('fecha_vencimiento', '>=', $desde);
            $retanqueosQuery->whereDate('created_at', '>=', $desde);
            $prestamosQuery->whereDate('created_at', '>=', $desde);
            $clientesQuery->whereDate('created_at', '>=', $desde);
            $gruposQuery->whereDate('created_at', '>=', $desde);
        }

        if ($hasta) {
            $pagosQuery->whereDate('fecha_pago', '<=', $hasta);
            $morasQuery->whereHas('cuotaGrupal', function($q) use ($hasta) {
                $q->whereDate('fecha_vencimiento', '<=', $hasta);
            });
            $cuotasQuery->whereDate('fecha_vencimiento', '<=', $hasta);
            $retanqueosQuery->whereDate('created_at', '<=', $hasta);
            $prestamosQuery->whereDate('created_at', '<=', $hasta);
            $clientesQuery->whereDate('created_at', '<=', $hasta);
            $gruposQuery->whereDate('created_at', '<=', $hasta);
        }

        $totalGrupos = $gruposQuery->count();
        $totalClientes = $clientesQuery->count();
        $totalPrestamos = $prestamosQuery->count();
        $totalRetanqueos = $retanqueosQuery->count();

        $totalMorasFiltradas = $morasQuery->count();

        $cuotasEnMora = Mora::query()
            ->when($desde, fn($q) => $q->whereHas('cuotaGrupal', fn($cq) => $cq->whereDate('fecha_vencimiento', '>=', $desde)))
            ->when($hasta, fn($q) => $q->whereHas('cuotaGrupal', fn($cq) => $cq->whereDate('fecha_vencimiento', '<=', $hasta)))
            ->whereIn('estado_mora', ['pendiente', 'parcial'])
            ->count();

        $montoTotalMora = $morasQuery->where('estado_mora', 'pendiente')->get()->sum(function($mora) {
            return abs($mora->monto_mora_calculado);
        });

        $pagosAprobados = (clone $pagosQuery)->where('estado_pago', 'Aprobado')->count();
        $pagosPendientes = (clone $pagosQuery)->where('estado_pago', 'Pendiente')->count();
        $pagosRechazados = (clone $pagosQuery)->where('estado_pago', 'Rechazado')->count();
        $totalPagosRegistrados = $pagosQuery->count();

        $listaGrupos = $this->getListaGruposConEstado($gruposQuery);

        $cuotasEstadosBar = [
            'Vigente' => (clone $cuotasQuery)->where('estado_cuota_grupal', 'vigente')->count(),
            'Mora' => (clone $cuotasQuery)->where('estado_cuota_grupal', 'mora')->count(),
            'Cancelada' => (clone $cuotasQuery)->where('estado_cuota_grupal', 'cancelada')->count(),
        ];

        $pagosPorFecha = (clone $pagosQuery)
            ->selectRaw('DATE(fecha_pago) as fecha, SUM(monto_pagado) as total')
            ->where('estado_pago', 'Aprobado')
            ->groupBy('fecha')
            ->orderBy('fecha')
            ->pluck('total', 'fecha');

        $pagosPie = [
            'Aprobados' => $pagosAprobados,
            'Pendientes' => $pagosPendientes,
            'Rechazados' => $pagosRechazados,
        ];

        $moraPorGrupo = $gruposQuery->with(['prestamos.cuotasGrupales.mora'])->get()->mapWithKeys(function($grupo) {
            $mora = 0;
            foreach ($grupo->prestamos as $prestamo) {
                foreach ($prestamo->cuotasGrupales as $cuota) {
                    if ($cuota->mora && $cuota->mora->estado_mora === 'pendiente') {
                        $mora += abs($cuota->mora->monto_mora_calculado);
                    }
                }
            }
            return [$grupo->nombre_grupo => $mora];
        })->sortDesc()->take(10);

        return [
            'totalGrupos' => $totalGrupos,
            'totalClientes' => $totalClientes,
            'totalPrestamos' => $totalPrestamos,
            'totalRetanqueos' => $totalRetanqueos,
            'cuotasEnMora' => $cuotasEnMora,
            'montoTotalMora' => $montoTotalMora,
            'pagosAprobados' => $pagosAprobados,
            'pagosPendientes' => $pagosPendientes,
            'pagosRechazados' => $pagosRechazados,
            'totalPagosRegistrados' => $totalPagosRegistrados,
            'totalMorasHistoricas' => $totalMorasFiltradas,
            'listaGrupos' => $listaGrupos,
            'cuotasEstadosBar' => $cuotasEstadosBar,
            'pagosPorFecha' => $pagosPorFecha,
            'pagosPie' => $pagosPie,
            'moraPorGrupo' => $moraPorGrupo,
        ];
    }

    public function getListaGruposConEstado($gruposQuery = null)
    {
        $grupos = ($gruposQuery ?: Grupo::query())->with(['clientes', 'prestamos.cuotasGrupales.mora'])->get()->map(function($grupo) {
            $numeroIntegrantes = $grupo->clientes->count();
            $prestamoPrincipal = $grupo->prestamos()->orderByDesc('id')->first();
            $estado = 'Activo';
            if ($prestamoPrincipal) {
                $tieneMora = $prestamoPrincipal->cuotasGrupales->contains(fn($cuota) => $cuota->mora && $cuota->mora->estado_mora === 'pendiente');
                if ($tieneMora) {
                    $estado = 'En mora';
                }
            }
            return [
                'nombre' => $grupo->nombre_grupo,
                'numero_integrantes' => $numeroIntegrantes,
                'estado' => $estado,
            ];
        });
        return $grupos;
    }
}
