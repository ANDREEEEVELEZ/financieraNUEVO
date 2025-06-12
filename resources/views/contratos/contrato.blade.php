<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>CONTRATO – PAGARE POR MUTUO DINERARIO</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; }
        h2, h3 { color: #2F5496; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #333; padding: 4px; text-align: center; }
        .section-title { background: #D9E1F2; font-weight: bold; }
        .datos { margin-top: 10px; }
        .resaltado { color: #2F5496; font-weight: bold; }
        .advertencia { color: #0070C0; font-weight: bold; }
        .cuadro { border: 1px solid #333; padding: 8px; margin-top: 10px; }
    </style>
</head>
<body>
    <h2 style="text-align:center;">CONTRATO – PAGARE POR MUTUO DINERARIO</h2>
    <p>
        Consta por el presente contrato Privado de una parte <b>"EMPRENDE CONMIGO SAC"</b> que en adelante se le denominará <b>PRESTAMISTA</b> y de otra parte la Sra. <b>{{ $cliente->nombre ?? '' }} {{ $cliente->apellidos ?? '' }}</b>, identificada con DNI: <b>{{ $cliente->DNI ?? '' }}</b> y domiciliado en <b>{{ $cliente->direccion ?? '' }}</b> a quien en adelante se le denominará <b>PRESTATARIO</b>.
    </p>
    <p><b>Ambas partes llegan a los siguientes acuerdos:</b></p>
    <p><b>PRIMERO.</b> EL PRESTAMISTA cederá en calidad de préstamo al PRESTATARIO la suma S/. <b>{{ number_format($monto, 2) }}</b> ({{ $monto_letras ?? '________' }}). Este monto será pagado por el PRESTATARIO una vez culminado el plazo pactado.</p>
    <p><b>SEGUNDO.</b> Con la firma del siguiente documento, el PRESTATARIO se compromete a devolver el préstamo en el lapso de <b>{{ $plazo * 7 }}</b> días como máximo a partir de la firma del presente contrato y el pago será de manera semanal generando un interés del <b>{{ $interes ?? '17' }}%</b> mensual. Por tanto, el PRESTATARIO está en el deber de devolver la cantidad de S/. <b>{{ number_format($total, 2) }}</b> una vez culminado el presente contrato.</p>
    <p><b>TERCERO.</b> En caso de incumplimiento de pago por parte del PRESTATARIO, el PRESTAMISTA tomará las medidas de cobranza necesarias para la recuperación del crédito.</p>
    <p><b>CUARTO.</b> El presente contrato incluye un Seguro Desgravamen, el cual se hace efectivo, en caso de fallecimiento del titular, cancelando la deuda total.</p>
    <p><b>QUINTO.</b> Ambas PARTES señalan y aseguran que en la celebración del presente contrato no ha mediado error, dolo o nulidad que pudiera invalidar el contenido del mismo, por lo que proceden a firmar en el lugar y fecha correspondiente.</p>

    <div class="cuadro">
        <span class="section-title">CRÉDITO/DESEMBOLSO</span>
        <table>
            <tr>
                <th>MONTO</th>
                <th>SEGURO</th>
                <th>INTERES</th>
                <th>CUOTAS</th>
                <th>CAPITAL+SEGURO+INTERES</th>
            </tr>
            <tr>
                <td>S/. {{ number_format($monto, 2) }}</td>
                <td>S/. {{ number_format($seguro, 2) }}</td>
                <td>{{ $interes ?? '17' }}%</td>
                <td>{{ $plazo }}</td>
                <td>S/. {{ number_format($total, 2) }}</td>
            </tr>
        </table>
        <br>
        <span class="section-title">CRONOGRAMA DE PAGOS</span>
        <table>
            <tr>
                <th>CUOTA</th>
                <th>FECHA</th>
                <th>TOTAL A PAGAR</th>
            </tr>
            @foreach($cronograma as $i => $cuota)
            <tr>
                <td>{{ $i+1 }}</td>
                <td>{{ \Carbon\Carbon::parse($cuota['fecha'])->format('d/m/Y') }}</td>
                <td>S/. {{ number_format($cuota['monto'], 2) }}</td>
            </tr>
            @endforeach
        </table>
    </div>
    <br>
    <p class="advertencia">*Estimado cliente EVITE EL PAGO DE INTERÉS MORATORIO, a partir del primer día de atraso UD. CANCELARÁ S/. 1.00 POR DÍA EN MORA.</p>
    <div class="cuadro">
        <b>CUENTA BCP:</b> 535026353940327<br>
        <b>CCI:</b> 00253510263539403237<br>
        <span style="color:#C00000; font-weight:bold;">IMPORTANTE: TODO PAGO DEBE SER REALIZADO A LA CUENTA BRINDADA. EL ASESOR NO ESTÁ AUTORIZADO A RECIBIR DINERO FÍSICO NI VIRTUAL (YAPE/TRANSFERENCIAS)</span><br>
        <b>NRO PARA CONSULTAS:</b> 922 185 917
    </div>
</body>
</html>
