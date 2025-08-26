<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $titulo }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Arial', sans-serif;
            font-size: 10px;
            line-height: 1.3;
            color: #333;
            background-color: #fff;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
            padding: 15px 0;
            border-bottom: 2px solid #e74c3c;
        }

        .header h1 {
            font-size: 18px;
            color: #e74c3c;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .header .subtitle {
            font-size: 12px;
            color: #666;
            margin-bottom: 10px;
        }

        .info-section {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            padding: 10px;
            background-color: #f8f9fa;
            border-radius: 5px;
        }

        .info-left, .info-right {
            width: 48%;
        }

        .info-item {
            margin-bottom: 5px;
            font-size: 9px;
        }

        .info-label {
            font-weight: bold;
            color: #e74c3c;
        }

        .table-container {
            width: 100%;
            margin-bottom: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background-color: #fff;
        }

        th {
            background-color: #e74c3c;
            color: white;
            padding: 8px 4px;
            text-align: center;
            font-weight: bold;
            font-size: 9px;
            border: 1px solid #c0392b;
        }

        td {
            padding: 6px 4px;
            text-align: center;
            font-size: 8px;
            border: 1px solid #ddd;
            vertical-align: middle;
        }

        tr:nth-child(even) {
            background-color: #f8f9fa;
        }

        tr:hover {
            background-color: #fff5f5;
        }

        .estado-pendiente { color: #f39c12; font-weight: bold; }
        .estado-pagada { color: #27ae60; font-weight: bold; }
        .estado-parcial { color: #3498db; font-weight: bold; }
        .estado-parcialmente_pagada { color: #e67e22; font-weight: bold; }

        .monto {
            text-align: right;
            font-weight: bold;
            color: #e74c3c;
        }

        .footer {
            margin-top: 20px;
            padding: 15px 0;
            border-top: 2px solid #e74c3c;
            text-align: center;
        }

        .summary {
            display: flex;
            justify-content: space-around;
            margin-bottom: 10px;
        }

        .summary-item {
            text-align: center;
            flex: 1;
        }

        .summary-label {
            font-size: 9px;
            color: #666;
            display: block;
        }

        .summary-value {
            font-size: 12px;
            font-weight: bold;
            color: #e74c3c;
        }

        .no-data {
            text-align: center;
            padding: 40px;
            color: #666;
            font-style: italic;
        }

        .page-break {
            page-break-before: always;
        }

        @media print {
            body { print-color-adjust: exact; }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $titulo }}</h1>
        <div class="subtitle">Sistema de Gestión Financiera</div>
    </div>

    <div class="info-section">
        <div class="info-left">
            <div class="info-item">
                <span class="info-label">Fecha de generación:</span> {{ $fechaGeneracion }}
            </div>
            <div class="info-item">
                <span class="info-label">Grupo seleccionado:</span> {{ $grupoSeleccionado }}
            </div>
            <div class="info-item">
                <span class="info-label">Estado de mora:</span>
                @if($estadoMora === 'todos')
                    Todos los estados
                @else
                    {{ ucfirst($estadoMora) }}
                @endif
            </div>
        </div>
        <div class="info-right">
            <div class="info-item">
                <span class="info-label">Total de registros:</span> {{ $totalRegistros }}
            </div>
            <div class="info-item">
                <span class="info-label">Monto total mora:</span> S/. {{ number_format($totalMonto, 2) }}
            </div>
            <div class="info-item">
                <span class="info-label">Usuario:</span> {{ request()->user()->name ?? 'Sistema' }}
            </div>
        </div>
    </div>

    @if($moras->count() > 0)
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th style="width: 12%">Fecha Atraso</th>
                        <th style="width: 18%">Grupo</th>
                        <th style="width: 8%">Cuota</th>
                        <th style="width: 10%">Días Atraso</th>
                        <th style="width: 12%">Monto Mora</th>
                        <th style="width: 12%">Monto Pagado</th>
                        <th style="width: 12%">Estado</th>
                        <th style="width: 16%">Observaciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($moras as $mora)
                        <tr>
                            <td>{{ $mora->fecha_atraso ? \Carbon\Carbon::parse($mora->fecha_atraso)->format('d/m/Y') : 'N/A' }}</td>
                            <td style="text-align: left;">{{ $mora->cuotaGrupal->prestamo->grupo->nombre_grupo ?? 'N/A' }}</td>
                            <td>{{ $mora->cuotaGrupal->numero_cuota ?? 'N/A' }}</td>
                            <td>{{ $mora->dias_atraso ?? 0 }}</td>
                            <td class="monto">S/. {{ number_format($mora->monto_mora ?? 0, 2) }}</td>
                            <td class="monto">S/. {{ number_format($mora->monto_pagado ?? 0, 2) }}</td>
                            <td class="estado-{{ $mora->estado_mora ?? 'pendiente' }}">
                                @switch($mora->estado_mora)
                                    @case('pendiente')
                                        Pendiente
                                        @break
                                    @case('pagada')
                                        Pagada
                                        @break
                                    @case('parcial')
                                        Parcial
                                        @break
                                    @case('parcialmente_pagada')
                                        Parc. Pagada
                                        @break
                                    @default
                                        {{ $mora->estado_mora ?? 'N/A' }}
                                @endswitch
                            </td>
                            <td style="text-align: left; font-size: 7px;">{{ $mora->observaciones ?? 'Sin observaciones' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="footer">
            <div class="summary">
                <div class="summary-item">
                    <span class="summary-label">Total Registros</span>
                    <span class="summary-value">{{ $totalRegistros }}</span>
                </div>
                <div class="summary-item">
                    <span class="summary-label">Monto Total Mora</span>
                    <span class="summary-value">S/. {{ number_format($totalMonto, 2) }}</span>
                </div>
                <div class="summary-item">
                    <span class="summary-label">Promedio por Mora</span>
                    <span class="summary-value">S/. {{ $totalRegistros > 0 ? number_format($totalMonto / $totalRegistros, 2) : '0.00' }}</span>
                </div>
            </div>
            <div style="margin-top: 10px; font-size: 8px; color: #666;">
                Generado el {{ $fechaGeneracion }} - Sistema de Gestión Financiera
            </div>
        </div>
    @else
        <div class="no-data">
            <h3>No se encontraron moras</h3>
            <p>No hay registros que coincidan con los filtros aplicados.</p>
        </div>
    @endif
</body>
</html>
