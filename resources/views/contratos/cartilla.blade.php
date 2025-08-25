<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>CARTILLA DE IDENTIFICACION</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            font-size: 14px; 
            margin: 0;
            padding: 20px;
            line-height: 1.4;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .logo {
            width: 150px;
            height: auto;
            margin-bottom: 10px;
        }
        .title {
            font-size: 18px;
            font-weight: bold;
            text-transform: uppercase;
            color: #2F5496;
            margin-bottom: 20px;
        }
        .info-section {
            margin-bottom: 25px;
        }
        .fecha-contrato {
            text-align: center;
            font-weight: bold;
            margin-bottom: 20px;
            font-size: 16px;
        }
        .contrato-info {
            text-align: justify;
            margin-bottom: 20px;
            line-height: 1.5;
        }
        .grupo-info {
            margin-bottom: 30px;
        }
        .integrantes-section {
            margin-bottom: 30px;
        }
        .integrante {
            margin-bottom: 15px;
            padding: 10px;
            border: 1px solid #333;
            page-break-inside: avoid;
        }
        .integrante-header {
            background-color: #f0f0f0;
            padding: 8px;
            border-bottom: 1px solid #333;
            margin: -10px -10px 10px -10px;
        }
        .integrante-nombre {
            font-weight: bold;
            font-size: 14px;
            margin: 0;
            color: #000;
            text-transform: uppercase;
        }
        .integrante-info {
            margin-bottom: 5px;
            display: flex;
            justify-content: flex-start;
        }
        .integrante-info strong {
            display: inline-block;
            min-width: 80px;
            font-size: 12px;
        }
        .integrante-info span {
            font-size: 12px;
        }
        .firma-section {
            margin-top: 40px;
            text-align: center;
        }
        .firma-cuadro {
            border: 1px solid #333;
            width: 250px;
            height: 80px;
            margin: 15px auto;
            position: relative;
        }
        .firma-label {
            position: absolute;
            bottom: 2px;
            left: 50%;
            transform: translateX(-50%);
            font-weight: bold;
            font-size: 11px;
        }
        .cuenta-info {
            background-color: #f0f0f0;
            padding: 15px;
            border: 2px solid #2F5496;
            margin-top: 30px;
            text-align: center;
        }
        .cuenta-info h3 {
            margin-top: 0;
            color: #2F5496;
            font-size: 16px;
        }
        .cuenta-detalle {
            font-size: 16px;
            font-weight: bold;
            margin: 10px 0;
        }
        .importante {
            color: #C00000;
            font-weight: bold;
            font-size: 14px;
            margin-top: 10px;
        }
        .declaracion {
            text-align: justify;
            margin: 30px 0;
            font-size: 13px;
            line-height: 1.5;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="title">CARTILLA DE IDENTIFICACION</div>
    </div>

    <div class="fecha-contrato">
        <strong>Fecha: </strong>{{ $fecha ? strtoupper(\Carbon\Carbon::parse($fecha)->locale('es')->translatedFormat('l')) . ', ' . \Carbon\Carbon::parse($fecha)->format('d') . ' DE ' . strtoupper(\Carbon\Carbon::parse($fecha)->locale('es')->translatedFormat('F')) . ' DEL ' . \Carbon\Carbon::parse($fecha)->format('Y') : 'FECHA' }}
    </div>

    <div class="contrato-info">
        <strong>El GRUPO:</strong> {{ $grupo->nombre_grupo ?? 'N/A' }}, Enterado del Contenido y alcances jurídicos de las Obligaciones que contraen con la Celebración de este Contrato; suscriben que tiene conocimiento y comprenden plenamente los términos y condiciones, habiendo sido aclaradas todas las consultas y/o Dudas. Por lo que firman el presente contrato sin limitaciones.
    </div>

    <div class="contrato-info">
        Así mismo declaro haber recibido el Contrato del Préstamo <strong>EMPRENDE CONMIGO</strong> Aprobado el cual se adjunta al <strong>CONTRATO PAGARE POR MUTUO DINERARIO.</strong> Así como haber sido instruido sobre lo estipulado en el mismo.
    </div>

    <div class="grupo-info">
        <h3 style="color: #2F5496;">GRUPO: {{ $grupo->nombre_grupo ?? 'N/A' }}</h3>
        <div class="integrante-info">
            <span><strong>Cantidad de Integrantes:</strong> {{ count($integrantes) }}</span>
        </div>
    </div>

    <div class="contrato-info">
        <strong>Las Integrantes DEL GRUPO</strong> manifiestan que ante Cualquier forma de impago o situaciones que atenten en el pago normal cada integrante responderá responsablemente. Sin ningún inconveniente allegando al límite consigo mismo.
    </div>

    <div class="contrato-info">
        <strong>Las Integrantes DEL GRUPO,</strong> manifiestan en forma de declaración jurada que los datos que su Contribución se llevan son verídicos.
    </div>

    <div class="integrantes-section">
        @foreach($integrantes as $integrante)
        <div class="integrante">
            <div class="integrante-header">
                <div class="integrante-nombre">
                    {{ $integrante['persona']->nombre ?? '' }} {{ $integrante['persona']->apellidos ?? '' }}
                </div>
            </div>
            <div class="integrante-info">
                <span><strong>DNI:</strong> {{ $integrante['persona']->DNI ?? '' }}</span>
            </div>
            <div class="integrante-info">
                <span><strong>DIREC:</strong> {{ $integrante['persona']->direccion ?? '' }}</span>
            </div>
            <div class="integrante-info">
                <span><strong>CELULAR:</strong> {{ $integrante['persona']->celular ?? '' }}</span>
            </div>
            
            <div class="firma-section">
                <div class="firma-cuadro">
                    <div class="firma-label">FIRMA DE CLIENTE</div>
                </div>
            </div>
        </div>
        @endforeach
    </div>

    <div class="declaracion">
        Las integrantes que suscriben el presente Documento declaran haber leído el Contrato de Préstamo del crédito <strong>EMPRENDE CONMIGO</strong> y Conocer las Condiciones específicas del mismo.
    </div>

    <div class="cuenta-info">
        <h3>INFORMACIÓN DE PAGOS</h3>
        <div class="cuenta-detalle">CUENTA BCP: 5357081563077</div>
        <div class="cuenta-detalle">CCI: 00253500708156307730</div>
        <div class="importante">
            IMPORTANTE: TODO PAGO DEBE SER REALIZADO A LA CTA. BANCARIA.<br>
            EL ASESOR NO ESTÁ AUTORIZADO A RECIBIR DINERO FÍSICO NI<br>
            VIRTUAL(YAPE/TRANSFERENCIA)
        </div>
        <div class="cuenta-detalle" style="margin-top: 15px;">NRO PARA CONSULTAS: 938651127</div>
    </div>

    <div class="firma-section" style="margin-top: 50px;">
        <div style="display: flex; justify-content: space-between; margin-top: 60px;">
            <div style="text-align: center; width: 45%;">
                <div style="border-top: 2px solid #333; padding-top: 10px; margin-top: 60px;">
                    <strong>Amar Melany Borace Rueda<br>
                    JEFE DE OPERACIONES<br>
                    EMPRENDE CONMIGO S.A.C.</strong>
                </div>
            </div>
            <div style="text-align: center; width: 45%;">
                <div style="border-top: 2px solid #333; padding-top: 10px; margin-top: 60px;">
                    <strong>FIRMA DE CLIENTE</strong>
                </div>
            </div>
        </div>
    </div>

</body>
</html>
