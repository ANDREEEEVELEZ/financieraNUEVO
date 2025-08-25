<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Declaración Jurada de Conocimiento del Cliente</title>
    <style>
        @page {
            margin: 0.5cm;
            size: A4;
        }
        body {
            font-family: Arial, sans-serif;
            font-size: 9px;
            line-height: 1.1;
            margin: 0;
            padding: 0;
            color: #000;
        }
        .main-table {
            width: 100%;
            border-collapse: collapse;
            border: 2px solid #000;
            margin: 0;
        }
        .main-table td {
            border: 1px solid #000;
            padding: 2px 4px;
            vertical-align: top;
            font-size: 8px;
        }
        .header-row {
            background-color: #e6e6e6;
            font-weight: bold;
            text-align: center;
            font-size: 10px;
            padding: 4px;
        }
        .number-cell {
            width: 25px;
            text-align: center;
            font-weight: bold;
            background-color: #f8f8f8;
            font-size: 9px;
            border-right: 2px solid #000;
        }
        .content-cell {
            padding: 3px 4px;
            line-height: 1.4;
            font-size: 8px;
        }
        .underline {
            border-bottom: 1px solid #000;
            display: inline-block;
            min-width: 60px;
            height: 14px;
            vertical-align: bottom;
        }
        .filled {
            display: inline-block;
            min-width: 60px;
            height: 14px;
            vertical-align: bottom;
        }
        .bold {
            font-weight: bold;
            font-size: 8px;
        }
        .small-text {
            font-size: 7px;
            font-style: italic;
        }
        .center-text {
            text-align: center;
            font-weight: bold;
            display: block;
            margin: 2px 0;
            font-size: 9px;
        }
        .signature-section {
            margin-top: 15px;
            text-align: center;
        }
    </style>
</head>
<body>
    <table class="main-table">
        <!-- Header -->
        <tr>
            <td colspan="2" class="header-row">
                <div style="margin-bottom: 5px;">FORMATO DE DECLARACIÓN JURADA DE CONOCIMIENTO DEL CLIENTE BAJO EL RÉGIMEN GENERAL – PERSONA NATURAL</div>
                <div class="small-text">(Información mínima para ser llenada por el cliente del sujeto obligado)</div>
            </td>
        </tr>
        
        <!-- Row 1: Nombres y Apellidos -->
        <tr>
            <td class="number-cell">1</td>
            <td class="content-cell">
                <span class="bold">Nombres:</span>
                <span class="{{ $persona->nombre ? 'filled' : 'underline' }}">{{ strtoupper($persona->nombre ?? '') }}</span>
                <span style="margin-left: 50px;" class="bold">Apellidos:</span>
                <span class="{{ $persona->apellidos ? 'filled' : 'underline' }}">{{ strtoupper($persona->apellidos ?? '') }}</span>
            </td>
        </tr>
        
        <!-- Row 2: Documento -->
        <tr>
            <td class="number-cell">2</td>
            <td class="content-cell">
                <span class="bold">Tipo y número de documento de identidad:</span><br>
                <span class="bold">DNI</span> ( <span>X</span> ) <span class="bold">Pasaporte</span> ( ) <span class="bold">Carné de Extranjería</span> ( ) <span class="bold">Otro:</span> ( )
                <span style="margin-left: 50px;" class="bold">N°:</span>
                <span class="{{ $persona->DNI ? 'filled' : 'underline' }}">{{ $persona->DNI ?? '' }}</span>
            </td>
        </tr>

        <!-- Row 3: Nacionalidad -->
        <tr>
            <td class="number-cell">3</td>
            <td class="content-cell">
                <span class="bold">Nacionalidad:</span>
                <span class="filled">PERUANA</span>
            </td>
        </tr>

        <!-- Row 4: Estado civil -->
        <tr>
            <td class="number-cell">4</td>
            <td class="content-cell">
                <span class="bold">Estado civil:</span>
                soltero/a ( @if(strtolower($persona->estado_civil ?? '') == 'soltero') X @endif )
                casado/a ( @if(strtolower($persona->estado_civil ?? '') == 'casado') X @endif )
                viudo/a ( @if(strtolower($persona->estado_civil ?? '') == 'viudo') X @endif )
                divorciado/a ( @if(strtolower($persona->estado_civil ?? '') == 'divorciado') X @endif )
                conviviente ( @if(strtolower($persona->estado_civil ?? '') == 'conviviente') X @endif )
            </td>
        </tr>

        <!-- Row 5: Cónyuge -->
        <tr>
            <td class="number-cell">5</td>
            <td class="content-cell">
                <span class="bold">Cónyuge/Conviviente:</span>
                <span class="{{ $pep_data['conyuge_conviviente'] ?? '' ? 'filled' : 'underline' }}">{{ $pep_data['conyuge_conviviente'] ?? '' }}</span>
            </td>
        </tr>

        <!-- Row 6: Dirección -->
        <tr>
            <td class="number-cell">6</td>
            <td class="content-cell">
                <span class="bold">Domicilio:</span>
                <span class="{{ $persona->direccion ? 'filled' : 'underline' }}">{{ strtoupper($persona->direccion ?? '') }}</span>
                <span class="bold">Distrito:</span>
                <span class="{{ $persona->distrito ? 'filled' : 'underline' }}">{{ strtoupper($persona->distrito ?? '') }}</span>
                <span class="bold">Provincia:</span>
                <span class="filled">SULLANA</span>
                <span class="bold">Departamento:</span>
                <span class="filled">PIURA</span>
            </td>
        </tr>

        <!-- Row 7: Ocupación -->
        <tr>
            <td class="number-cell">7</td>
            <td class="content-cell">
                <span class="bold">Ocupación:</span>
                <span class="{{ $cliente->actividad ? 'filled' : 'underline' }}">{{ strtoupper($cliente->actividad ?? '') }}</span>
            </td>
        </tr>

        <!-- Row 8: Teléfono -->
        <tr>
            <td class="number-cell">8</td>
            <td class="content-cell">
                <span class="bold">Celular:</span>
                <span class="{{ $persona->celular ? 'filled' : 'underline' }}">{{ $persona->celular ?? '' }}</span>
                <span class="bold">Correo:</span>
                <span class="{{ $persona->correo ? 'filled' : 'underline' }}">{{ strtolower($persona->correo ?? '') }}</span>
            </td>
        </tr>

        <!-- Row 9: Propósito -->
        <tr>
            <td class="number-cell">9</td>
            <td class="content-cell">
                <span class="bold">Propósito de la relación:</span>
                <span class="{{ $pep_data['proposito_relacion'] ?? '' ? 'filled' : 'underline' }}">{{ $pep_data['proposito_relacion'] ?? 'Préstamo' }}</span>
            </td>
        </tr>

        <!-- Signature -->
        <tr>
            <td colspan="2" style="text-align:center;padding:20px;">
                <span class="bold">FECHA:</span>
                <span class="filled">{{ $fecha_generacion ?? now()->format('d/m/Y') }}</span>
                <span style="margin-left:100px;" class="bold">FIRMA</span><br><br>
                <div style="margin-top:30px;">
                    <div style="border-top:1px solid #000;width:200px;margin:0 auto;"></div>
                    <div style="margin-top:5px;">{{ strtoupper($persona->nombre ?? '') }} {{ strtoupper($persona->apellidos ?? '') }}</div>
                    <div>DNI: {{ $persona->DNI ?? '' }}</div>
                </div>
            </td>
        </tr>
    </table>
</body>
</html>
