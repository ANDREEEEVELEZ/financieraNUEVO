<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $titulo }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            margin: 0;
            padding: 20px;
            color: #333;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #4a90e2;
            padding-bottom: 15px;
        }

        .header h1 {
            color: #4a90e2;
            margin: 0;
            font-size: 20px;
            font-weight: bold;
        }

        .info-section {
            margin-bottom: 20px;
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            border-left: 4px solid #4a90e2;
        }

        .info-section p {
            margin: 5px 0;
            font-weight: bold;
        }

        .table-container {
            margin-bottom: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            font-size: 10px;
        }

        th {
            background-color: #4a90e2;
            color: white;
            padding: 8px 6px;
            text-align: left;
            font-weight: bold;
            border: 1px solid #ddd;
        }

        td {
            padding: 6px;
            border: 1px solid #ddd;
            vertical-align: top;
        }

        tr:nth-child(even) {
            background-color: #f8f9fa;
        }

        tr:hover {
            background-color: #e3f2fd;
        }

        .total-row {
            background-color: #e8f4fd !important;
            font-weight: bold;
            border-top: 2px solid #4a90e2;
        }

        .total-row td {
            font-weight: bold;
            font-size: 11px;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .badge {
            padding: 3px 6px;
            border-radius: 3px;
            font-size: 9px;
            font-weight: bold;
            color: white !important;
            display: inline-block;
        }

        .badge-aprobado {
            background-color: #28a745 !important;
            color: white !important;
        }

        .badge-pendiente {
            background-color: #ffc107 !important;
            color: white !important;
        }

        .badge-rechazado {
            background-color: #dc3545 !important;
            color: white !important;
        }

        .estado-text {
            font-weight: bold;
            color: #333 !important;
        }

        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 10px;
            color: #666;
            border-top: 1px solid #ddd;
            padding-top: 15px;
        }

        .summary-box {
            background-color: #f0f8ff;
            border: 2px solid #4a90e2;
            border-radius: 8px;
            padding: 15px;
            margin-top: 20px;
            text-align: center;
        }

        .summary-box h3 {
            color: #4a90e2;
            margin: 0 0 10px 0;
            font-size: 16px;
        }

        .summary-item {
            display: inline-block;
            margin: 0 20px;
            font-weight: bold;
        }

        .money {
            color: #333;
            font-size: 13px;
        }

        .no-data {
            text-align: center;
            padding: 40px;
            color: #666;
            font-style: italic;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $titulo }}</h1>
    </div>

    <div class="info-section">
        @if($fechaDesde || $fechaHasta)
            <p>Período:
                @if($fechaDesde) Desde {{ $fechaDesde }} @endif
                @if($fechaDesde && $fechaHasta) - @endif
                @if($fechaHasta) Hasta {{ $fechaHasta }} @endif
            </p>
        @else
            <p>Período: Todos los registros</p>
        @endif

        @if($grupoSeleccionado)
            <p>Grupo: {{ $grupoSeleccionado }}</p>
        @endif

        @if($estadoPago !== 'todos')
            <p>Estado: {{ ucfirst($estadoPago) }}</p>
        @endif
    </div>

    @if($pagos->count() > 0)
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th style="width: 12%">Fecha</th>
                        <th style="width: 20%">Grupo</th>
                        <th style="width: 12%">Tipo Pago</th>
                        <th style="width: 15%">Código Op.</th>
                        <th style="width: 12%" class="text-right">Monto</th>
                        <th style="width: 12%" class="text-right">Mora</th>
                        <th style="width: 10%">Estado</th>
                        <th style="width: 7%">Observ.</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($pagos as $pago)
                        <tr>
                            <td>{{ \Carbon\Carbon::parse($pago->fecha_pago)->format('d/m/Y') }}</td>
                            <td>{{ $pago->cuotaGrupal->prestamo->grupo->nombre_grupo ?? 'N/A' }}</td>
                            <td>{{ $pago->tipo_pago ?? 'N/A' }}</td>
                            <td>{{ $pago->codigo_operacion ?? 'N/A' }}</td>
                            <td class="text-right" style="color: #333;">S/. {{ number_format($pago->monto_pagado ?? 0, 2, ',', '.') }}</td>
                            <td class="text-right" style="color: #333;">S/. {{ number_format($pago->monto_mora_pagada ?? 0, 2, ',', '.') }}</td>
                            <td class="text-center">
                                @if(strtolower($pago->estado_pago) === 'aprobado')
                                    <span style="color: #28a745;">Aprobado</span>
                                @elseif(strtolower($pago->estado_pago) === 'pendiente')
                                    <span style="color: #ffc107;">Pendiente</span>
                                @elseif(strtolower($pago->estado_pago) === 'rechazado')
                                    <span style="color: #dc3545;">Rechazado</span>
                                @else
                                    <span style="color: #333;">{{ $pago->estado_pago ?? 'N/A' }}</span>
                                @endif
                            </td>
                            <td>{{ Str::limit($pago->observaciones ?? '', 20) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="summary-box">
            <h3>RESUMEN TOTAL</h3>
            <div class="summary-item">
                <span>Total Pagado: </span>
                <span class="money" style="font-size: 18px;">S/. {{ number_format($totalMonto, 2, ',', '.') }}</span>
            </div>
            <div class="summary-item">
                <span>Registros: </span>
                <span style="color: #4a90e2; font-size: 16px;">{{ $totalRegistros }}</span>
            </div>
        </div>
    @else
        <div class="no-data">
            <p>No se encontraron registros para mostrar con los filtros seleccionados.</p>
        </div>
    @endif

    <div class="footer">
        <p>Este reporte fue generado automáticamente el {{ $fechaGeneracion }}</p>
        <p>Sistema de Gestión Financiera</p>
    </div>
</body>
</html>
