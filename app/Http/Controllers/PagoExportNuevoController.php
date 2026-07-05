<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Pago;
use App\Domain\Documentos\ReporteProfesionalService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Response;

class PagoExportNuevoController extends Controller
{
    protected $reporteService;

    public function __construct(ReporteProfesionalService $reporteService)
    {
        $this->reporteService = $reporteService;
    }

    public function export(Request $request)
    {
        $request->validate([
            'formato'     => ['nullable', 'string', 'in:pdf,excel'],
            'grupo'       => ['nullable', 'integer', 'min:1'],
            'from'        => ['nullable', 'date'],
            'until'       => ['nullable', 'date', 'after_or_equal:from'],
            'estado_pago' => ['nullable', 'string', 'in:Todos los estados,aprobado,pendiente,rechazado,parcial'],
        ]);

        $formato = $request->get('formato', 'pdf');

        if ($formato === 'excel') {
            return $this->exportExcel($request);
        } else {
            return $this->exportPDFProfesional($request);
        }
    }

    private function exportPDFProfesional(Request $request)
    {
        $data   = $this->obtenerDatosConFiltros($request);
        $pagos  = $data['pagos'];
        $filtros = $data['filtros'];

        $pdf = $this->reporteService->generarReportePagos($pagos, $filtros);

        $nombreArchivo = 'Reporte_Profesional_Pagos_' . now()->format('Y-m-d_H-i-s') . '.pdf';

        return $pdf->download($nombreArchivo);
    }

