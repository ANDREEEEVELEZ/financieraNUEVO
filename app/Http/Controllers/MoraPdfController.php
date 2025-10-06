<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CuotasGrupales;
use App\Models\Asesor; // Asegúrate de importar el modelo Asesor
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Response;
use Carbon\Carbon;

class MoraPdfController extends Controller
{
    public function exportar(Request $request)
    {
        $formato = $request->get('formato', 'pdf');

        if ($formato === 'excel') {
            return $this->exportarExcel($request);
        } else {
            return $this->exportarPDF($request);
        }
    }

    private function exportarExcel(Request $request)
    {
        $user = $request->user();

        $query = CuotasGrupales::with(['mora', 'prestamo.grupo'])
            ->whereHas('mora');

        // Filtro por rol Asesor: solo grupos propios
        if ($user->hasRole('Asesor')) {
            $asesor = Asesor::where('user_id', $user->id)->first();

            if ($asesor) {
                $query->whereHas('prestamo.grupo', function ($q) use ($asesor) {
                    $q->where('asesor_id', $asesor->id);
                });
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        // Aplicar filtros
        $this->aplicarFiltros($query, $request);

        $cuotas_mora = $query->get();

        // Calcular totales
        $totalRegistros = $cuotas_mora->count();
        $totalMontoCuotas = $cuotas_mora->sum('monto_cuota_grupal');
        $totalSaldoPendiente = $cuotas_mora->sum(function($cuota) {
            return $cuota->getSaldoCuotaPendiente();
        });
        $totalMoraPendiente = $cuotas_mora->sum(function($cuota) {
            return $cuota->getSaldoMoraPendiente();
        });
        $totalMoraPagada = $cuotas_mora->sum(function($cuota) {
            return $cuota->getMoraPagada();
        });
        $totalGeneral = $totalSaldoPendiente + $totalMoraPendiente;

        // Generar HTML profesional para Excel
        $html = '<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <title>Reporte de Moras</title>
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
        .amount { text-align: right; font-weight: bold; }
        .amount-positive { color: #059669; }
        .amount-warning { color: #d97706; }
        .amount-danger { color: #dc2626; }
        .status-pendiente { background: #fef3c7; color: #92400e; padding: 4px 8px; border-radius: 4px; text-align: center; font-weight: bold; }
        .status-pagada { background: #d1fae5; color: #065f46; padding: 4px 8px; border-radius: 4px; text-align: center; font-weight: bold; }
        .status-parcial { background: #fce7f3; color: #be185d; padding: 4px 8px; border-radius: 4px; text-align: center; font-weight: bold; }
        .footer { margin-top: 20px; text-align: center; font-size: 10px; color: #6b7280; }
        .totals { background: #1e40af; color: white; font-weight: bold; }
        .no-data { text-align: center; padding: 40px; color: #6b7280; font-style: italic; }
    </style>
</head>
<body>
    <div class="header">
        <h1>REPORTE DE MORAS</h1>
        <p>Sistema de Control Financiero - Generado el ' . now()->format('d/m/Y H:i:s') . '</p>
    </div>';

        if ($cuotas_mora->isEmpty()) {
            $html .= '<div class="no-data">
                <h3>No se encontraron registros de moras</h3>
                <p>No hay datos que coincidan con los filtros aplicados</p>
            </div>';
        } else {
            // Tabla de datos (SIN resumen ejecutivo arriba)
            $html .= '<table class="table">
                <thead>
                    <tr>
                        <th>Grupo</th>
                        <th>N° Integrantes</th>
                        <th>N° Cuota</th>
                        <th>Monto Cuota</th>
                        <th>Fecha Vencimiento</th>
                        <th>Saldo Pendiente</th>
                        <th>Días Atraso</th>
                        <th>Mora Pendiente</th>
                        <th>Mora Pagada</th>
                        <th>Total a Pagar</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>';

            foreach ($cuotas_mora as $cuota) {
                $nombreGrupo = $cuota->prestamo->grupo->nombre_grupo ?? '';
                $numeroIntegrantes = $cuota->prestamo->grupo->clientes()->count() ?? 0;
                $numeroCuota = $cuota->numero_cuota ?? '';
                $montoCuota = $cuota->monto_cuota_grupal ?? 0;
                $fechaVencimiento = $cuota->fecha_vencimiento ? Carbon::parse($cuota->fecha_vencimiento)->format('d/m/Y') : '';
                
                $saldoCuotaPendiente = $cuota->getSaldoCuotaPendiente();
                $moraPendiente = $cuota->getSaldoMoraPendiente();
                $moraPagada = $cuota->getMoraPagada();
                $montoTotal = $saldoCuotaPendiente + $moraPendiente;
                
                $diasAtraso = $cuota->mora->dias_atraso ?? 0;
                $estado = $cuota->mora->estado_mora ?? '';
                $estadoClass = '';
                $estadoTexto = '';
                
                switch ($estado) {
                    case 'pendiente':
                        $estadoClass = 'status-pendiente';
                        $estadoTexto = 'PENDIENTE';
                        break;
                    case 'pagada':
                        $estadoClass = 'status-pagada';
                        $estadoTexto = 'PAGADA';
                        break;
                    case 'parcialmente_pagada':
                        $estadoClass = 'status-parcial';
                        $estadoTexto = 'PARCIAL';
                        break;
                    default:
                        $estadoClass = 'status-pendiente';
                        $estadoTexto = strtoupper($estado);
                }

                $html .= "<tr>
                    <td>{$nombreGrupo}</td>
                    <td>{$numeroIntegrantes}</td>
                    <td>{$numeroCuota}</td>
                    <td class='amount amount-positive'>S/ " . number_format($montoCuota, 2) . "</td>
                    <td>{$fechaVencimiento}</td>
                    <td class='amount amount-warning'>S/ " . number_format($saldoCuotaPendiente, 2) . "</td>
                    <td style='text-align: center; font-weight: bold; color: #dc2626;'>{$diasAtraso}</td>
                    <td class='amount amount-danger'>S/ " . number_format($moraPendiente, 2) . "</td>
                    <td class='amount amount-positive'>S/ " . number_format($moraPagada, 2) . "</td>
                    <td class='amount amount-danger'>S/ " . number_format($montoTotal, 2) . "</td>
                    <td><span class='{$estadoClass}'>{$estadoTexto}</span></td>
                </tr>";
            }

            // Fila de totales
            $html .= "<tr class='totals'>
                <td colspan='3'><strong>TOTALES</strong></td>
                <td class='amount'><strong>S/ " . number_format($totalMontoCuotas, 2) . "</strong></td>
                <td></td>
                <td class='amount'><strong>S/ " . number_format($totalSaldoPendiente, 2) . "</strong></td>
                <td></td>
                <td class='amount'><strong>S/ " . number_format($totalMoraPendiente, 2) . "</strong></td>
                <td class='amount'><strong>S/ " . number_format($totalMoraPagada, 2) . "</strong></td>
                <td class='amount'><strong>S/ " . number_format($totalGeneral, 2) . "</strong></td>
                <td></td>
            </tr>";

            $html .= '</tbody></table>';

            // RESUMEN EJECUTIVO AL FINAL con formato horizontal (etiqueta: monto)
            $html .= '<div class="summary" style="margin-top: 30px;">
                <h3>RESUMEN EJECUTIVO</h3>
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="padding: 10px; border: 1px solid #cbd5e0; background: #f8fafc; font-weight: bold; width: 200px;">Total Registros:</td>
                        <td style="padding: 10px; border: 1px solid #cbd5e0; background: white; font-weight: bold; color: #1e40af;">' . number_format($totalRegistros) . '</td>
                    </tr>
                    <tr>
                        <td style="padding: 10px; border: 1px solid #cbd5e0; background: #f8fafc; font-weight: bold;">Monto Total Cuotas:</td>
                        <td style="padding: 10px; border: 1px solid #cbd5e0; background: white; font-weight: bold; color: #059669;">S/ ' . number_format($totalMontoCuotas, 2) . '</td>
                    </tr>
                    <tr>
                        <td style="padding: 10px; border: 1px solid #cbd5e0; background: #f8fafc; font-weight: bold;">Saldo Pendiente:</td>
                        <td style="padding: 10px; border: 1px solid #cbd5e0; background: white; font-weight: bold; color: #d97706;">S/ ' . number_format($totalSaldoPendiente, 2) . '</td>
                    </tr>
                    <tr>
                        <td style="padding: 10px; border: 1px solid #cbd5e0; background: #f8fafc; font-weight: bold;">Mora Pendiente:</td>
                        <td style="padding: 10px; border: 1px solid #cbd5e0; background: white; font-weight: bold; color: #dc2626;">S/ ' . number_format($totalMoraPendiente, 2) . '</td>
                    </tr>
                    <tr>
                        <td style="padding: 10px; border: 1px solid #cbd5e0; background: #f8fafc; font-weight: bold;">Mora Pagada:</td>
                        <td style="padding: 10px; border: 1px solid #cbd5e0; background: white; font-weight: bold; color: #059669;">S/ ' . number_format($totalMoraPagada, 2) . '</td>
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
        $nombreArchivo = "Reporte_Moras_Profesional_{$totalRegistros}reg_{$fechaActual}.xls";

        return Response::make($html, 200, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$nombreArchivo}\"",
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0'
        ]);
    }

    private function exportarPDF(Request $request)
    {
        $user = $request->user();

        $query = CuotasGrupales::with(['mora', 'prestamo.grupo'])
            ->whereHas('mora');

        // Filtro por rol Asesor: solo grupos propios
        if ($user->hasRole('Asesor')) {
            $asesor = Asesor::where('user_id', $user->id)->first();

            if ($asesor) {
                $query->whereHas('prestamo.grupo', function ($q) use ($asesor) {
                    $q->where('asesor_id', $asesor->id);
                });
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        // Aplicar filtros
        $this->aplicarFiltros($query, $request);

        $cuotas_mora = $query->get();

        if ($cuotas_mora->isEmpty()) {
            $pdf = Pdf::loadHtml('<h2 style="color:#2563eb;text-align:center;margin-top:40px;">No hay registros de moras para los filtros seleccionados.</h2>');
            return $pdf->download('reporte_moras.pdf');
        }

        $pdf = Pdf::loadView('pdf.moras', compact('cuotas_mora'))
            -> setPaper('A4', 'landscape');
          // Renderiza el contenido
    $dompdf = $pdf->getDomPDF();
    $canvas = $dompdf->getCanvas();

    $canvas->page_script(function ($pageNumber, $pageCount, $canvas, $fontMetrics) {
        $text = "Página $pageNumber de $pageCount";
        $font = $fontMetrics->getFont('Helvetica', 'normal');
        $size = 10;
        $width = $fontMetrics->getTextWidth($text, $font, $size);
        $canvas->text(500 - $width, 820, $text, $font, $size);
    });

        return $pdf->download('reporte_moras.pdf');
    }

    private function aplicarFiltros($query, $request)
    {
        if ($request->filled('grupo')) {
            $query->whereHas('prestamo.grupo', function ($q) use ($request) {
                $q->where('nombre_grupo', 'like', '%' . $request->input('grupo') . '%');
            });
        }
        if ($request->filled('desde')) {
            $query->whereDate('fecha_vencimiento', '>=', $request->input('desde'));
        }
        if ($request->filled('hasta')) {
            $query->whereDate('fecha_vencimiento', '<=', $request->input('hasta'));
        }
        if ($request->filled('monto')) {
            $query->where('monto_cuota_grupal', '>=', $request->input('monto'));
        }
        if ($request->filled('estado_mora') && $request->input('estado_mora') !== 'Todos los estados') {
            $estado = $request->input('estado_mora');
            // Mapear 'parcial' a 'parcialmente_pagada' para consistencia
            if ($estado === 'parcial') {
                $estado = 'parcialmente_pagada';
            }
            $estadosValidos = ['pendiente', 'pagada', 'parcialmente_pagada'];
            if (in_array($estado, $estadosValidos)) {
                $query->whereHas('mora', function ($q) use ($estado) {
                    $q->where('estado_mora', $estado);
                });
            }
        }
    }
}
