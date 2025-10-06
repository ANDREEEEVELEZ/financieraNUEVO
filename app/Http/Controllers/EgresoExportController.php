<?php

namespace App\Http\Controllers;

use App\Models\Egreso;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;



class EgresoExportController extends Controller
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
                return $this->exportPDF($egresos, $tipoEgreso, $fechaDesde, $fechaHasta);
            }

        } catch (\Exception $e) {
            Log::error('Error al generar la exportación: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Error al generar la exportación: ' . $e->getMessage());
        }
    }

    private function exportCSV($egresos, $tipoEgreso, $fechaDesde, $fechaHasta)
    {
        // Calcular totales para el resumen ejecutivo
        $totalRegistros = $egresos->count();
        $totalMonto = $egresos->sum('monto');
        $totalDesembolsos = $egresos->where('tipo_egreso', 'desembolso')->sum('monto');
        $totalGastos = $egresos->where('tipo_egreso', 'gasto')->sum('monto');

        // Generar HTML profesional para Excel
        $html = '<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <title>Reporte de Egresos</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; margin: 20px; }
        .header { background: #dc2626; color: white; padding: 15px; text-align: center; margin-bottom: 20px; }
        .header h1 { margin: 0; font-size: 18px; font-weight: bold; }
        .header p { margin: 5px 0 0 0; font-size: 12px; opacity: 0.9; }
        .summary { background: #fef2f2; border: 2px solid #dc2626; padding: 15px; margin-bottom: 20px; }
        .summary h3 { margin: 0 0 10px 0; color: #dc2626; font-size: 14px; }
        .table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .table th { background: #dc2626; color: white; padding: 12px 8px; text-align: left; font-weight: bold; font-size: 11px; border: 1px solid #dc2626; }
        .table td { padding: 10px 8px; border: 1px solid #cbd5e0; font-size: 11px; }
        .table tbody tr:nth-child(even) { background: #fef2f2; }
        .table tbody tr:nth-child(odd) { background: white; }
        .amount { text-align: right; font-weight: bold; color: #dc2626; }
        .type-desembolso { background: #fef3c7; color: #92400e; padding: 4px 8px; border-radius: 4px; text-align: center; font-weight: bold; }
        .type-gasto { background: #fee2e2; color: #991b1b; padding: 4px 8px; border-radius: 4px; text-align: center; font-weight: bold; }
        .footer { margin-top: 20px; text-align: center; font-size: 10px; color: #6b7280; }
        .totals { background: #dc2626; color: white; font-weight: bold; }
        .no-data { text-align: center; padding: 40px; color: #6b7280; font-style: italic; }
    </style>
</head>
<body>
    <div class="header">
        <h1>REPORTE DE EGRESOS</h1>
        <p>Sistema de Control Financiero - Generado el ' . now()->format('d/m/Y H:i:s') . '</p>
    </div>';


        if ($egresos->isEmpty()) {
            $html .= '<div class="no-data">
                <h3>No se encontraron registros de egresos</h3>
                <p>No hay datos que coincidan con los filtros aplicados</p>
            </div>';
        } else {
            // Tabla de datos (SIN resumen ejecutivo arriba)
            $html .= '<table class="table">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Tipo</th>
                        <th>Descripción</th>
                        <th>Categoría</th>
                        <th>Monto</th>
                    </tr>
                </thead>
                <tbody>';

            foreach ($egresos as $egreso) {
                $fecha = Carbon::parse($egreso->fecha)->format('d/m/Y');
                $tipo = $egreso->tipo_egreso ?? 'N/A';
                $descripcion = $egreso->descripcion ?? 'Sin descripción';
                $categoria = $egreso->categoria->nombre ?? ($egreso->subcategoria->nombre ?? 'Sin categoría');
                $monto = $egreso->monto ?? 0;

                $tipoClass = '';
                $tipoTexto = '';

                switch ($tipo) {
                    case 'desembolso':
                        $tipoClass = 'type-desembolso';
                        $tipoTexto = 'DESEMBOLSO';
                        break;
                    case 'gasto':
                        $tipoClass = 'type-gasto';
                        $tipoTexto = 'GASTO';
                        break;
                    default:
                        $tipoClass = 'type-gasto';
                        $tipoTexto = strtoupper($tipo);
                }

                $html .= "<tr>
                    <td>{$fecha}</td>
                    <td><span class='{$tipoClass}'>{$tipoTexto}</span></td>
                    <td>{$descripcion}</td>
                    <td>{$categoria}</td>
                    <td class='amount'>S/ " . number_format($monto, 2) . "</td>
                </tr>";
            }

            // Fila de totales
            $html .= "<tr class='totals'>
                <td colspan='4'><strong>TOTAL GENERAL</strong></td>
                <td class='amount'><strong>S/ " . number_format($totalMonto, 2) . "</strong></td>
            </tr>";

            $html .= '</tbody></table>';

            // RESUMEN EJECUTIVO AL FINAL con formato horizontal
            $html .= '<div class="summary" style="margin-top: 30px;">
                <h3>RESUMEN EJECUTIVO</h3>
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="padding: 10px; border: 1px solid #cbd5e0; background: #fef2f2; font-weight: bold; width: 200px;">Total Registros:</td>
                        <td style="padding: 10px; border: 1px solid #cbd5e0; background: white; font-weight: bold; color: #dc2626;">' . number_format($totalRegistros) . '</td>
                    </tr>
                    <tr>
                        <td style="padding: 10px; border: 1px solid #cbd5e0; background: #fef2f2; font-weight: bold;">Total Desembolsos:</td>
                        <td style="padding: 10px; border: 1px solid #cbd5e0; background: white; font-weight: bold; color: #92400e;">S/ ' . number_format($totalDesembolsos, 2) . '</td>
                    </tr>
                    <tr>
                        <td style="padding: 10px; border: 1px solid #cbd5e0; background: #fef2f2; font-weight: bold;">Total Gastos:</td>
                        <td style="padding: 10px; border: 1px solid #cbd5e0; background: white; font-weight: bold; color: #991b1b;">S/ ' . number_format($totalGastos, 2) . '</td>
                    </tr>
                    <tr>
                        <td style="padding: 12px; border: 2px solid #dc2626; background: #dc2626; font-weight: bold; color: white;">TOTAL GENERAL:</td>
                        <td style="padding: 12px; border: 2px solid #dc2626; background: #dc2626; font-weight: bold; color: white; font-size: 14px;">S/ ' . number_format($totalMonto, 2) . '</td>
                    </tr>
                </table>
            </div>';
        }

        $html .= '<div class="footer">
            <p><strong>Documento generado automáticamente el ' . now()->format('d/m/Y H:i:s') . '</strong></p>
            <p>Información confidencial - Uso interno únicamente</p>
        </div>
    </body>
    </html>';

        $fechaActual = now()->format('Y-m-d_H-i-s');
        $filename = "Reporte_Egresos_Profesional_{$totalRegistros}reg_{$fechaActual}.xls";

        return response($html, 200, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0'
        ]);
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
