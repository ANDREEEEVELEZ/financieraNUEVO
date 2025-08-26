<?php

namespace App\Http\Controllers;

use App\Models\Egreso;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;


class EgresoExportControllerSimple extends Controller
{
    public function export(Request $request)
    {
        try {
            $formato = $request->input('formato', 'excel'); // excel o pdf
            $tipoEgreso = $request->input('tipo_egreso', 'todos'); // desembolso, gasto, todos
            $fechaDesde = $request->input('fecha_desde');
            $fechaHasta = $request->input('fecha_hasta');

            // Validar formato
            if (!in_array($formato, ['excel', 'pdf'])) {
                return redirect()->back()->with('error', 'Formato de exportación no válido.');
            }

            // Validar tipo de egreso
            if (!in_array($tipoEgreso, ['todos', 'desembolso', 'gasto'])) {
                return redirect()->back()->with('error', 'Tipo de egreso no válido.');
            }

            // Construir la consulta base
            $query = Egreso::with(['prestamo.grupo', 'categoria', 'subcategoria']);

            // Aplicar filtros
            if ($tipoEgreso !== 'todos') {
                $query->where('tipo_egreso', $tipoEgreso);
            }

            if ($fechaDesde) {
                $query->whereDate('fecha', '>=', $fechaDesde);
            }

            if ($fechaHasta) {
                $query->whereDate('fecha', '<=', $fechaHasta);
            }

            // Aplicar filtros de usuario (si es asesor, solo sus registros)
            $user = Auth::user();
            if ($user->hasRole('Asesor')) {
                $asesor = \App\Models\Asesor::where('user_id', $user->id)->first();
                if ($asesor) {
                    $query->whereHas('prestamo.grupo', function ($q) use ($asesor) {
                        $q->where('asesor_id', $asesor->id);
                    });
                }
            }

            $egresos = $query->orderBy('fecha', 'desc')->get();

            // Verificar que hay datos para exportar
            if ($egresos->isEmpty()) {
                return redirect()->back()->with('warning', 'No hay registros para exportar con los filtros seleccionados.');
            }

            if ($formato === 'excel') {
                return $this->exportCSV($egresos, $tipoEgreso, $fechaDesde, $fechaHasta);
            } else {
                // Agregar logging para debugging
                Log::info('Iniciando exportación PDF', [
                    'total_egresos' => $egresos->count(),
                    'tipo_egreso' => $tipoEgreso,
                    'fecha_desde' => $fechaDesde,
                    'fecha_hasta' => $fechaHasta
                ]);

                return $this->exportPDF($egresos, $tipoEgreso, $fechaDesde, $fechaHasta);
            }

        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Error al generar la exportación: ' . $e->getMessage());
        }
    }

    private function exportCSV($egresos, $tipoEgreso, $fechaDesde, $fechaHasta)
    {
        $filename = 'egresos_' . date('Y-m-d_H-i-s') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
            'Pragma' => 'public',
        ];

        $callback = function() use ($egresos, $tipoEgreso, $fechaDesde, $fechaHasta) {
            $file = fopen('php://output', 'w');

            // BOM para UTF-8 (para que Excel abra correctamente los caracteres especiales)
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            // Encabezado del reporte
            fputcsv($file, ['REPORTE DE EGRESOS'], ';');
            fputcsv($file, [''], ';'); // Línea vacía

            // Información del filtro
            if ($tipoEgreso !== 'todos') {
                fputcsv($file, ['Tipo:', strtoupper($tipoEgreso === 'desembolso' ? 'DESEMBOLSOS' : 'GASTOS')], ';');
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
                'Categoría',
                'Subcategoría',
                'Descripción',
                'Monto',
                'Grupo',
                'Beneficiario/Destino'
            ], ';');

            // Datos
            foreach ($egresos as $egreso) {
                fputcsv($file, [
                    Carbon::parse($egreso->fecha)->format('d/m/Y'),
                    ucfirst($egreso->tipo_egreso ?? 'N/A'),
                    $egreso->categoria ? $egreso->categoria->nombre : 'Sin categoría',
                    $egreso->subcategoria ? $egreso->subcategoria->nombre : 'Sin subcategoría',
                    $egreso->descripcion ?? 'Sin descripción',
                    'S/. ' . number_format($egreso->monto ?? 0, 2, ',', '.'),
                    $egreso->prestamo && $egreso->prestamo->grupo ? $egreso->prestamo->grupo->nombre : 'N/A',
                    $egreso->beneficiario_destino ?? 'N/A'
                ], ';');
            }

            // Totales
            $totalMonto = $egresos->sum('monto');
            fputcsv($file, [''], ';'); // Línea vacía
            fputcsv($file, ['TOTAL GENERAL:', 'S/. ' . number_format($totalMonto, 2, ',', '.')], ';');
            fputcsv($file, ['Total de registros:', $egresos->count()], ';');

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function exportPDF($egresos, $tipoEgreso, $fechaDesde, $fechaHasta)
    {
        try {
            Log::info('Iniciando proceso de exportación PDF');

            // Construir el título
            $titulo = 'REPORTE DE EGRESOS';
            if ($tipoEgreso !== 'todos') {
                $titulo .= ' - ' . strtoupper($tipoEgreso === 'desembolso' ? 'DESEMBOLSOS' : 'GASTOS');
            }

            $data = [
                'titulo' => $titulo,
                'egresos' => $egresos,
                'tipoEgreso' => $tipoEgreso,
                'fechaDesde' => $fechaDesde ? Carbon::parse($fechaDesde)->format('d/m/Y') : null,
                'fechaHasta' => $fechaHasta ? Carbon::parse($fechaHasta)->format('d/m/Y') : null,
                'fechaGeneracion' => now()->format('d/m/Y H:i:s'),
                'totalMonto' => $egresos->sum('monto'),
                'totalRegistros' => $egresos->count()
            ];

            Log::info('Datos preparados para PDF', ['titulo' => $titulo, 'total_registros' => $data['totalRegistros']]);

            // Verificar que la vista existe
            if (!view()->exists('exports.egresos-pdf-simple')) {
                throw new \Exception('La vista exports.egresos-pdf-simple no existe');
            }

            Log::info('Vista encontrada, generando PDF...');

            $pdf = Pdf::loadView('exports.egresos-pdf-simple', $data);
            $pdf->setPaper('A4', 'landscape');

            $filename = 'egresos_' . date('Y-m-d_H-i-s') . '.pdf';

            Log::info('PDF generado exitosamente', ['filename' => $filename]);

            return $pdf->download($filename);

        } catch (\Exception $e) {
            // Log del error para debugging
            Log::error('Error generando PDF de egresos: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());

            // Redirigir con mensaje de error
            return redirect()->back()->with('error', 'Error al generar el PDF: ' . $e->getMessage());
        }
    }
}
