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
            line-height: 1.15;
            font-size: 8px;
        }
        .field-container {
            position: relative;
            display: inline-block;
            margin: 0;
        }
        .field-container.auto-width {
            width: auto;
            min-width: 50px;
        }
        .field-container.half-width {
            width: 48%;
            min-width: 150px;
        }
        .field-container.full-width {
            width: 98%;
            min-width: 300px;
        }
        .field-value {
            font-weight: bold;
            display: inline-block;
            padding: 0 2px;
        }
        .underline {
            border-bottom: 1px solid #000;
            display: inline-block;
            height: 12px;
            width: 100%;
            position: relative;
            top: 2px;
        }
        .checkbox {
            display: inline-block;
            width: 10px;
            height: 10px;
            border: 1px solid #000;
            text-align: center;
            vertical-align: middle;
            line-height: 9px;
            font-size: 10px;
            margin: 0 2px;
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
        .sub-table {
            width: 100%;
            border-collapse: collapse;
        }
        .sub-table td {
            border: none;
            padding: 1px;
            vertical-align: middle;
        }
        .sub-table-bordered td {
            border: 1px solid #000;
            padding: 3px;
            font-size: 7px;
            text-align: center;
        }
        .sub-table-bordered th {
            font-weight: bold;
            background-color: #f5f5f5;
        }
        .signature-line {
            border-top: 1px solid #000;
            width: 200px;
            margin: 0 auto;
            position: relative;
        }
        .signature-text {
            position: absolute;
            bottom: -15px;
            left: 50%;
            transform: translateX(-50%);
            white-space: nowrap;
        }
    </style>
</head>
<body>
    <table class="main-table">
        <tr>
            <td colspan="2" class="header-row">
                <div style="margin-bottom: 5px;">FORMATO DE DECLARACIÓN JURADA DE CONOCIMIENTO DEL CLIENTE BAJO EL RÉGIMEN GENERAL – PERSONA NATURAL</div>
                <div class="small-text">(Información mínima para ser llenada por el cliente del sujeto obligado)</div>
            </td>
        </tr>
        
        <tr>
            <td class="number-cell">1</td>
            <td class="content-cell">
                <table class="sub-table">
                    <tr>
                        <td style="width: 50%;">
                            <span class="bold">Nombres:</span>
                            <span class="field-container full-width">
                                <span class="field-value" style="width:100%;">{{ strtoupper($persona->nombre) }}</span>
                                <span class="underline" style="width:100%;"></span>
                            </span>
                        </td>
                        <td style="width: 50%;">
                            <span class="bold">Apellidos:</span>
                            <span class="field-container full-width">
                                <span class="field-value" style="width:100%;">{{ strtoupper($persona->apellidos) }}</span>
                                <span class="underline" style="width:100%;"></span>
                            </span>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        
        <tr>
            <td class="number-cell">2</td>
            <td class="content-cell">
                <table class="sub-table">
                    <tr>
                        <td colspan="2" style="padding-bottom: 5px;"><span class="bold">Tipo y número de documento de identidad (marque con una "X" según corresponda):</span></td>
                    </tr>
                    <tr>
                        <td>
                            <span class="bold">DNI</span> (<span class="checkbox">{{ ($persona->tipo_documento ?? '') == 'DNI' ? 'X' : '' }}</span>)
                            <span class="bold">Pasaporte</span> (<span class="checkbox">{{ ($persona->tipo_documento ?? '') == 'Pasaporte' ? 'X' : '' }}</span>)
                            <span class="bold">Carné de Extranjería</span> (<span class="checkbox">{{ ($persona->tipo_documento ?? '') == 'Carné de Extranjería' ? 'X' : '' }}</span>)
                            <span class="bold">Otro (Indique):</span> (<span class="checkbox">{{ ($persona->tipo_documento ?? '') == 'Otro' ? 'X' : '' }}</span>)
                        </td>
                        <td style="text-align: right;">
                            <span class="bold">N°:</span>
                            <span class="field-container" style="min-width: 100px;">
                                <span class="field-value" style="width:100%;">{{ $persona->DNI }}</span>
                                <span class="underline" style="width:100%;"></span>
                            </span>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        
        <tr>
            <td class="number-cell">3</td>
            <td class="content-cell">
                <span class="bold">Nacionalidad (en el caso de extranjero):</span>
                <span class="field-container auto-width" style="min-width: 150px;">
                    <span class="field-value" style="width:100%;">PERUANA</span>
                    <span class="underline" style="width:100%;"></span>
                </span>
            </td>
        </tr>
        
        <tr>
            <td class="number-cell">4</td>
            <td class="content-cell">
                <span class="bold">Estado civil (marque con una "X" según corresponda) soltero/a</span> (<span class="checkbox">{{ strtolower($persona->estado_civil ?? '') == 'soltero' ? 'X' : '' }}</span>)
                <span class="bold">casado/a</span> (<span class="checkbox">{{ strtolower($persona->estado_civil ?? '') == 'casado' ? 'X' : '' }}</span>)
                <span class="bold">viudo/a</span> (<span class="checkbox">{{ strtolower($persona->estado_civil ?? '') == 'viudo' ? 'X' : '' }}</span>)
                <span class="bold">divorciado/a</span> (<span class="checkbox">{{ strtolower($persona->estado_civil ?? '') == 'divorciado' ? 'X' : '' }}</span>)
                <span class="bold">conviviente</span> (<span class="checkbox">{{ strtolower($persona->estado_civil ?? '') == 'conviviente' ? 'X' : '' }}</span>)
            </td>
        </tr>
        
        <tr>
            <td class="number-cell">5</td>
            <td class="content-cell">
                <span class="bold">Nombres y apellidos del cónyuge o conviviente:</span>
                <span class="field-container full-width">
                    <span class="field-value" style="width:100%;">{{ $pep_data['conyuge_conviviente'] ?? '' }}</span>
                    <span class="underline" style="width:100%;"></span>
                </span>
            </td>
        </tr>
        
        <tr>
            <td class="number-cell">6</td>
            <td class="content-cell">
                <span class="bold">Domicilio (indicar tipo y nombre de la vía): Jr. / Av. / Calle / Pasaje / Óvalo:</span>
                <span class="field-container auto-width" style="min-width: 150px;">
                    <span class="field-value" style="width:100%;">{{ strtoupper($persona->direccion) }}</span>
                    <span class="underline" style="width:100%;"></span>
                </span>
                <span class="bold" style="margin-left: 10px;">N°</span>
                <span class="field-container auto-width" style="min-width: 40px;">
                    <span class="field-value" style="width:100%;">{{ $persona->numero_domicilio ?? '' }}</span>
                    <span class="underline" style="width:100%;"></span>
                </span>
                <span class="bold" style="margin-left: 10px;">Dpto.-Int. N°:</span>
                <span class="field-container auto-width" style="min-width: 40px;">
                    <span class="field-value" style="width:100%;">{{ $persona->dpto_int ?? '' }}</span>
                    <span class="underline" style="width:100%;"></span>
                </span>
                <br>
                <span class="bold">Urb. - Complejo - Zona - Sector:</span>
                <span class="field-container auto-width" style="min-width: 150px;">
                    <span class="field-value" style="width:100%;">{{ $persona->urb_complejo ?? '' }}</span>
                    <span class="underline" style="width:100%;"></span>
                </span>
                <span class="bold" style="margin-left: 10px;">Distrito:</span>
                <span class="field-container auto-width" style="min-width: 80px;">
                    <span class="field-value" style="width:100%;">{{ strtoupper($persona->distrito) }}</span>
                    <span class="underline" style="width:100%;"></span>
                </span>
                <span class="bold" style="margin-left: 10px;">Provincia:</span>
                <span class="field-container auto-width" style="min-width: 80px;">
                    <span class="field-value" style="width:100%;">SULLANA</span>
                    <span class="underline" style="width:100%;"></span>
                </span>
                <span class="bold" style="margin-left: 10px;">Departamento:</span>
                <span class="field-container auto-width" style="min-width: 80px;">
                    <span class="field-value" style="width:100%;">PIURA</span>
                    <span class="underline" style="width:100%;"></span>
                </span>
            </td>
        </tr>
        
        <tr>
            <td class="number-cell">7</td>
            <td class="content-cell">
                <span class="bold">Ocupación:</span>
                <span class="field-container auto-width" style="min-width: 150px;">
                    <span class="field-value" style="width:100%;">{{ strtoupper($cliente->actividad) }}</span>
                    <span class="underline" style="width:100%;"></span>
                </span>
            </td>
        </tr>
        
        <tr>
            <td class="number-cell">8</td>
            <td class="content-cell">
                <span class="bold">N° Teléfono Fijo (Indicar código de ciudad):</span>
                <span class="field-container auto-width" style="min-width: 80px;">
                    <span class="field-value" style="width:100%;">{{ $pep_data['telefono_fijo'] ?? '' }}</span>
                    <span class="underline" style="width:100%;"></span>
                </span>
                <span class="bold" style="margin-left: 10px;">Celular:</span>
                <span class="field-container auto-width" style="min-width: 80px;">
                    <span class="field-value" style="width:100%;">{{ $persona->celular }}</span>
                    <span class="underline" style="width:100%;"></span>
                </span>
                <span class="bold" style="margin-left: 10px;">Correo electrónico:</span>
                <span class="field-container auto-width" style="min-width: 150px;">
                    <span class="field-value" style="width:100%;">{{ strtolower($persona->correo) }}</span>
                    <span class="underline" style="width:100%;"></span>
                </span>
            </td>
        </tr>
        
        <tr>
            <td class="number-cell">9</td>
            <td class="content-cell">
                <span class="bold">Propósito de la relación con el sujeto obligado (siempre que esta no se desprenda directamente del objeto del contrato):</span>
                <span class="field-container full-width">
                    <span class="field-value" style="width:100%;">{{ $pep_data['proposito_relacion'] ?? 'Préstamo' }}</span>
                    <span class="underline" style="width:100%;"></span>
                </span>
            </td>
        </tr>
        
        <tr>
            <td class="number-cell">10</td>
            <td class="content-cell">
                <table class="sub-table" style="margin-bottom: 5px;">
                    <tr>
                        <td colspan="2"><span class="bold">10.1. Indicar si es o ha sido PEP: ¿Ha cumplido, en los últimos 5 años: i) funciones públicas en un organismo público o ii) funciones prominentes en una organización internacional? (marque con una "X" según corresponda):</span></td>
                    </tr>
                    <tr>
                        <td>
                            <span class="bold">SI SOY</span> (<span class="checkbox">{{ ($pep_data['es_pep'] ?? '') == 'si_soy' ? 'X' : '' }}</span>)
                            <span class="bold">SI HE SIDO</span> (<span class="checkbox">{{ ($pep_data['es_pep'] ?? '') == 'si_he_sido' ? 'X' : '' }}</span>)
                            <span class="bold">NO SOY</span> (<span class="checkbox">{{ ($pep_data['es_pep'] ?? 'no_soy') == 'no_soy' ? 'X' : '' }}</span>)
                            <span class="bold">NO HE SIDO</span> (<span class="checkbox">{{ ($pep_data['es_pep'] ?? 'no_he_sido') == 'no_he_sido' ? 'X' : '' }}</span>)
                        </td>
                        <td>
                            <span class="bold">¿Ha sido colaborador directo de la máxima autoridad en dichas instituciones?</span>
                            <span class="bold">SI SOY</span> (<span class="checkbox">{{ ($pep_data['es_colaborador_pep'] ?? '') == 'si_soy' ? 'X' : '' }}</span>)
                            <span class="bold">SI HE SIDO</span> (<span class="checkbox">{{ ($pep_data['es_colaborador_pep'] ?? '') == 'si_he_sido' ? 'X' : '' }}</span>)
                            <span class="bold">NO SOY</span> (<span class="checkbox">{{ ($pep_data['es_colaborador_pep'] ?? 'no_soy') == 'no_soy' ? 'X' : '' }}</span>)
                            <span class="bold">NO HE SIDO</span> (<span class="checkbox">{{ ($pep_data['es_colaborador_pep'] ?? 'no_he_sido') == 'no_he_sido' ? 'X' : '' }}</span>)
                        </td>
                    </tr>
                </table>
                <span class="bold">Si marcó "Si soy" o "Si he sido", complete la información siguiente:</span><br>
                <table class="sub-table" style="margin-top: 5px;">
                    <tr>
                        <td style="width: 50%;">
                            <span class="bold">Cargo:</span>
                            <span class="field-container full-width">
                                <span class="field-value" style="width:100%;">{{ $pep_data['cargo_pep'] ?? '' }}</span>
                                <span class="underline" style="width:100%;"></span>
                            </span>
                        </td>
                        <td style="width: 50%;">
                            <span class="bold">Nombre de la institución (organismo público u organización internacional):</span>
                            <span class="field-container full-width">
                                <span class="field-value" style="width:100%;">{{ $pep_data['institucion_pep'] ?? '' }}</span>
                                <span class="underline" style="width:100%;"></span>
                            </span>
                        </td>
                    </tr>
                </table>
                <br>
                <span class="bold">10.2. De ser PEP, indicar los nombres y apellidos de sus:</span><br>
                <span class="bold">(1) Parientes hasta el 2do grado de consanguinidad</span> <span class="small-text">(Padre, Madre, hijo/as, Abuelo/as, nieto/as, hermano/as)</span> <span class="bold">y 2do de afinidad</span> <span class="small-text">(suegro/a, yerno, nuera, cuñado/as, abuelo/as del cónyuge, nieto/as del cónyuge):</span><br>
                <span class="bold">(2) Cónyuge o conviviente:</span><br>
                <br>
                <span class="bold">10.3. Indicar si es pariente de PEP hasta el 2do. grado de consanguinidad</span> <span class="small-text">(Padre, Madre, hijo/as, Abuelo/as, nieto/as, hermano/as)</span> <span class="bold">2do.de afinidad</span> <span class="small-text">(suegro/a, yerno, nuera, cuñado/as, abuelo/as del cónyuge, nieto/as del cónyuge);</span> <span class="bold">y cónyuge o conviviente (marque con una "X" según corresponda):</span>
                <span class="bold">SI SOY</span> (<span class="checkbox">{{ ($pep_data['es_pariente_pep'] ?? '') == 'si_soy' ? 'X' : '' }}</span>)
                <span class="bold">NO SOY</span> (<span class="checkbox">{{ ($pep_data['es_pariente_pep'] ?? 'no_soy') == 'no_soy' ? 'X' : '' }}</span>)<br>
                <span class="bold">Si marcó "Si SOY" especifique los datos siguientes:</span><br><br>
                <table class="sub-table sub-table-bordered">
                    <thead>
                        <tr>
                            <td style="width: 70%;">Nombres y Apellidos del PEP</td>
                            <td>Indicar Parentesco</td>
                        </tr>
                    </thead>
                    <tbody>
                        @if(($pep_data['es_pariente_pep'] ?? '') == 'si_soy' && !empty($pep_data['parientes_pep']))
                            @foreach($pep_data['parientes_pep'] as $pariente)
                            <tr>
                                <td>{{ $pariente['nombre_pariente'] ?? '' }}</td>
                                <td>{{ $pariente['parentesco'] ?? '' }}</td>
                            </tr>
                            @endforeach
                            @for($i = count($pep_data['parientes_pep']); $i < 3; $i++)
                            <tr>
                                <td></td>
                                <td></td>
                            </tr>
                            @endfor
                        @else
                            <tr>
                                <td style="height: 20px;"></td>
                                <td></td>
                            </tr>
                            <tr>
                                <td style="height: 20px;"></td>
                                <td></td>
                            </tr>
                            <tr>
                                <td>......</td>
                                <td></td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </td>
        </tr>
        
        <tr>
            <td class="number-cell">11</td>
            <td class="content-cell">
                <span class="center-text">IDENTIDAD DEL BENEFICIARIO DE LA OPERACIÓN</span>
                <br>
                <span class="bold">Realizo esta operación a favor de (marque con una "X" según corresponda):</span><br>
                <span class="bold">1. De mí mismo</span> (<span class="checkbox">{{ ($pep_data['operacion_favor'] ?? 'mi_mismo') == 'mi_mismo' ? 'X' : '' }}</span>)
                <span class="bold">2. De un tercero persona natural</span> (<span class="checkbox">{{ ($pep_data['operacion_favor'] ?? '') == 'tercero_natural' ? 'X' : '' }}</span>)
                <span class="bold">3. Persona jurídica</span> (<span class="checkbox">{{ ($pep_data['operacion_favor'] ?? '') == 'persona_juridica' ? 'X' : '' }}</span>)
                <span class="bold">4. Ente jurídico</span> (<span class="checkbox">{{ ($pep_data['operacion_favor'] ?? '') == 'ente_juridico' ? 'X' : '' }}</span>)<br>
                <span class="bold">Si marcó la opción 1, complete la información del numeral 11.1. Si marcó la opción 2, complete la información del numeral 11.2. Si marcó la opción 3, complete la información del numeral 11.3. Si marco la opción 4, complete la información del numeral 11.3 en lo que resulte aplicable.</span><br>
                <br>
                <span class="bold">11.1. Si realiza la operación a favor de sí mismo, complete la información siguiente:</span><br>
                <span class="bold">i) Origen de los fondos/activos involucrados en la operación, cuando esta se realice en efectivo e iguale o supere el umbral para efectos del RO:</span><br>
                <span class="field-container full-width">
                    <span class="field-value" style="width:100%;">{{ ($pep_data['operacion_favor'] ?? 'mi_mismo') == 'mi_mismo' ? ($pep_data['observaciones'] ?? 'NO APLICA') : '' }}</span>
                    <span class="underline" style="width:100%;"></span>
                </span>
            </td>
        </tr>
        
        <tr>
            <td class="number-cell">12</td>
            <td class="content-cell">
                <span class="bold">11.2. Si realiza la operación a favor de un tercero persona natural, complete la información siguiente:</span><br>
                <span class="bold">i) Nombres y apellido del tercero persona natural:</span>
                <span class="field-container full-width">
                    <span class="field-value" style="width:100%;">{{ ($pep_data['operacion_favor'] ?? '') == 'tercero_natural' ? ($pep_data['tercero_nombres'] ?? '') : '' }}</span>
                    <span class="underline" style="width:100%;"></span>
                </span><br>
                <span class="bold">ii) Tipo y número de documento de identidad:</span>
                <span class="field-container half-width">
                    <span class="field-value" style="width:100%;">{{ ($pep_data['operacion_favor'] ?? '') == 'tercero_natural' ? ($pep_data['tercero_documento'] ?? '') : '' }}</span>
                    <span class="underline" style="width:100%;"></span>
                </span><br>
                <span class="bold">iii) Datos de la representación (Marque con una "X" según corresponda): Poder por Escritura Pública</span> (<span class="checkbox"></span>)
                <span class="bold">Mandato</span> (<span class="checkbox"></span>)<br>
                <span class="bold">iv) Indicar si es o ha sido PEP: ¿Ha cumplido, en los últimos 5 años: i) funciones públicas en un organismo público o ii) funciones prominentes en una organización internacional? (marque con una "X" según corresponda):</span>
                <span class="bold">SI SOY</span> (<span class="checkbox"></span>)
                <span class="bold">SI HA SIDO</span> (<span class="checkbox"></span>)
                <span class="bold">NO ES</span> (<span class="checkbox"></span>)
                <span class="bold">NO HA SIDO</span> (<span class="checkbox"></span>)<br>
                <span class="bold">Si marcó "Si es" o "Si ha sido", complete la información siguiente:</span><br>
                <span class="bold">- Cargo:</span>
                <span class="field-container half-width">
                    <span class="field-value" style="width:100%;"></span>
                    <span class="underline" style="width:100%;"></span>
                </span>
                <span class="bold" style="margin-left: 10px;">- Nombre de la institución (organismo público u organización internacional):</span>
                <span class="field-container half-width">
                    <span class="field-value" style="width:100%;"></span>
                    <span class="underline" style="width:100%;"></span>
                </span>
                <br>
                <span class="bold">v) Origen de los fondos/activos involucrados en la operación, cuando esta se realice en efectivo o iguale o supere el umbral para efectos del RO:</span>
            </td>
        </tr>
        
        <tr>
            <td class="number-cell">13</td>
            <td class="content-cell">
                <span class="bold">11.3. Si realiza la operación a favor de un tercero persona jurídica o ente jurídico, en lo que resulte aplicable a este último, complete la información siguiente:</span><br>
                <span class="bold">i) Denominación o Razón Social:</span>
                <span class="field-container full-width">
                    <span class="field-value" style="width:100%;">{{ in_array($pep_data['operacion_favor'] ?? '', ['persona_juridica', 'ente_juridico']) ? ($pep_data['razon_social'] ?? '') : '' }}</span>
                    <span class="underline" style="width:100%;"></span>
                </span><br>
                <span class="bold">ii) Número de RUC, de ser el caso:</span>
                <span class="field-container half-width">
                    <span class="field-value" style="width:100%;">{{ in_array($pep_data['operacion_favor'] ?? '', ['persona_juridica', 'ente_juridico']) ? ($pep_data['ruc'] ?? '') : '' }}</span>
                    <span class="underline" style="width:100%;"></span>
                </span><br>
                <span class="bold">iii) Datos de la representación (Marque con una "X" según corresponda): Poder por acta</span> (<span class="checkbox"></span>)
                <span class="bold">Poder por Escritura Pública</span> (<span class="checkbox"></span>)
                <span class="bold">Mandato</span> (<span class="checkbox"></span>)<br>
                <span class="bold">iv) Origen de los fondos/activos involucrados en la operación, cuando esta se realice en efectivo o iguale o supere el umbral para efectos del RO:</span><br>
                <span class="field-container full-width">
                    <span class="field-value" style="width:100%;"></span>
                    <span class="underline" style="width:100%;"></span>
                </span><br>
                <span class="bold">v) Identificación del Beneficiario Final del Beneficiario de la operación, conforme al artículo 4 del Decreto Legislativo N° 1372 y sus modificatorias, según corresponda (Nombres y Apellidos):</span><br>
                <span class="field-container full-width">
                    <span class="field-value" style="width:100%;"></span>
                    <span class="underline" style="width:100%;"></span>
                </span><br>
                <span class="bold">Afirmo y ratifico todo lo manifestado en la presente declaración jurada</span>
            </td>
        </tr>
        
        <tr>
            <td colspan="2" style="text-align: center; padding: 20px;">
                <table class="sub-table" style="width: 100%;">
                    <tr>
                        <td style="text-align: left;"><span class="bold">FECHA (día/mes/año):</span> <span class="field-container auto-width" style="min-width: 100px;"><span class="field-value" style="width:100%;">{{ $fecha_generacion ?? now()->format('d/m/Y') }}</span><span class="underline" style="width:100%;"></span></span></td>
                        <td style="text-align: right;"><span class="bold">FIRMA</span></td>
                    </tr>
                </table>
                <div style="margin-top: 30px; position: relative;">
                    <div class="signature-line"></div>
                    <div style="margin-top: 5px;">{{ strtoupper($persona->nombre ?? '') }} {{ strtoupper($persona->apellidos ?? '') }}</div>
                    <div>DNI: {{ $persona->DNI ?? '' }}</div>
                </div>
            </td>
        </tr>
        
        <tr>
            <td colspan="2" class="small-text" style="text-align: center; padding: 5px;">
                Nota: Para ser conservada por el sujeto obligado y, en su caso, exhibida a solicitud de la UIF-Perú en actividades de supervisión. No se envía a la UIF-Perú, salvo solicitud expresa.
            </td>
        </tr>
    </table>
</body>
</html>