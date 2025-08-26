<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $titulo ?? 'Reporte de Egresos' }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 10px;
            margin: 15px;
        }
        .header {
            text-align: center;
            margin-bottom: 15px;
        }
        .title {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .info {
            text-align: right;
            margin-bottom: 15px;
            font-size: 9px;
            color: #666;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        th, td {
            border: 1px solid #ccc;
            padding: 6px;
            text-align: left;
            font-size: 9px;
        }
        th {
            background-color: #f0f0f0;
            font-weight: bold;
            text-align: center;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .total-row {
            background-color: #f9f9f9;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="title">{{ $titulo ?? 'REPORTE DE EGRESOS' }}</div>
        @if(isset($fechaDesde) || isset($fechaHasta))
            <div style="font-size: 12px; color: #666; margin-top: 5px;">
                @if($fechaDesde && $fechaHasta)
                    Período: {{ $fechaDesde }} - {{ $fechaHasta }}
                @elseif($fechaDesde)
                    Desde: {{ $fechaDesde }}
                @elseif($fechaHasta)
                    Hasta: {{ $fechaHasta }}
                @endif
            </div>
        @endif
    </div>

    <div class="info">
        Generado el: {{ $fechaGeneracion ?? now()->format('d/m/Y H:i:s') }}
    </div>

    <table>
        <thead>
            <tr>
                <th width="10%">Fecha</th>
                <th width="10%">Tipo</th>
                <th width="15%">Categoría</th>
                <th width="25%">Descripción</th>
                <th width="12%">Monto</th>
                <th width="15%">Grupo</th>
                <th width="13%">Beneficiario</th>
            </tr>
        </thead>
        <tbody>
            @forelse($egresos as $egreso)
                <tr>
                    <td class="text-center">{{ \Carbon\Carbon::parse($egreso->fecha)->format('d/m/Y') }}</td>
                    <td class="text-center">{{ ucfirst($egreso->tipo_egreso ?? 'N/A') }}</td>
                    <td>{{ $egreso->categoria->nombre ?? 'Sin categoría' }}</td>
                    <td>{{ $egreso->descripcion ?? 'Sin descripción' }}</td>
                    <td class="text-right">${{ number_format($egreso->monto ?? 0, 2, ',', '.') }}</td>
                    <td>{{ $egreso->prestamo->grupo->nombre ?? 'N/A' }}</td>
                    <td>{{ $egreso->beneficiario_destino ?? 'N/A' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="text-center">No hay registros para mostrar</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    @if($egresos->count() > 0)
        <table>
            <tr class="total-row">
                <td width="70%" class="text-right"><strong>TOTAL GENERAL:</strong></td>
                <td width="15%" class="text-right"><strong>${{ number_format($totalMonto ?? 0, 2, ',', '.') }}</strong></td>
                <td width="15%" class="text-center"><strong>{{ $totalRegistros ?? 0 }} registros</strong></td>
            </tr>
        </table>
    @endif

</body>
</html>
        }
        .total-row {
            background-color: #fff3cd;
            font-weight: bold;
        }
        .monto {
            text-align: right;
            font-family: monospace;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="title">{{ $titulo }}</div>
        @if($fechaReporte)
            <div class="subtitle">{{ $fechaReporte }}</div>
        @endif
    </div>

    <div class="info">
        Generado el: {{ $fechaGeneracion }}
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 10%;">Fecha</th>
                <th style="width: 10%;">Tipo</th>
                <th style="width: 15%;">Grupo/Préstamo</th>
                <th style="width: 12%;">Categoría</th>
                <th style="width: 12%;">Subcategoría</th>
                <th style="width: 26%;">Descripción</th>
                <th style="width: 10%;">Monto</th>
                <th style="width: 5%;">Estado</th>
            </tr>
        </thead>
        <tbody>
            @forelse($egresos as $egreso)
                <tr>
                    <td class="text-center">
                        {{ $egreso->fecha ? $egreso->fecha->format('d/m/Y') : '' }}
                    </td>
                    <td class="text-center">
                        {{ ucfirst($egreso->tipo_egreso) }}
                    </td>
                    <td>
                        {{ $egreso->prestamo && $egreso->prestamo->grupo
                           ? $egreso->prestamo->grupo->nombre_grupo
                           : '' }}
                    </td>
                    <td>
                        {{ $egreso->categoria ? $egreso->categoria->nombre_categoria : '' }}
                    </td>
                    <td>
                        {{ $egreso->subcategoria ? $egreso->subcategoria->nombre_subcategoria : '' }}
                    </td>
                    <td>
                        {{ $egreso->descripcion }}
                    </td>
                    <td class="monto">
                        S/ {{ number_format($egreso->monto, 2) }}
                    </td>
                    <td class="text-center">
                        Registrado
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="text-center">
                        No se encontraron registros para los filtros seleccionados.
                    </td>
                </tr>
            @endforelse
        </tbody>
        @if($egresos->count() > 0)
            <tfoot>
                <tr class="total-row">
                    <td colspan="6" class="text-right"><strong>TOTAL:</strong></td>
                    <td class="monto"><strong>S/ {{ number_format($totalMonto, 2) }}</strong></td>
                    <td></td>
                </tr>
            </tfoot>
        @endif
    </table>

    @if($egresos->count() > 0)
        <div style="margin-top: 20px; font-size: 11px;">
            <strong>Resumen:</strong><br>
            Total de registros: {{ $egresos->count() }}<br>
            Monto total: S/ {{ number_format($totalMonto, 2) }}
        </div>
    @endif
</body>
</html>
