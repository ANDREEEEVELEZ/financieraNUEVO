<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Declaracion Jurada PEP</title>
    <style>
        @page {
            margin: 15mm;
            font-family: Arial, sans-serif;
        }
        
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            line-height: 1.3;
            margin: 0;
            padding: 0;
        }
        
        .container {
            width: 100%;
            max-width: 210mm;
        }
        
        .header {
            text-align: center;
            font-weight: bold;
            font-size: 12px;
            margin-bottom: 10px;
            border: 2px solid black;
            padding: 8px;
        }
        
        .subtitle {
            text-align: center;
            font-size: 11px;
            margin-bottom: 15px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid black;
            margin-bottom: 5px;
        }
        
        td {
            border: 1px solid black;
            padding: 4px 6px;
            vertical-align: top;
        }
        
        .numero {
            width: 25px;
            text-align: center;
            font-weight: bold;
            vertical-align: middle;
        }
        
        .contenido {
            padding-left: 8px;
        }
        
        .checkbox {
            display: inline-block;
            width: 12px;
            height: 12px;
            border: 1px solid black;
            text-align: center;
            line-height: 10px;
            margin: 0 2px;
            font-weight: bold;
        }
        
        .underline {
            border-bottom: 1px solid black;
            display: inline-block;
            min-width: 200px;
            padding-bottom: 1px;
        }
        
        .campo-datos {
            font-weight: bold;
            text-decoration: none;
            border-bottom: none;
        }
        
        .seccion-pep {
            background-color: #f9f9f9;
        }
        
        .texto-small {
            font-size: 9px;
        }
        
        .firma-section {
            margin-top: 20px;
            text-align: center;
        }
        
        .firma-linea {
            border-bottom: 1px solid black;
            width: 300px;
            margin: 0 auto 5px auto;
            height: 20px;
        }
        
        .beneficiario-header {
            background-color: #f0f0f0;
            text-align: center;
            font-weight: bold;
            padding: 8px;
        }
        
        .no-border {
            border: none;
        }
        
        .pep-subsection {
            background-color: #fffacd;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            FORMATO DE DECLARACION JURADA DE CONOCIMIENTO DEL CLIENTE BAJO EL REGIMEN GENERAL - PERSONA NATURAL
        </div>
        
        <div class="subtitle">
            (Informacion minima para ser llenada por el cliente del sujeto obligado)
        </div>

        <!-- Campo 1 - Nombres y Apellidos -->
        <table>
            <tr>
                <td class="numero">1</td>
                <td class="contenido">
                    <strong>Nombres:</strong> <span class="campo-datos underline">{{ strtoupper($clienteLimpio->nombres) }}</span>
                    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                    <strong>Apellidos:</strong> <span class="campo-datos underline">{{ strtoupper($clienteLimpio->apellidos) }}</span>
                </td>
            </tr>
        </table>

        <!-- Campo 2 - Documento de Identidad -->
        <table>
            <tr>
                <td class="numero">2</td>
                <td class="contenido">
                    <strong>Tipo y numero de documento de identidad (marque con una "X" segun corresponda):</strong><br>
                    <strong>DNI</strong> ( <span class="checkbox">{{ $dniMarcado }}</span> ) <strong>Pasaporte</strong> ( <span class="checkbox">{{ $pasaporteMarcado }}</span> ) <strong>Carne de Extranjeria</strong> ( <span class="checkbox">{{ $carneExtranjeraMarcado }}</span> ) <strong>Otro (Indique):</strong> ( <span class="checkbox">{{ $otroMarcado }}</span> )
                    &nbsp;&nbsp;&nbsp;&nbsp;<strong>N:</strong> <span class="campo-datos underline">{{ $clienteLimpio->DNI }}</span>
                </td>
            </tr>
        </table>

        <!-- Campo 3 - Nacionalidad -->
        <table>
            <tr>
                <td class="numero">3</td>
                <td class="contenido">
                    <strong>Nacionalidad (en el caso de extranjero):</strong> <span class="campo-datos underline">PERUANA</span>
                </td>
            </tr>
        </table>

        <!-- Campo 4 - Estado Civil -->
        <table>
            <tr>
                <td class="numero">4</td>
                <td class="contenido">
                    <strong>Estado civil (marque con una "X" segun corresponda) soltero/a</strong> ( <span class="checkbox">{{ $solteroMarcado }}</span> ) <strong>casado/a</strong> ( <span class="checkbox">{{ $casadoMarcado }}</span> ) <strong>viudo/a</strong> ( <span class="checkbox">{{ $viudoMarcado }}</span> ) <strong>divorciado/a</strong> ( <span class="checkbox">{{ $divorciadoMarcado }}</span> )
                </td>
            </tr>
        </table>

        <!-- Campo 5 - Conyuge -->
        <table>
            <tr>
                <td class="numero">5</td>
                <td class="contenido">
                    <strong>Nombres y apellidos del conyuge o conviviente:</strong> <span class="campo-datos underline">{{ strtoupper($datosLimpios['conyuge_nombre'] ?? 'NINGUNO') }}</span>
                </td>
            </tr>
        </table>

        <!-- Campo 6 - Domicilio -->
        <table>
            <tr>
                <td class="numero">6</td>
                <td class="contenido">
                    <strong>Domicilio (indicar tipo y nombre de la via): Jr. / Av. / Calle / Pasaje / Ovalo</strong><br>
                    <span class="campo-datos underline">{{ strtoupper($clienteLimpio->direccion) }}</span> <strong>Urb. Complejo Zona - Sector:</strong> <span class="underline">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span> <strong>Distrito:</strong><br>
                    <span class="campo-datos underline">{{ strtoupper($clienteLimpio->distrito ?? '') }}</span> <strong>Int:</strong> <span class="underline">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span> <strong>Dpto./Int. No:</strong> <span class="underline">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span><br>
                    <strong>Provincia:</strong> <span class="campo-datos underline">SULLANA</span> <strong>Departamento:</strong> <span class="campo-datos underline">PIURA</span>
                </td>
            </tr>
        </table>

        <!-- Campo 7 - Ocupacion -->
        <table>
            <tr>
                <td class="numero">7</td>
                <td class="contenido">
                    <strong>Ocupacion:</strong> <span class="campo-datos underline">{{ strtoupper($clienteLimpio->ocupacion ?? 'Sin especificar') }}</span>
                </td>
            </tr>
        </table>

        <!-- Campo 8 - Contacto -->
        <table>
            <tr>
                <td class="numero">8</td>
                <td class="contenido">
                    <strong>N Telefono Fijo (indicar codigo de cuidad):</strong> <span class="underline">{{ $datosLimpios['telefono_fijo'] ?? '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' }}</span> <strong>Celular:</strong> <span class="campo-datos underline">{{ $clienteLimpio->celular ?? $clienteLimpio->telefono }}</span> <strong>Correo electronico:</strong><br>
                    <span class="campo-datos underline">{{ strtolower($clienteLimpio->correo ?? '') }}</span>
                </td>
            </tr>
        </table>

        <!-- Campo 9 - Proposito -->
        <table>
            <tr>
                <td class="numero">9</td>
                <td class="contenido">
                    <strong>Proposito de la relacion comercial o de negocio (siempre que esta se desprenda directamente del objeto del contrato):</strong><br>
                    <span class="campo-datos underline">{{ strtoupper($datosLimpios['proposito_relacion'] ?? 'PRESTAMO') }}</span>
                </td>
            </tr>
        </table>

        <!-- Campo 10 - Seccion PEP -->
        <table class="seccion-pep">
            <tr>
                <td class="numero">10</td>
                <td class="contenido">
                    <strong>10.1. Indicar si es o ha sido PEP:</strong> <span class="texto-small">¿Ha cumplido, en los ultimos 5 anos, funciones publicas en un organismo publico o funciones prominentes en una organizacion internacional?</span> <span class="texto-small">(marque con una "X" segun corresponda):</span><br><br>
                    <strong>SI SOY</strong> <span class="checkbox">{{ $siSoyPep }}</span> &nbsp;&nbsp;&nbsp;
                    <strong>SI HE SIDO</strong> <span class="checkbox">{{ $siHeSidoPep }}</span> &nbsp;&nbsp;&nbsp;
                    <strong>NO SOY</strong> <span class="checkbox">{{ $noSoyPep }}</span> &nbsp;&nbsp;&nbsp;
                    <strong>NO HE SIDO</strong> <span class="checkbox">{{ $noHeSidoPep }}</span><br><br>
                    
                    <span class="texto-small">¿Ha sido colaborador directo de la maxima autoridad en dichas instituciones?</span><br>
                    <strong>SI SOY</strong> <span class="checkbox">{{ $siSoyColab }}</span> &nbsp;&nbsp;&nbsp;
                    <strong>SI HE SIDO</strong> <span class="checkbox">{{ $siHeSidoColab }}</span> &nbsp;&nbsp;&nbsp;
                    <strong>NO SOY</strong> <span class="checkbox">{{ $noSoyColab }}</span> &nbsp;&nbsp;&nbsp;
                    <strong>NO HE SIDO</strong> <span class="checkbox">{{ $noHeSidoColab }}</span>
                </td>
            </tr>
        </table>

        @if(in_array($datosLimpios['es_pep'], ['si_soy', 'si_he_sido']) || in_array($datosLimpios['colaborador_directo'], ['si_soy', 'si_he_sido']))
        <!-- Campo 10.2 - Solo si es PEP -->
        <table class="pep-subsection">
            <tr>
                <td class="numero">10.2</td>
                <td class="contenido">
                    <strong>De ser PEP, indicar los nombres y apellidos de sus:</strong><br>
                    <strong>(1) Parientes hasta el 2do grado de consanguinidad (Padre, Madre, Abuelos, Abuelas, Hermanos, Hermanas) y 2do de afinidad (suegros, yerno, nuera, cunados, nueras o cunadas de conyuge)</strong><br>
                    <strong>(2) Conyuge o conviviente</strong><br><br>
                    
                    <strong>¿Es pariente de PEP hasta el 2do grado?</strong><br>
                    <strong>SI SOY</strong> <span class="checkbox">{{ $siSoyPariente }}</span> &nbsp;&nbsp;&nbsp;
                    <strong>NO SOY</strong> <span class="checkbox">{{ $noSoyPariente }}</span><br><br>
                    
                    <strong>Datos de Parientes PEP:</strong><br>
                    @if(isset($datosLimpios['parientes_data']) && count($datosLimpios['parientes_data']) > 0)
                        @foreach($datosLimpios['parientes_data'] as $pariente)
                            <span class="underline">{{ strtoupper($pariente['nombre'] ?? 'NADA') }}</span> - <span class="underline">{{ strtoupper($pariente['parentesco'] ?? 'NADA') }}</span><br>
                        @endforeach
                    @else
                        <span class="underline">NADA</span> - <span class="underline">NADA</span>
                    @endif
                </td>
            </tr>
        </table>
        @endif

        <!-- Campo 11 - Beneficiario -->
        <table>
            <tr>
                <td class="numero" colspan="2" class="beneficiario-header">
                    IDENTIFICACION DEL BENEFICIARIO DE LA OPERACION
                </td>
            </tr>
        </table>
        
        <table>
            <tr>
                <td class="numero">11</td>
                <td class="contenido">
                    <strong>Realiza esta operacion a favor de (marque con una "X" segun corresponda):</strong><br>
                    <strong>1. De si mismo</strong> <span class="checkbox">{{ $porMiMismo }}</span> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                    <strong>2. De un tercero persona natural</strong> <span class="checkbox">{{ $deTercero }}</span><br><br>
                    <strong>3. Persona juridica</strong> <span class="checkbox">{{ $personaJuridica }}</span> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                    <strong>4. Ente juridico</strong> <span class="checkbox">{{ $enteJuridico }}</span><br><br>
                    
                    <span class="texto-small">Si marco la opcion 2, complete la informacion del numeral 11.2. Si marco la opcion 3, complete la informacion del numeral 11.3. Si marco la opcion 4, complete la informacion del numeral 11.4.</span>
                </td>
            </tr>
        </table>

        <!-- Campo 11.1 -->
        <table>
            <tr>
                <td class="numero">11.1</td>
                <td class="contenido">
                    <strong>Si realiza la operacion a favor de si mismo, complete la informacion siguiente:</strong><br>
                    <span style="color: green; font-weight: bold;">✓ Esta seccion se completa automaticamente con los datos del cliente actual</span>
                </td>
            </tr>
        </table>

        <!-- Campo 12 - Identificacion del beneficiario -->
        <table>
            <tr>
                <td class="numero">12</td>
                <td class="contenido">
                    <strong>Identificacion del Beneficiario Final del Beneficiario de la operacion, conforme al articulo 4 del Decreto Supremo No 1372 y sus modificatorias; segun corresponda (Nombres y Apellidos):</strong><br>
                    <span class="underline">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span>
                </td>
            </tr>
        </table>

        <!-- Campo 13 - Declaracion -->
        <table>
            <tr>
                <td class="numero">13</td>
                <td class="contenido">
                    <strong>Afirmo y ratifico todo lo manifestado en la presente declaracion jurada</strong>
                </td>
            </tr>
        </table>

        <!-- Observaciones -->
        <table>
            <tr>
                <td class="numero">13</td>
                <td class="contenido">
                    <strong>Observaciones adicionales:</strong><br>
                    {{ $datosLimpios['observaciones'] ?? 'Escriba observaciones adicionales si las hubiera...' }}
                </td>
            </tr>
        </table>

        <!-- Firma y Fecha -->
        <div class="firma-section">
            <table style="border: none;">
                <tr>
                    <td class="no-border" style="width: 50%; text-align: center;">
                        <strong>FECHA (dia/mes/ano):</strong> <span class="underline" style="min-width: 150px;">{{ $fechaActual }}</span>
                    </td>
                    <td class="no-border" style="width: 50%; text-align: center;">
                        <strong>FIRMA</strong>
                    </td>
                </tr>
                <tr>
                    <td class="no-border" style="text-align: center; padding-top: 30px;">
                        &nbsp;
                    </td>
                    <td class="no-border" style="text-align: center; padding-top: 20px;">
                        <div class="firma-linea"></div>
                        <strong>{{ strtoupper($clienteLimpio->nombres . ' ' . $clienteLimpio->apellidos) }}</strong><br>
                        <strong>DNI: {{ $clienteLimpio->DNI }}</strong>
                    </td>
                </tr>
            </table>
        </div>
    </div>
</body>
</html>
