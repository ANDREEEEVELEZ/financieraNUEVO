<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Moras</title>
    <style>
        @page {
            margin: 80px 25px;
            size: A4 landscape;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
        }

        header {
            position: fixed;
            top: -60px;
            left: 0px;
            right: 0px;
            height: 40px;
            text-align: right;
            font-size: 12px;
            color: #555;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        table th, table td {
            border: 1px solid #aaa;
            padding: 3px;
            text-align: center;
            font-size: 9px;
        }

        table th {
            background-color: #f2f2f2;
            font-weight: bold;
            font-size: 9px;
        }

        h2 {
            font-size: 16px;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <header>
        Generado el {{ now()->format('d/m/Y H:i') }}
    </header>



    <main>
        <h2 style="text-align: center; margin-bottom: 10px;">Reporte de Moras</h2>

        <table>
            <thead>
                <tr>
                    <th>Nombre del Grupo</th>
                    <th>N° Integrantes</th>
                    <th>N° Cuota</th>
                    <th>Monto de Cuota</th>
                    <th>Fecha Vencimiento</th>
                    <th>Saldo pendiente</th>
                    <th>Días de Atraso</th>
                    <th>Monto Mora pendiente</th>
                    <th>Monto Mora Pagada</th>
                    <th>Monto total a pagar</th>
                    <th>Estado</th>
                </tr>
            </thead>
        <tbody>
        @foreach($cuotas_mora as $cuota)
            <tr>
                <td>{{ $cuota->prestamo->grupo->nombre_grupo ?? '-' }}</td>
                <td>{{ $cuota->prestamo->grupo->clientes()->count() ?? 0 }}</td>
                <td>{{ $cuota->numero_cuota ?? '-' }}</td>
                <td>S/ {{ number_format($cuota->monto_cuota_grupal, 2) }}</td>
                <td>{{ $cuota->fecha_vencimiento ? \Carbon\Carbon::parse($cuota->fecha_vencimiento)->format('d/m/Y') : '-' }}</td>
                <td>S/ {{ number_format($cuota->saldo_pendiente, 2) }}</td>
                <td>
                @if($cuota->mora)
                    {{ $cuota->mora->dias_atraso }}
                @else
                    @php
                        $diasAtraso = 0;
                        if ($cuota->fecha_vencimiento && $cuota->estado_pago !== 'pagado' && $cuota->estado_cuota_grupal !== 'cancelada') {
                            $fechaVencimiento = \Carbon\Carbon::parse($cuota->fecha_vencimiento)->addDay()->startOfDay();
                            $diasAtraso = max(0, floor($fechaVencimiento->diffInDays(now())));
                        }
                    @endphp
                    {{ $diasAtraso }}
                @endif
                </td>
                <td>
                    @php
                        $moraPendiente = $cuota->getSaldoMoraPendiente();
                    @endphp
                    S/ {{ number_format($moraPendiente, 2) }}
                </td>
                <td>
                    @php
                        $moraPagada = $cuota->getMoraPagada();
                    @endphp
                    S/ {{ number_format($moraPagada, 2) }}
                </td>
                <td>
                    @php
                        $saldoCuotaPendiente = $cuota->getSaldoCuotaPendiente();
                        $moraPendiente = $cuota->getSaldoMoraPendiente();
                        $montoTotal = $saldoCuotaPendiente + $moraPendiente;
                    @endphp
                    S/ {{ number_format($montoTotal, 2) }}
                </td>
                <td>
                    @if($cuota->mora)
                        @if($cuota->mora->estado_mora === 'pendiente') Pendiente
                        @elseif($cuota->mora->estado_mora === 'pagada') Pagada
                        @elseif($cuota->mora->estado_mora === 'parcialmente_pagada') Parcial
                        @else {{ ucfirst(str_replace('_', ' ', $cuota->mora->estado_mora)) }}
                        @endif
                    @else
                        Sin mora
                    @endif
                </td>
            </tr>
        @endforeach
            </tbody>
        </table>
    </main>
</body>
</html>
