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

        @php
            // Calcular totales para el resumen ejecutivo
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
        @endphp

        <!-- Resumen Ejecutivo al Final -->
        <div style="margin-top: 20px; background: #f8fafc; border: 1px solid #3b82f6; padding: 8px;">
            <h3 style="margin: 0 0 8px 0; color: #1e40af; font-size: 11px; text-align: center;">RESUMEN EJECUTIVO</h3>

            <table style="width: 100%; border-collapse: collapse; margin: 0;">
                <tr>
                    <td style="padding: 4px; border: 1px solid #cbd5e0; background: #f8fafc; font-weight: bold; width: 50%; font-size: 8px;">Total Registros:</td>
                    <td style="padding: 4px; border: 1px solid #cbd5e0; background: white; font-weight: bold; color: #1e40af; font-size: 8px;">{{ number_format($totalRegistros) }}</td>
                </tr>
                <tr>
                    <td style="padding: 4px; border: 1px solid #cbd5e0; background: #f8fafc; font-weight: bold; font-size: 8px;">Monto Total Cuotas:</td>
                    <td style="padding: 4px; border: 1px solid #cbd5e0; background: white; font-weight: bold; color: #059669; font-size: 8px;">S/ {{ number_format($totalMontoCuotas, 2) }}</td>
                </tr>
                <tr>
                    <td style="padding: 4px; border: 1px solid #cbd5e0; background: #f8fafc; font-weight: bold; font-size: 8px;">Saldo Pendiente:</td>
                    <td style="padding: 4px; border: 1px solid #cbd5e0; background: white; font-weight: bold; color: #d97706; font-size: 8px;">S/ {{ number_format($totalSaldoPendiente, 2) }}</td>
                </tr>
                <tr>
                    <td style="padding: 4px; border: 1px solid #cbd5e0; background: #f8fafc; font-weight: bold; font-size: 8px;">Mora Pendiente:</td>
                    <td style="padding: 4px; border: 1px solid #cbd5e0; background: white; font-weight: bold; color: #dc2626; font-size: 8px;">S/ {{ number_format($totalMoraPendiente, 2) }}</td>
                </tr>
                <tr>
                    <td style="padding: 4px; border: 1px solid #cbd5e0; background: #f8fafc; font-weight: bold; font-size: 8px;">Mora Pagada:</td>
                    <td style="padding: 4px; border: 1px solid #cbd5e0; background: white; font-weight: bold; color: #059669; font-size: 8px;">S/ {{ number_format($totalMoraPagada, 2) }}</td>
                </tr>
                <tr>
                    <td style="padding: 5px; border: 1px solid #1e40af; background: #1e40af; font-weight: bold; color: white; font-size: 9px;">TOTAL GENERAL:</td>
                    <td style="padding: 5px; border: 1px solid #1e40af; background: #1e40af; font-weight: bold; color: white; font-size: 9px;">S/ {{ number_format($totalGeneral, 2) }}</td>
                </tr>
            </table>
        </div>

        <div style="margin-top: 10px; text-align: center; font-size: 7px; color: #6b7280;">
            <p style="margin: 0;"><strong>Documento generado automáticamente el {{ now()->format('d/m/Y H:i:s') }}</strong></p>
            <p style="margin: 0;">Información confidencial - Uso interno únicamente</p>
        </div>
    </main>
</body>
</html>