    private function exportExcel(Request $request)
    {
        // Obtener datos con filtros
        $data = $this->obtenerDatosConFiltros($request);
        $pagos = $data['pagos'];

        // Calcular totales para el resumen ejecutivo
        $totalRegistros = $pagos->count();
        $totalMonto = $pagos->sum('monto_pagado');
        $totalMora = $pagos->sum('monto_mora_pagada');
        $totalGeneral = $totalMonto + $totalMora;

        // Generar HTML profesional para Excel (igual que moras pero con colores azules)
        $html = '<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <title>Reporte de Pagos</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; margin: 20px; }
        .header { background: #1e40af; color: white; padding: 15px; text-align: center; margin-bottom: 20px; }
        .header h1 { margin: 0; font-size: 18px; font-weight: bold; }
        .header p { margin: 5px 0 0 0; font-size: 12px; opacity: 0.9; }
        .summary { background: #f8fafc; border: 2px solid #3b82f6; padding: 15px; margin-bottom: 20px; }
        .summary h3 { margin: 0 0 10px 0; color: #1e40af; font-size: 14px; }
        .table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .table th { background: #1e40af; color: white; padding: 12px 8px; text-align: left; font-weight: bold; font-size: 11px; border: 1px solid #1e40af; }
        .table td { padding: 10px 8px; border: 1px solid #cbd5e0; font-size: 11px; }
        .table tbody tr:nth-child(even) { background: #f7fafc; }
        .table tbody tr:nth-child(odd) { background: white; }
        .amount { text-align: right; font-weight: bold; color: #1e40af; }
        .status-completado { background: #d1fae5; color: #065f46; padding: 4px 8px; border-radius: 4px; text-align: center; font-weight: bold; }
        .status-pendiente { background: #fef3c7; color: #92400e; padding: 4px 8px; border-radius: 4px; text-align: center; font-weight: bold; }
        .status-parcial { background: #fce7f3; color: #be185d; padding: 4px 8px; border-radius: 4px; text-align: center; font-weight: bold; }
        .status-cancelado { background: #fee2e2; color: #dc2626; padding: 4px 8px; border-radius: 4px; text-align: center; font-weight: bold; }
        .footer { margin-top: 20px; text-align: center; font-size: 10px; color: #6b7280; }
        .totals { background: #1e40af; color: white; font-weight: bold; }
        .no-data { text-align: center; padding: 40px; color: #6b7280; font-style: italic; }
    </style>
</head>
<body>
    <div class="header">
        <h1>REPORTE DE PAGOS</h1>
        <p>Sistema de Control Financiero - Generado el ' . now()->format('d/m/Y H:i:s') . '</p>
    </div>';

        if ($pagos->isEmpty()) {
            $html .= '<div class="no-data">
                <h3>No se encontraron registros de pagos</h3>
                <p>No hay datos que coincidan con los filtros aplicados</p>
            </div>';
        } else {
            // Tabla de datos (SIN resumen ejecutivo arriba)
            $html .= '<table class="table">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Grupo</th>
                        <th>Tipo</th>
                        <th>Código</th>
                        <th>Monto Pagado</th>
                        <th>Mora Pagada</th>
                        <th>Estado</th>
                        <th>Observaciones</th>
                    </tr>
                </thead>
                <tbody>';

            foreach ($pagos as $pago) {
                $fecha = $pago->fecha_pago ? Carbon::parse($pago->fecha_pago)->format('d/m/Y H:i') : 'N/A';
                $grupo = $pago->cuotaGrupal->prestamo->grupo->nombre_grupo ?? 'Sin grupo';
                $tipo = $pago->tipo_pago ?? 'N/A';
                $codigo = $pago->codigo_operacion ?? 'N/A';
                $montoPagado = $pago->monto_pagado ?? 0;
                $moraPagada = $pago->monto_mora_pagada ?? 0;
                $estado = $pago->estado_pago ?? 'N/A';
                $observaciones = $pago->observaciones ?? 'Sin observaciones';
                
                $estadoClass = '';
                $estadoTexto = '';
                
                switch (strtolower($estado)) {
                    case 'completado':
                        $estadoClass = 'status-completado';
                        $estadoTexto = 'COMPLETADO';
                        break;
                    case 'pendiente':
                        $estadoClass = 'status-pendiente';
                        $estadoTexto = 'PENDIENTE';
                        break;
                    case 'parcial':
                        $estadoClass = 'status-parcial';
                        $estadoTexto = 'PARCIAL';
                        break;
                    case 'cancelado':
                        $estadoClass = 'status-cancelado';
                        $estadoTexto = 'CANCELADO';
                        break;
                    default:
                        $estadoClass = 'status-pendiente';
                        $estadoTexto = strtoupper($estado);
                }

                $html .= "<tr>
                    <td>{$fecha}</td>
                    <td>{$grupo}</td>
                    <td>{$tipo}</td>
                    <td>{$codigo}</td>
                    <td class='amount'>S/ " . number_format($montoPagado, 2) . "</td>
                    <td class='amount'>S/ " . number_format($moraPagada, 2) . "</td>
                    <td><span class='{$estadoClass}'>{$estadoTexto}</span></td>
                    <td>{$observaciones}</td>
                </tr>";
            }

            // Fila de totales
            $html .= "<tr class='totals'>
                <td colspan='4'><strong>TOTAL GENERAL</strong></td>
                <td class='amount'><strong>S/ " . number_format($totalMonto, 2) . "</strong></td>
                <td class='amount'><strong>S/ " . number_format($totalMora, 2) . "</strong></td>
                <td colspan='2' class='amount'><strong>S/ " . number_format($totalGeneral, 2) . "</strong></td>
            </tr>";

            $html .= '</tbody></table>';

            // RESUMEN EJECUTIVO AL FINAL con formato horizontal (igual que moras)
            $html .= '<div class="summary" style="margin-top: 30px;">
                <h3>RESUMEN EJECUTIVO</h3>
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="padding: 10px; border: 1px solid #cbd5e0; background: #f8fafc; font-weight: bold; width: 200px;">Total Registros:</td>
                        <td style="padding: 10px; border: 1px solid #cbd5e0; background: white; font-weight: bold; color: #1e40af;">' . number_format($totalRegistros) . '</td>
                    </tr>
                    <tr>
                        <td style="padding: 10px; border: 1px solid #cbd5e0; background: #f8fafc; font-weight: bold;">Total Monto Pagado:</td>
                        <td style="padding: 10px; border: 1px solid #cbd5e0; background: white; font-weight: bold; color: #3b82f6;">S/ ' . number_format($totalMonto, 2) . '</td>
                    </tr>
                    <tr>
                        <td style="padding: 10px; border: 1px solid #cbd5e0; background: #f8fafc; font-weight: bold;">Total Mora Pagada:</td>
                        <td style="padding: 10px; border: 1px solid #cbd5e0; background: white; font-weight: bold; color: #2563eb;">S/ ' . number_format($totalMora, 2) . '</td>
                    </tr>
                    <tr>
                        <td style="padding: 12px; border: 2px solid #1e40af; background: #1e40af; font-weight: bold; color: white;">TOTAL GENERAL:</td>
                        <td style="padding: 12px; border: 2px solid #1e40af; background: #1e40af; font-weight: bold; color: white; font-size: 14px;">S/ ' . number_format($totalGeneral, 2) . '</td>
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
        $filename = "Reporte_Pagos_Profesional_{$totalRegistros}reg_{$fechaActual}.xls";

        return response($html, 200, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0'
        ]);
    }

    private function obtenerDatosConFiltros(Request $request)
    {
        $user = $request->user();
        $query = Pago::query();
        $filtros = [];

        if ($user->hasRole('Asesor')) {
            $asesor = \App\Models\Asesor::where('user_id', $user->id)->first();
            if ($asesor) {
                $query->whereHas('cuotaGrupal.prestamo.grupo', function ($q) use ($asesor) {
                    $q->where('asesor_id', $asesor->id);
                });
                $nombre = $asesor->persona
                    ? trim($asesor->persona->nombre . ' ' . $asesor->persona->apellidos)
                    : 'N/A';
                $filtros[] = 'Asesor: ' . $nombre;
            } else {
                abort(403, 'No tienes un asesor asociado.');
            }
        } elseif (!$user->hasAnyRole(['super_admin', 'Jefe de operaciones', 'Jefe de creditos'])) {
            abort(403);
        }

        // Aplicar filtros
        if ($request->filled('grupo')) {
            $grupo = \App\Models\Grupo::find($request->input('grupo'));
            if ($grupo) {
                $query->whereHas('cuotaGrupal.prestamo.grupo', function ($q) use ($request) {
                    $q->where('id', $request->input('grupo'));
                });
                $filtros[] = 'Grupo: ' . $grupo->nombre_grupo;
            }
        }

        if ($request->filled('from')) {
            $query->whereDate('fecha_pago', '>=', $request->input('from'));
            $filtros[] = 'Desde: ' . Carbon::parse($request->input('from'))->format('d/m/Y');
        }

        if ($request->filled('until')) {
            $query->whereDate('fecha_pago', '<=', $request->input('until'));
            $filtros[] = 'Hasta: ' . Carbon::parse($request->input('until'))->format('d/m/Y');
        }

        if ($request->filled('estado_pago') && $request->input('estado_pago') !== '' && $request->input('estado_pago') !== 'Todos los estados') {
            $query->where('estado_pago', $request->input('estado_pago'));
            $filtros[] = 'Estado: ' . $request->input('estado_pago');
        }

        $pagos = $query->with([
                        'cuotaGrupal.prestamo.grupo'
                      ])
                      ->orderBy('fecha_pago', 'desc')
                      ->get();

        return [
            'pagos' => $pagos,
            'filtros' => $filtros
        ];
    }

    private function formatearEstadoExcel($estado)
    {
        switch (strtolower($estado)) {
            case 'completado':
                return '✅ Completado';
            case 'pendiente':
                return '⏳ Pendiente';
            case 'parcial':
                return '🟡 Parcial';
            case 'cancelado':
                return '❌ Cancelado';
            default:
                return htmlspecialchars($estado);
        }
    }
}
