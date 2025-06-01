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

class Asesor extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    protected static string $view = 'filament.dashboard.pages.asesor';
    protected static ?string $navigationLabel = 'Panel asesor';

    public function getViewData(): array
    {
        $desde = request('desde');
        $hasta = request('hasta');
        $user = request()->user();

        $query = null;
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

        // Conteos básicos
        $totalClientes = $clientesQuery->count();
        $totalGrupos = $gruposQuery->count();
        $totalPrestamos = $prestamosQuery->count();
        $totalRetanqueos = Retanqueo::whereIn('prestamo_id', $prestamosQuery->pluck('id'))->count();

        // Estadísticas de moras
        $cuotasQuery = CuotasGrupales::whereHas('prestamo', function ($q) use ($prestamosQuery) {
            $q->whereIn('id', $prestamosQuery->pluck('id'));
        });
        
        $cuotasEnMora = $cuotasQuery->whereHas('mora', function ($q) {
            $q->where('estado_mora', 'pendiente');
        })->count();

        $montoTotalMora = $cuotasQuery->whereHas('mora', function ($q) {
            $q->where('estado_mora', 'pendiente');
        })->get()->sum(function ($cuota) {
            return $cuota->mora ? $cuota->mora->getMontoMoraCalculadoAttribute() : 0;
        });

        $totalMorasHistoricas = $cuotasQuery->whereHas('mora')->count();

        // Estadísticas de pagos
        $pagosQuery = Pago::whereHas('cuotaGrupal.prestamo', function ($q) use ($prestamosQuery) {
            $q->whereIn('id', $prestamosQuery->pluck('id'));
        });

        $totalPagosRegistrados = $pagosQuery->count();
        $pagosAprobados = $pagosQuery->where('estado_pago', 'Aprobado')->count();
        $pagosPendientes = $pagosQuery->where('estado_pago', 'Pendiente')->count();
        $pagosRechazados = $pagosQuery->where('estado_pago', 'Rechazado')->count();

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

        // Obtener pagos por fecha
        $pagosPorFecha = $pagosQuery->selectRaw('DATE(fecha_pago) as fecha, COUNT(*) as total')
            ->groupBy('fecha')
            ->orderBy('fecha')
            ->pluck('total', 'fecha');

        $grupos = ($gruposQuery)->with(['clientes', 'prestamos.cuotasGrupales.mora'])->get()->map(function($grupo) {
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

        // Calcular mora por grupo
        $moraPorGrupo = $gruposQuery->with(['prestamos.cuotasGrupales.mora'])->get()->mapWithKeys(function($grupo) {
            $mora = 0;
            foreach ($grupo->prestamos as $prestamo) {
                foreach ($prestamo->cuotasGrupales as $cuota) {
                    if ($cuota->mora && $cuota->mora->estado_mora === 'pendiente') {
                        $mora += $cuota->mora->getMontoMoraCalculadoAttribute();
                    }
                }
            }
            return [$grupo->nombre_grupo => $mora];
        })->sortByDesc(function($value) {
            return $value;
        })->take(10);

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
            'grupos' => $grupos,
            'moraPorGrupo' => $moraPorGrupo,
        ];
    }
}
