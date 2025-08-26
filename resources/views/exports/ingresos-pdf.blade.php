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
            font-size: 11px;
        }

        th {
            background-color: #4a90e2;
            color: white;
            padding: 10px 8px;
            text-align: left;
            font-weight: bold;
            border: 1px solid #ddd;
        }

        td {
            padding: 8px;
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
            font-size: 12px;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .badge {
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 10px;
            font-weight: bold;
            color: white !important;
            display: inline-block;
        }

        .badge-transferencia {
            background-color: #28a745 !important;
            color: white !important;
        }

        .badge-pago_cuota {
            background-color: #007bff !important;
            color: white !important;
        }

        .tipo-text {
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

        @if($tipoIngreso !== 'todos')
            <p>Tipo:
                @if($tipoIngreso === 'transferencia')
                    Transferencias
                @elseif($tipoIngreso === 'pago_cuota')
                    Pagos de Cuota
                @else
                    {{ ucfirst($tipoIngreso) }}
                @endif
            </p>
        @endif
    </div>

    @if($ingresos->count() > 0)
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th style="width: 15%">Fecha/Hora</th>
                        <th style="width: 15%">Tipo</th>
                        <th style="width: 50%">Descripción</th>
                        <th style="width: 20%" class="text-right">Monto</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($ingresos as $ingreso)
                        <tr>
                            <td>{{ \Carbon\Carbon::parse($ingreso->fecha_hora)->format('d/m/Y H:i') }}</td>
                            <td>
                                @if($ingreso->tipo_ingreso === 'pago_cuota')
                                    <span style="color: #333;">
                                        Pago Cuota
                                    </span>
                                @elseif($ingreso->tipo_ingreso === 'transferencia')
                                    <span style="color: #333;">
                                        Transferencia
                                    </span>
                                @else
                                    <span style="color: #333;">
                                        {{ ucfirst($ingreso->tipo_ingreso ?? 'N/A') }}
                                    </span>
                                @endif
                            </td>
                            <td>{{ $ingreso->descripcion ?? 'Sin descripción' }}</td>
                            <td class="text-right" style="color: #333;">S/. {{ number_format($ingreso->monto ?? 0, 2, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="summary-box">
            <h3>RESUMEN TOTAL</h3>
            <div class="summary-item">
                <span>Total General: </span>
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
