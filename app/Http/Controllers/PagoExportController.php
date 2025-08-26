<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Pago;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Log;

class PagoExportController extends Controller
{
    public function export(Request $request)
    {
        // Debug: registrar los parámetros recibidos
        Log::info('PagoExportController - Parámetros recibidos:', $request->all());

        $formato = $request->get('formato', 'pdf');

        if ($formato === 'excel') {
            return $this->exportCSV($request);
        } else {
            return $this->exportPDF($request);
        }
    }

    private function exportCSV(Request $request)
    {
        $user = $request->user();
        $query = Pago::query();

        Log::info('PagoExportController CSV - Usuario:', ['user' => $user->id, 'roles' => $user->getRoleNames()]);

        // Aplicar filtros de autorización
        if ($user->hasRole('Asesor')) {
            $asesor = \App\Models\Asesor::where('user_id', $user->id)->first();
            if ($asesor) {
                Log::info('PagoExportController CSV - Asesor encontrado:', ['asesor_id' => $asesor->id]);
                $query->whereHas('cuotaGrupal.prestamo.grupo', function ($q) use ($asesor) {
                    $q->where('asesor_id', $asesor->id);
                });
            } else {
                Log::error('PagoExportController CSV - No se encontró asesor para user_id:', $user->id);
                return response()->json(['error' => 'No se encontró asesor asociado'], 403);
            }
        } elseif (!$user->hasAnyRole(['super_admin', 'Jefe de operaciones', 'Jefe de creditos'])) {
            Log::error('PagoExportController CSV - Usuario no autorizado:', ['user_id' => $user->id]);
            return response()->json(['error' => 'No autorizado'], 403);
        }

        // Aplicar filtros adicionales
        if ($request->filled('grupo')) {
            Log::info('PagoExportController CSV - Filtro grupo aplicado:', ['grupo' => $request->input('grupo')]);
            $query->whereHas('cuotaGrupal.prestamo.grupo', function ($q) use ($request) {
                $q->where('id', $request->input('grupo'));
            });
        }

        if ($request->filled('from')) {
            Log::info('PagoExportController CSV - Filtro fecha desde:', ['from' => $request->input('from')]);
            $query->whereDate('fecha_pago', '>=', $request->input('from'));
        }
        if ($request->filled('until')) {
            Log::info('PagoExportController CSV - Filtro fecha hasta:', ['until' => $request->input('until')]);
            $query->whereDate('fecha_pago', '<=', $request->input('until'));
        }
        if ($request->filled('estado_pago') && $request->input('estado_pago') !== '' && $request->input('estado_pago') !== 'Todos los estados') {
            Log::info('PagoExportController CSV - Filtro estado:', ['estado' => $request->input('estado_pago')]);
            $query->where('estado_pago', $request->input('estado_pago'));
        }

        // Debug: mostrar la consulta SQL
        Log::info('PagoExportController CSV - SQL Query:', ['sql' => $query->toSql(), 'bindings' => $query->getBindings()]);

        $pagos = $query->with(['cuotaGrupal.prestamo.grupo'])->orderBy('fecha_pago', 'desc')->get();

        Log::info('PagoExportController CSV - Registros encontrados:', ['count' => $pagos->count()]);

        // Si no hay registros, crear un CSV con mensaje
        if ($pagos->count() === 0) {
            $csvContent = "\xEF\xBB\xBF"; // BOM para UTF-8
            $csvContent .= "\"REPORTE DE PAGOS\";\"SIN RESULTADOS\";\"\";\"\";\"\";\"\";\"\";\"\"\n";
            $csvContent .= "\"No se encontraron registros\";\"Fecha: " . now()->format('d/m/Y H:i:s') . "\";\"\";\"\";\"\";\"\";\"\";\"\"\n";
            $csvContent .= "\"\";\"\";\"\";\"\";\"\";\"\";\"\";\"\";\n";
            $csvContent .= "\"Fecha Pago\";\"Grupo\";\"Tipo Pago\";\"Codigo Operacion\";\"Monto Pagado\";\"Mora Pagada\";\"Estado\";\"Observaciones\"\n";
        } else {
            // Crear CSV con delimitador punto y coma para Excel
            $csvContent = "\xEF\xBB\xBF"; // BOM para UTF-8

            // Encabezados de columnas con punto y coma como delimitador
            $csvContent .= "\"Fecha Pago\";\"Grupo\";\"Tipo Pago\";\"Codigo Operacion\";\"Monto Pagado\";\"Mora Pagada\";\"Estado\";\"Observaciones\"\n";

            // Datos de pagos - cada valor entre comillas y separado por punto y coma
            foreach ($pagos as $pago) {
                $fechaPago = $pago->fecha_pago ? Carbon::parse($pago->fecha_pago)->format('d/m/Y H:i') : '';
                $nombreGrupo = $pago->cuotaGrupal->prestamo->grupo->nombre_grupo ?? '';
                $tipoPago = $pago->tipo_pago ?? '';
                $codigoOperacion = $pago->codigo_operacion ?? '';
                $montoPagado = number_format($pago->monto_pagado ?? 0, 2, ',', '');
                $montoMoraPagada = number_format($pago->monto_mora_pagada ?? 0, 2, ',', '');
                $estado = $pago->estado_pago ?? '';
                $observaciones = str_replace(['"', "\n", "\r"], ['""', ' ', ' '], $pago->observaciones ?? '');

                $csvContent .= "\"{$fechaPago}\";\"{$nombreGrupo}\";\"{$tipoPago}\";\"{$codigoOperacion}\";\"{$montoPagado}\";\"{$montoMoraPagada}\";\"{$estado}\";\"{$observaciones}\"\n";
            }
        }

        // Generar nombre del archivo más descriptivo
        $fechaActual = now()->format('Y-m-d_H-i-s');
        $totalRegistros = $pagos->count();
        $estadoFiltro = $request->input('estado_pago', 'Todos los estados');
        if ($estadoFiltro === 'Todos los estados') {
            $estadoFiltro = 'Todos';
        }
        $nombreArchivo = "Reporte_Pagos_{$estadoFiltro}_{$totalRegistros}reg_{$fechaActual}.csv";

        return Response::make($csvContent, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$nombreArchivo}\"",
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0'
        ]);
    }

    private function exportPDF(Request $request)
    {
        $user = $request->user();
        $query = Pago::query();

        // Aplicar filtros de autorización
        if ($user->hasRole('Asesor')) {
            $asesor = \App\Models\Asesor::where('user_id', $user->id)->first();
            if ($asesor) {
                $query->whereHas('cuotaGrupal.prestamo.grupo', function ($q) use ($asesor) {
                    $q->where('asesor_id', $asesor->id);
                });
            } else {
                return response()->json(['error' => 'No se encontró asesor asociado'], 403);
            }
        } elseif (!$user->hasAnyRole(['super_admin', 'Jefe de operaciones', 'Jefe de creditos'])) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        // Aplicar filtros adicionales
        if ($request->filled('grupo')) {
            $query->whereHas('cuotaGrupal.prestamo.grupo', function ($q) use ($request) {
                $q->where('id', $request->input('grupo'));
            });
        }

        if ($request->filled('from')) {
            $query->whereDate('fecha_pago', '>=', $request->input('from'));
        }
        if ($request->filled('until')) {
            $query->whereDate('fecha_pago', '<=', $request->input('until'));
        }
        if ($request->filled('estado_pago') && $request->input('estado_pago') !== '' && $request->input('estado_pago') !== 'Todos los estados') {
            $query->where('estado_pago', $request->input('estado_pago'));
        }

        $pagos = $query->with(['cuotaGrupal.prestamo.grupo'])->orderBy('fecha_pago', 'desc')->get();

        // Datos para la vista
        $fechaDesde = $request->input('from') ? Carbon::parse($request->input('from'))->format('d/m/Y') : null;
        $fechaHasta = $request->input('until') ? Carbon::parse($request->input('until'))->format('d/m/Y') : null;
        $grupoSeleccionado = null;
        if ($request->filled('grupo')) {
            $grupo = \App\Models\Grupo::find($request->input('grupo'));
            $grupoSeleccionado = $grupo->nombre_grupo ?? 'Grupo no encontrado';
        }
        $estadoPago = ($request->input('estado_pago') && $request->input('estado_pago') !== 'Todos los estados') ? $request->input('estado_pago') : 'todos';
        $fechaGeneracion = now()->format('d/m/Y H:i:s');
        $totalMonto = $pagos->sum('monto_pagado');
        $totalRegistros = $pagos->count();
        $titulo = 'REPORTE DE PAGOS';

        try {
            $pdf = Pdf::loadView('exports.pagos-pdf', compact(
                'pagos',
                'titulo',
                'fechaDesde',
                'fechaHasta',
                'grupoSeleccionado',
                'estadoPago',
                'fechaGeneracion',
                'totalMonto',
                'totalRegistros'
            ))
            ->setOptions([
                'isPhpEnabled' => true,
                'defaultFont' => 'Arial'
            ]);

            $nombreArchivo = 'pagos_' . now()->format('Y-m-d_H-i-s') . '.pdf';

            return $pdf->download($nombreArchivo);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al generar PDF: ' . $e->getMessage()], 500);
        }
    }
}
