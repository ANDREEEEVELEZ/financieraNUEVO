<?php

namespace App\Http\Controllers;

use App\Models\Ingreso;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;

class IngresoExportController extends Controller
{
    public function export(Request $request)
    {
        try {
            $formato = $request->input('formato', 'excel'); // excel o pdf
            $tipoIngreso = $request->input('tipo_ingreso', 'todos'); // transferencia, pago_cuota, todos
            $fechaDesde = $request->input('fecha_desde');
            $fechaHasta = $request->input('fecha_hasta');

            // Validar formato
            if (!in_array($formato, ['excel', 'pdf'])) {
                return redirect()->back()->with('error', 'Formato de exportación no válido.');
            }

            // Validar tipo de ingreso
            if (!in_array($tipoIngreso, ['todos', 'transferencia', 'pago_cuota'])) {
                return redirect()->back()->with('error', 'Tipo de ingreso no válido.');
            }

            // Construir la consulta base
            $query = Ingreso::with(['pago.cuotaGrupal.prestamo.grupo', 'grupo']);

            // Aplicar filtros
            if ($tipoIngreso !== 'todos') {
                $query->where('tipo_ingreso', $tipoIngreso);
            }

            if ($fechaDesde) {
                $query->whereDate('fecha_hora', '>=', $fechaDesde);
            }

            if ($fechaHasta) {
                $query->whereDate('fecha_hora', '<=', $fechaHasta);
            }

            // Aplicar filtros de usuario (si es asesor, solo sus registros)
            $user = Auth::user();
            if ($user->hasRole('Asesor')) {
                $asesor = \App\Models\Asesor::where('user_id', $user->id)->first();
                if ($asesor) {
                    $query->whereHas('pago.cuotaGrupal.prestamo.grupo', function ($q) use ($asesor) {
                        $q->where('asesor_id', $asesor->id);
                    });
                }
            }

            $ingresos = $query->orderBy('fecha_hora', 'desc')->get();

            // Verificar que hay datos para exportar
            if ($ingresos->isEmpty()) {
                return redirect()->back()->with('warning', 'No hay registros para exportar con los filtros seleccionados.');
            }

            if ($formato === 'excel') {
                return $this->exportCSV($ingresos, $tipoIngreso, $fechaDesde, $fechaHasta);
            } else {
                return $this->exportPDF($ingresos, $tipoIngreso, $fechaDesde, $fechaHasta);
            }

        } catch (\Exception $e) {
            Log::error('Error al generar la exportación de ingresos: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Error al generar la exportación: ' . $e->getMessage());
        }
    }

    private function exportCSV($ingresos, $tipoIngreso, $fechaDesde, $fechaHasta)
    {
        $filename = 'ingresos_' . date('Y-m-d_H-i-s') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
            'Pragma' => 'public',
        ];

        $callback = function() use ($ingresos, $tipoIngreso, $fechaDesde, $fechaHasta) {
            $file = fopen('php://output', 'w');

            // BOM para UTF-8 (para que Excel abra correctamente los caracteres especiales)
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            // Encabezado del reporte
            fputcsv($file, ['REPORTE DE INGRESOS'], ';');
            fputcsv($file, [''], ';'); // Línea vacía

            // Información del filtro
            if ($tipoIngreso !== 'todos') {
                $tipoTexto = $tipoIngreso === 'transferencia' ? 'TRANSFERENCIAS' : 'PAGOS DE CUOTA';
                fputcsv($file, ['Tipo:', $tipoTexto], ';');
            }
            if ($fechaDesde) {
                fputcsv($file, ['Fecha desde:', Carbon::parse($fechaDesde)->format('d/m/Y')], ';');
            }
            if ($fechaHasta) {
                fputcsv($file, ['Fecha hasta:', Carbon::parse($fechaHasta)->format('d/m/Y')], ';');
            }
            fputcsv($file, ['Generado el:', now()->format('d/m/Y H:i:s')], ';');
            fputcsv($file, [''], ';'); // Línea vacía

            // Encabezados de las columnas
            fputcsv($file, [
                'Fecha',
                'Tipo',
                'Descripción',
                'Monto'
            ], ';');

            // Datos
            foreach ($ingresos as $ingreso) {
                fputcsv($file, [
                    Carbon::parse($ingreso->fecha_hora)->format('d/m/Y H:i'),
                    ucfirst($ingreso->tipo_ingreso ?? 'N/A'),
                    $ingreso->descripcion ?? 'Sin descripción',
                    'S/. ' . number_format($ingreso->monto ?? 0, 2, ',', '.')
                ], ';');
            }

            // Totales
            $totalMonto = $ingresos->sum('monto');
            fputcsv($file, [''], ';'); // Línea vacía
            fputcsv($file, ['TOTAL GENERAL:', 'S/. ' . number_format($totalMonto, 2, ',', '.')], ';');
            fputcsv($file, ['Total de registros:', $ingresos->count()], ';');

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function exportPDF($ingresos, $tipoIngreso, $fechaDesde, $fechaHasta)
    {
        try {
            Log::info('Iniciando proceso de exportación PDF de ingresos');

            // Construir el título
            $titulo = 'REPORTE DE INGRESOS';
            if ($tipoIngreso !== 'todos') {
                $tipoTexto = $tipoIngreso === 'transferencia' ? 'TRANSFERENCIAS' : 'PAGOS DE CUOTA';
                $titulo .= ' - ' . $tipoTexto;
            }

            $data = [
                'titulo' => $titulo,
                'ingresos' => $ingresos,
                'tipoIngreso' => $tipoIngreso,
                'fechaDesde' => $fechaDesde ? Carbon::parse($fechaDesde)->format('d/m/Y') : null,
                'fechaHasta' => $fechaHasta ? Carbon::parse($fechaHasta)->format('d/m/Y') : null,
                'fechaGeneracion' => now()->format('d/m/Y H:i:s'),
                'totalMonto' => $ingresos->sum('monto'),
                'totalRegistros' => $ingresos->count()
            ];

            Log::info('Datos preparados para PDF de ingresos', ['titulo' => $titulo, 'total_registros' => $data['totalRegistros']]);

            // Verificar que la vista existe
            if (!view()->exists('exports.ingresos-pdf')) {
                throw new \Exception('La vista exports.ingresos-pdf no existe');
            }

            Log::info('Vista encontrada, generando PDF...');

            $pdf = Pdf::loadView('exports.ingresos-pdf', $data);
            $pdf->setPaper('A4', 'landscape');

            $filename = 'ingresos_' . date('Y-m-d_H-i-s') . '.pdf';

            Log::info('PDF generado exitosamente', ['filename' => $filename]);

            return $pdf->download($filename);

        } catch (\Exception $e) {
            // Log del error para debugging
            Log::error('Error generando PDF de ingresos: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());

            // Redirigir con mensaje de error
            return redirect()->back()->with('error', 'Error al generar el PDF: ' . $e->getMessage());
        }
    }
}
