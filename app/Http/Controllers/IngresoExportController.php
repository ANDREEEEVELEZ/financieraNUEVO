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
        // Calcular totales para el resumen ejecutivo
        $totalRegistros = $ingresos->count();
        $totalMonto = $ingresos->sum('monto');
        $totalTransferencias = $ingresos->where('tipo_ingreso', 'transferencia')->sum('monto');
        $totalPagosCuota = $ingresos->where('tipo_ingreso', 'pago_cuota')->sum('monto');

        // Generar HTML profesional para Excel
        $html = '<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <title>Reporte de Ingresos</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; margin: 20px; }
        .header { background: #059669; color: white; padding: 15px; text-align: center; margin-bottom: 20px; }
        .header h1 { margin: 0; font-size: 18px; font-weight: bold; }
        .header p { margin: 5px 0 0 0; font-size: 12px; opacity: 0.9; }
        .summary { background: #f0fdf4; border: 2px solid #059669; padding: 15px; margin-bottom: 20px; }
        .summary h3 { margin: 0 0 10px 0; color: #059669; font-size: 14px; }
        .table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .table th { background: #059669; color: white; padding: 12px 8px; text-align: left; font-weight: bold; font-size: 11px; border: 1px solid #059669; }
        .table td { padding: 10px 8px; border: 1px solid #cbd5e0; font-size: 11px; }
        .table tbody tr:nth-child(even) { background: #f0fdf4; }
        .table tbody tr:nth-child(odd) { background: white; }
        .amount { text-align: right; font-weight: bold; color: #059669; }
        .type-transferencia { background: #dcfce7; color: #166534; padding: 4px 8px; border-radius: 4px; text-align: center; font-weight: bold; }
        .type-pago { background: #d1fae5; color: #065f46; padding: 4px 8px; border-radius: 4px; text-align: center; font-weight: bold; }
        .footer { margin-top: 20px; text-align: center; font-size: 10px; color: #6b7280; }
        .totals { background: #059669; color: white; font-weight: bold; }
        .no-data { text-align: center; padding: 40px; color: #6b7280; font-style: italic; }
    </style>
</head>
<body>
    <div class="header">
        <h1>REPORTE DE INGRESOS</h1>
        <p>Sistema de Control Financiero - Generado el ' . now()->format('d/m/Y H:i:s') . '</p>
    </div>';

        if ($ingresos->isEmpty()) {
            $html .= '<div class="no-data">
                <h3>No se encontraron registros de ingresos</h3>
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
                        <th>Grupo</th>
                        <th>Monto</th>
                    </tr>
                </thead>
                <tbody>';

            foreach ($ingresos as $ingreso) {
                $fecha = Carbon::parse($ingreso->fecha_hora)->format('d/m/Y H:i');
                $tipo = $ingreso->tipo_ingreso ?? 'N/A';
                $descripcion = $ingreso->descripcion ?? 'Sin descripción';
                $grupo = $ingreso->grupo->nombre_grupo ?? ($ingreso->pago->cuotaGrupal->prestamo->grupo->nombre_grupo ?? 'Sin grupo');
                $monto = $ingreso->monto ?? 0;

                $tipoClass = '';
                $tipoTexto = '';

                switch ($tipo) {
                    case 'transferencia':
                        $tipoClass = 'type-transferencia';
                        $tipoTexto = 'TRANSFERENCIA';
                        break;
                    case 'pago_cuota':
                        $tipoClass = 'type-pago';
                        $tipoTexto = 'PAGO CUOTA';
                        break;
                    default:
                        $tipoClass = 'type-pago';
                        $tipoTexto = strtoupper($tipo);
                }

                $html .= "<tr>
                    <td>{$fecha}</td>
                    <td><span class='{$tipoClass}'>{$tipoTexto}</span></td>
                    <td>{$descripcion}</td>
                    <td>{$grupo}</td>
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
                        <td style="padding: 10px; border: 1px solid #cbd5e0; background: #f0fdf4; font-weight: bold; width: 200px;">Total Registros:</td>
                        <td style="padding: 10px; border: 1px solid #cbd5e0; background: white; font-weight: bold; color: #059669;">' . number_format($totalRegistros) . '</td>
                    </tr>
                    <tr>
                        <td style="padding: 10px; border: 1px solid #cbd5e0; background: #f0fdf4; font-weight: bold;">Total Transferencias:</td>
                        <td style="padding: 10px; border: 1px solid #cbd5e0; background: white; font-weight: bold; color: #166534;">S/ ' . number_format($totalTransferencias, 2) . '</td>
                    </tr>
                    <tr>
                        <td style="padding: 10px; border: 1px solid #cbd5e0; background: #f0fdf4; font-weight: bold;">Total Pagos de Cuota:</td>
                        <td style="padding: 10px; border: 1px solid #cbd5e0; background: white; font-weight: bold; color: #065f46;">S/ ' . number_format($totalPagosCuota, 2) . '</td>
                    </tr>
                    <tr>
                        <td style="padding: 12px; border: 2px solid #059669; background: #059669; font-weight: bold; color: white;">TOTAL GENERAL:</td>
                        <td style="padding: 12px; border: 2px solid #059669; background: #059669; font-weight: bold; color: white; font-size: 14px;">S/ ' . number_format($totalMonto, 2) . '</td>
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
        $filename = "Reporte_Ingresos_Profesional_{$totalRegistros}reg_{$fechaActual}.xls";

        return response($html, 200, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0'
        ]);
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
