<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class ReporteProfesionalService
{
    public function generarReportePagos($pagos, $filtros = [])
    {
        $totalRegistros = $pagos->count();
        $totalMonto = $pagos->sum('monto_pagado');
        $totalMora = $pagos->sum('monto_mora_pagada');
        $fechaGeneracion = now()->format('d/m/Y H:i:s');



        $html = $this->generarHTMLProfesional($pagos, $totalRegistros, $totalMonto, $totalMora, $fechaGeneracion, $filtros);

        // isPhpEnabled and isRemoteEnabled MUST remain false (config/dompdf.php defaults).
        // PHP-in-PDF is an RCE vector; remote resources enable SSRF.
        $pdf = Pdf::loadHTML($html)
            ->setOptions([
                'defaultFont' => 'Arial',
                'defaultPaperSize' => 'a4',
                'dpi' => 150,
                'fontHeightRatio' => 1.1,
                'chroot' => public_path(),
                'isUnicode' => true,
                'isHtml5ParserEnabled' => true,
                'isFontSubsettingEnabled' => true,
            ])
            ->setPaper('a4', 'portrait');

        return $pdf;
    }

    private function generarHTMLProfesional($pagos, $totalRegistros, $totalMonto, $totalMora, $fechaGeneracion, $filtros)
    {
        $granTotal = $totalMonto + $totalMora;

        $html = '<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de Pagos</title>
    <style>
        @page {
            margin: 15mm 10mm;
            @top-left {
                content: "Sistema Financiero";
                font-size: 9px;
                color: #666;
            }
            @top-right {
                content: "Página " counter(page) " de " counter(pages);
                font-size: 9px;
                color: #666;
            }
            @bottom-center {
                content: "Generado el ' . $fechaGeneracion . ' | Documento confidencial";
                font-size: 8px;
                color: #999;
            }
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 14px;
            line-height: 1.6;
            color: #2d3748;
            margin: 0;
            padding: 0;
            -webkit-font-smoothing: antialiased;
        }

        /* ENCABEZADO PROFESIONAL */
        .header-section {
            background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 50%, #1e40af 100%);
            color: white;
            padding: 25px 20px;
            margin: -10mm -10mm 20px -10mm;
            position: relative;
            overflow: hidden;
        }

        .header-section::before {
            content: "";
            position: absolute;
            top: -50%;
            right: -20%;
            width: 200px;
            height: 200px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
        }

        .header-content {
            position: relative;
            z-index: 2;
        }

        .company-logo {
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 5px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }

        .report-title {
            font-size: 20px;
            font-weight: 300;
            margin-bottom: 3px;
            opacity: 0.95;
        }

        .report-subtitle {
            font-size: 12px;
            opacity: 0.8;
            font-weight: 300;
        }

        /* SECCIÓN DE RESUMEN EJECUTIVO */
        .executive-summary {
            background: #f8fafc;
            border-left: 5px solid #3b82f6;
            padding: 20px;
            margin: 20px 0;
            border-radius: 0 8px 8px 0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .summary-title {
            font-size: 16px;
            font-weight: bold;
            color: #1e40af;
            margin-bottom: 15px;
            border-bottom: 2px solid #e5e7eb;
            padding-bottom: 5px;
        }

        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 15px;
        }

        .metric-card {
            background: white;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            border-top: 3px solid #3b82f6;
        }

        .metric-icon {
            font-size: 24px;
            margin-bottom: 8px;
            display: block;
        }

        .metric-value {
            font-size: 18px;
            font-weight: bold;
            color: #1e40af;
            margin-bottom: 3px;
        }

        .metric-label {
            font-size: 10px;
            color: #6b7280;
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.5px;
        }

        /* FILTROS APLICADOS */
        .filters-applied {
            background: #ecfdf5;
            border: 1px solid #10b981;
            border-radius: 6px;
            padding: 12px 15px;
            margin: 15px 0;
        }

        .filters-title {
            font-weight: bold;
            color: #047857;
            margin-bottom: 8px;
            font-size: 12px;
        }

        .filter-item {
            color: #065f46;
            font-size: 11px;
            margin: 3px 0;
        }

        /* TABLA PROFESIONAL */
        .data-section {
            margin: 25px 0;
        }

        .section-title {
            font-size: 16px;
            font-weight: bold;
            color: #1f2937;
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 2px solid #e5e7eb;
        }

        .professional-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            background: white;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            border-radius: 12px;
            overflow: hidden;
            border: 2px solid #1e40af;
        }

        .professional-table thead {
            background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%);
            color: white;
        }

        .professional-table th {
            padding: 20px 14px;
            text-align: left;
            font-weight: 800;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
            border-right: 2px solid rgba(255,255,255,0.4);
            background: linear-gradient(135deg, #1e40af 0%, #3b82f6 50%, #60a5fa 100%);
            color: #ffffff;
            text-shadow: 1px 1px 3px rgba(0,0,0,0.7);
            box-shadow: inset 0 -3px 6px rgba(0,0,0,0.3);
        }

        .professional-table th:last-child {
            border-right: none;
        }

        .professional-table td {
            padding: 16px 12px;
            border-bottom: 1px solid #cbd5e0;
            font-size: 13px;
            vertical-align: middle;
            line-height: 1.6;
            border-right: 1px solid #e2e8f0;
        }

        .professional-table tbody tr:nth-child(even) {
            background-color: #f7fafc;
        }

        .professional-table tbody tr:nth-child(odd) {
            background-color: #ffffff;
        }

        .professional-table tbody tr:hover {
            background-color: #edf2f7;
        }

        /* ESTADOS CON ESTILO */
        .status-badge {
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 10px;
            font-weight: 700;
            text-align: center;
            display: inline-block;
            min-width: 80px;
            letter-spacing: 0.5px;
        }

        .status-completado {
            background: #c6f6d5;
            color: #22543d;
            border: 2px solid #38a169;
        }

        .status-pendiente {
            background: #faf089;
            color: #744210;
            border: 2px solid #d69e2e;
        }

        .status-parcial {
            background: #fed7e2;
            color: #97266d;
            border: 2px solid #d53f8c;
        }

        .status-cancelado {
            background: #fed7d7;
            color: #742a2a;
            border: 2px solid #e53e3e;
        }

        /* OBSERVACIONES */
        .observaciones-cell {
            word-wrap: break-word;
            word-break: break-word;
            white-space: normal;
            max-width: 150px;
            font-size: 11px;
            line-height: 1.4;
            vertical-align: top;
        }

        /* MONTOS */
        .amount {
            text-align: right;
            font-weight: 600;
            font-family: "Courier New", monospace;
        }

        .amount-positive {
            color: #059669;
        }

        .amount-warning {
            color: #d97706;
        }

        /* FOOTER PROFESIONAL */
        .footer-section {
            background: #f8fafc;
            border-top: 3px solid #3b82f6;
            padding: 20px;
            margin: 30px -10mm -15mm -10mm;
            text-align: center;
        }

        .footer-totals {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 15px;
        }

        .footer-total {
            background: white;
            padding: 15px;
            border-radius: 6px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .footer-total-label {
            font-size: 11px;
            color: #6b7280;
            margin-bottom: 5px;
            text-transform: uppercase;
            font-weight: 600;
        }

        .footer-total-value {
            font-size: 18px;
            font-weight: bold;
            color: #1e40af;
        }

        .grand-total {
            background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%);
            color: white;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
            text-align: center;
        }

        .grand-total-label {
            font-size: 12px;
            opacity: 0.9;
            margin-bottom: 5px;
        }

        .grand-total-value {
            font-size: 24px;
            font-weight: bold;
        }

        /* UTILIDADES */
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .font-bold { font-weight: bold; }
        .text-sm { font-size: 10px; }
        .text-xs { font-size: 9px; }
        .mb-2 { margin-bottom: 8px; }
        .mt-4 { margin-top: 16px; }

        /* NO DATA STATE */
        .no-data {
            text-align: center;
            padding: 40px 20px;
            color: #6b7280;
            font-style: italic;
            background: #f9fafb;
            border-radius: 8px;
            border: 2px dashed #d1d5db;
        }

        .no-data-icon {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.5;
        }
    </style>
</head>
<body>
    <!-- ENCABEZADO PROFESIONAL -->
    <div class="header-section">
        <div class="header-content">
            <div class="company-logo">SISTEMA FINANCIERO</div>
            <div class="report-title">REPORTE DE PAGOS</div>
            <div class="report-subtitle">Sistema de Control Financiero</div>
        </div>
    </div>';

        // Filtros aplicados
        if (!empty($filtros)) {
            $html .= '<div class="filters-applied">
                <div class="filters-title">FILTROS APLICADOS</div>';

            foreach ($filtros as $filtro) {
                $html .= '<div class="filter-item">• ' . $filtro . '</div>';
            }

            $html .= '</div>';
        }

        // Sección de datos
        $html .= '<div class="data-section">
        <div class="section-title">DETALLE DE TRANSACCIONES</div>';

        if ($pagos->count() === 0) {
            $html .= '<div class="no-data">
                <div class="no-data-icon">!</div>
                <div><strong>No se encontraron registros</strong></div>
                <div>No hay datos que coincidan con los filtros aplicados</div>
            </div>';
        } else {
            $html .= '<table class="professional-table">
                <thead>
                    <tr>
                        <th style="width: 100px;">Fecha</th>
                        <th style="width: 160px;">Grupo</th>
                        <th style="width: 90px;">Tipo</th>
                        <th style="width: 100px;">Código</th>
                        <th style="width: 90px;">Monto</th>
                        <th style="width: 90px;">Mora</th>
                        <th style="width: 100px;">Estado</th>
                        <th>Observaciones</th>
                    </tr>
                </thead>
                <tbody>';

            foreach ($pagos as $pago) {
                $fecha = $pago->fecha_pago ? Carbon::parse($pago->fecha_pago)->format('d/m/Y') : 'N/A';
                $fechaHora = $pago->fecha_pago ? Carbon::parse($pago->fecha_pago)->format('H:i') : '';
                $grupo = $pago->cuotaGrupal->prestamo->grupo->nombre_grupo ?? 'Sin grupo';



                $tipo = $pago->tipo_pago ?? 'N/A';
                $codigo = $pago->codigo_operacion ?? 'N/A';
                $monto = number_format($pago->monto_pagado ?? 0, 2);
                $mora = number_format($pago->monto_mora_pagada ?? 0, 2);
                $estado = $this->formatearEstadoProfesional($pago->estado_pago ?? 'N/A');
                $obs = $pago->observaciones ?? 'Sin observaciones';
                $obsCorta = $obs; // Mostrar texto completo

                $html .= "<tr>
                    <td>
                        <div class='font-bold'>{$fecha}</div>
                        <div class='text-xs' style='color: #6b7280;'>{$fechaHora}</div>
                    </td>
                    <td>{$grupo}</td>
                    <td>{$tipo}</td>
                    <td class='text-xs'>{$codigo}</td>
                    <td class='amount amount-positive'>S/{$monto}</td>
                    <td class='amount amount-warning'>S/{$mora}</td>
                    <td>{$estado}</td>
                    <td class='observaciones-cell'>{$obsCorta}</td>
                </tr>";
            }

            $html .= '</tbody></table>';
        }

        $html .= '</div>';

        // Footer profesional
        $html .= '<div class="footer-section">
        <div class="footer-totals">
            <div class="footer-total">
                <div class="footer-total-label">Registros Procesados</div>
                <div class="footer-total-value">' . number_format($totalRegistros) . '</div>
            </div>

            <div class="footer-total">
                <div class="footer-total-label">Monto de Pagos</div>
                <div class="footer-total-value">S/' . number_format($totalMonto, 2) . '</div>
            </div>

            <div class="footer-total">
                <div class="footer-total-label">Monto de Moras</div>
                <div class="footer-total-value">S/' . number_format($totalMora, 2) . '</div>
            </div>
        </div>

        <div class="grand-total">
            <div class="grand-total-label">TOTAL GENERAL</div>
            <div class="grand-total-value">S/' . number_format($granTotal, 2) . '</div>
        </div>

        <div style="margin-top: 20px; font-size: 10px; color: #718096; text-align: center;">
            <div style="margin-bottom: 5px;"><strong>Documento generado automáticamente el ' . $fechaGeneracion . '</strong></div>
            <div>Información confidencial - Uso interno únicamente</div>
        </div>
    </div>

</body>
</html>';

        return $html;
    }

    private function formatearEstadoProfesional($estado)
    {
        switch (strtolower($estado)) {
            case 'completado':
                return '<span class="status-badge status-completado">COMPLETADO</span>';
            case 'pendiente':
                return '<span class="status-badge status-pendiente">PENDIENTE</span>';
            case 'parcial':
                return '<span class="status-badge status-parcial">PARCIAL</span>';
            case 'cancelado':
                return '<span class="status-badge status-cancelado">CANCELADO</span>';
            default:
                return '<span class="status-badge status-parcial">' . strtoupper(htmlspecialchars($estado)) . '</span>';
        }
    }
}
