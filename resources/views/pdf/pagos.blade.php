<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Pagos</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ccc; padding: 4px; text-align: center; }
        th { background: #f0f0f0; }
    </style>
</head>
<body>
    <h2>Reporte de Pagos Registrados</h2>
    <table>
        <thead>
            <tr>
                <th>Grupo</th>
                <th>Cuota</th>
                <th>Tipo de Pago</th>
                <th>Monto Pagado</th>
                <th>Fecha de Pago</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
        @foreach($pagos as $pago)
            <tr>
                <td>{{ $pago->cuotaGrupal->prestamo->grupo->nombre_grupo ?? '-' }}</td>
                <td>{{ $pago->cuotaGrupal->numero_cuota ?? '-' }}</td>
                <td>{{ ucfirst(str_replace('_', ' + ', $pago->tipo_pago)) }}</td>
                <td>S/ {{ number_format($pago->monto_pagado, 2) }}</td>
                <td>{{ $pago->fecha_pago ? $pago->fecha_pago->format('d/m/Y') : '-' }}</td>
                <td>{{ $pago->estado_pago }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</body>
</html>
