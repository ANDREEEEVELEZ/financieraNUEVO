<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Pagos</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; margin: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ccc; padding: 4px; text-align: center; }
        th { background: #f0f0f0; }

        .header {
            text-align: right;
            font-size: 10px;
        }
    </style>
</head>
<body>
    <div class="header">
        Generado el: {{ now()->format('d/m/Y H:i:s') }}
    </div>

    <h2 style="text-align: center;">Reporte de Pagos Registrados</h2>

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

    <!-- Script PHP para numeración de páginas (correcto) -->
   <script type="text/php">
    if (isset($pdf)) {
        $pdf->page_script(function($pageNumber, $pageCount, $pdf, $fontMetrics) {
            $text = "Página $pageNumber de $pageCount";
            $font = $fontMetrics->getFont('Arial', 'normal');
            $size = 10;
            $x = 500;
            $y = 820;
            $pdf->text($x, $y, $text, $font, $size);
        });
    }
</script>
</body>
</html>
