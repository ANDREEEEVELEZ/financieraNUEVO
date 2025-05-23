<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Contrato de Préstamo</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; }
        h2, h3 { color: #2F5496; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #333; padding: 4px; }
        .section-title { background: #D9E1F2; font-weight: bold; }
        .datos { margin-top: 10px; }
    </style>
</head>
<body>
    <h2>CONTRATO DE PRÉSTAMO Y PAGARÉ POR MUTUO DINERARIO</h2>
    <p>
        Conste por el presente contrato privado de préstamo de dinero que celebran, de una parte, la empresa <b>"EMPRENDE CONMIGO SAC"</b>, identificada con RUC N° ________, con domicilio en __________, a quien en adelante se le denominará <b>"EL PRESTAMISTA"</b>; y de la otra parte, <b>{{ $cliente->nombre ?? '' }} {{ $cliente->apellidos ?? '' }}</b>, identificado con DNI N° {{ $cliente->DNI ?? '' }}, con domicilio en {{ $cliente->direccion ?? '' }}, a quien en adelante se le denominará <b>"EL PRESTATARIO"</b>.
    </p>
    <h3>PRIMERO: OBJETO DEL CONTRATO</h3>
    <p>
        EL PRESTAMISTA otorga en calidad de préstamo la cantidad de <b>S/. {{ number_format($monto, 2) }}</b> a EL PRESTATARIO, quien se compromete a devolver dicho monto conforme a las condiciones establecidas en el presente contrato.
    </p>
    <h3>SEGUNDO: PLAZO Y MODALIDAD DE PAGO</h3>
    <p>
        EL PRESTATARIO se compromete a devolver el préstamo en un plazo de <b>{{ $plazo }}</b> semanas a partir de la fecha de firma del contrato. El pago se realizará en cuotas semanales de <b>S/. {{ number_format($cuota, 2) }}</b> cada una, sumando un monto total a devolver de <b>S/. {{ number_format($total, 2) }}</b>.
    </p>
    <h3>TERCERO: INTERESES Y SEGURO</h3>
    <p>
        El préstamo incluye un Seguro Desgravamen de <b>S/. {{ number_format($seguro, 2) }}</b> que deberá ser asumido por EL PRESTATARIO junto con sus pagos. En caso de mora, se aplicará un recargo de <b>S/. 1.00</b> por día de atraso.
    </p>
    <h3>CUARTO: GARANTÍA</h3>
    <p>
        EL PRESTATARIO deja como garantía personal sus bienes muebles y se compromete a cumplir con los pagos dentro del plazo establecido. En caso de incumplimiento, EL PRESTAMISTA podrá tomar acciones legales correspondientes.
    </p>
    <h3>QUINTO: JURISDICCIÓN</h3>
    <p>
        Las partes acuerdan que cualquier controversia derivada del presente contrato será resuelta en los tribunales de la jurisdicción de ____________.
    </p>
    <div class="datos">
        <span class="section-title">DATOS DEL PRESTATARIO</span>
        <table>
            <tr><th>Nombre Completo:</th><td>{{ $cliente->nombre ?? '' }} {{ $cliente->apellidos ?? '' }}</td></tr>
            <tr><th>DNI:</th><td>{{ $cliente->DNI ?? '' }}</td></tr>
            <tr><th>Dirección:</th><td>{{ $cliente->direccion ?? '' }}</td></tr>
            <tr><th>Teléfono:</th><td>{{ $cliente->celular ?? '' }}</td></tr>
            <tr><th>Monto Prestado:</th><td>S/. {{ number_format($monto, 2) }}</td></tr>
            <tr><th>Ciclo de Crédito:</th><td>{{ $ciclo ?? '' }}</td></tr>
        </table>
    </div>
</body>
</html>
