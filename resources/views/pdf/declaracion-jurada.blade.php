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
        .underline {
            border-bottom: 1px solid #000;
            display: inline-block;
            min-width: 60px;
            padding: 0 2px 1px 2px;
            margin: 0 1px;
        }
        .checkbox {
            display: inline-block;
            width: 12px;
            height: 12px;
            border: 1px solid #000;
            text-align: center;
            vertical-align: middle;
            margin: 0 2px;
            font-size: 10px;
            line-height: 10px;
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
        .sub-table {
            width: 100%;
            border-collapse: collapse;
            margin: 2px 0;
        }
        .sub-table td, .sub-table th {
            border: 1px solid #000;
            padding: 2px;
            font-size: 7px;
            text-align: center;
        }
        .sub-table th {
            font-weight: bold;
            background-color: #f5f5f5;
        }
        .spacer {
            margin: 2px 0;
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
                <span class="bold">Nombres:</span> <span class="underline">{{ strtoupper($persona->nombre) }}</span>
                <span style="margin-left: 50px;" class="bold">Apellidos:</span> <span class="underline">{{ strtoupper($persona->apellidos) }}</span>
            </td>
        </tr>
        
        <!-- Row 2: Tipo y número de documento -->
        <tr>
            <td class="number-cell">2</td>
            <td class="content-cell">
                <span class="bold">Tipo y número de documento de identidad (marque con una "X" según corresponda):</span><br>
                <span class="bold">DNI</span> ( <span style="font-size: 14px;">X</span> ) <span class="bold">Pasaporte</span> ( ) <span class="bold">Carné de Extranjería</span> ( ) <span class="bold">Otro (Indique):</span> ( )
                <span style="margin-left: 50px;" class="bold">N°:</span> <span class="underline">{{ $persona->DNI }}</span>
            </td>
        </tr>
        
        <!-- Row 3: Nacionalidad -->
        <tr>
            <td class="number-cell">3</td>
            <td class="content-cell">
                <span class="bold">Nacionalidad (en el caso de extranjero):</span> <span class="underline">PERUANA</span>
            </td>
        </tr>
        
        <!-- Row 4: Estado civil -->
        <tr>
            <td class="number-cell">4</td>
            <td class="content-cell">
                <span class="bold">Estado civil (marque con una "X" según corresponda) soltero/a</span> 
                ( @if(strtolower($persona->estado_civil ?? '') == 'soltero') X @endif ) 
                <span class="bold">casado/a</span> ( @if(strtolower($persona->estado_civil ?? '') == 'casado') X @endif ) 
                <span class="bold">viudo/a</span> ( @if(strtolower($persona->estado_civil ?? '') == 'viudo') X @endif ) 
                <span class="bold">divorciado/a</span> ( @if(strtolower($persona->estado_civil ?? '') == 'divorciado') X @endif )
                <span class="bold">conviviente</span> ( @if(strtolower($persona->estado_civil ?? '') == 'conviviente') X @endif )
            </td>
        </tr>
        
        <!-- Row 5: Cónyuge -->
        <tr>
            <td class="number-cell">5</td>
            <td class="content-cell">
                <span class="bold">Nombres y apellidos del cónyuge o conviviente:</span> <span class="underline" style="width: 300px;">{{ $pep_data['conyuge_conviviente'] ?? '' }}</span>
            </td>
        </tr>
        
        <!-- Row 6: Domicilio -->
        <tr>
            <td class="number-cell">6</td>
            <td class="content-cell">
                <span class="bold">Domicilio (indicar tipo y nombre de la vía): Jr. / Av. / Calle / Pasaje / Ovalo</span><br>
                <span class="underline">{{ strtoupper($persona->direccion) }}</span>
                <span class="bold">Urb. Complejo Zona - Sector:</span> <span class="underline" style="width: 100px;"></span>
                <span class="bold">Distrito:</span> <span class="underline">{{ strtoupper($persona->distrito) }}</span>
                <span class="bold">Int:</span> <span class="underline" style="width: 50px;"></span>
                <span class="bold">Dpto./Int. N°:</span> <span class="underline" style="width: 50px;"></span><br>
                <span class="bold">Provincia:</span> <span class="underline">SULLANA</span>
                <span class="bold">Departamento:</span> <span class="underline">PIURA</span>
            </td>
        </tr>
        
        <!-- Row 7: Ocupación -->
        <tr>
            <td class="number-cell">7</td>
            <td class="content-cell">
                <span class="bold">Ocupación:</span> <span class="underline">{{ strtoupper($cliente->actividad) }}</span>
            </td>
        </tr>
        
        <!-- Row 8: Teléfonos -->
        <tr>
            <td class="number-cell">8</td>
            <td class="content-cell">
                <span class="bold">N° Teléfono Fijo (indicar código de cuidad):</span> <span class="underline" style="width: 120px;">{{ $pep_data['telefono_fijo'] ?? '' }}</span>
                <span class="bold">Celular:</span> <span class="underline">{{ $persona->celular }}</span>
                <span class="bold">Correo electrónico:</span> <span class="underline">{{ strtolower($persona->correo) }}</span>
            </td>
        </tr>
        
        <!-- Row 9: Propósito -->
        <tr>
            <td class="number-cell">9</td>
            <td class="content-cell">
                <span class="bold">Propósito de la relación comercial o de negocio (siempre que esta se desprenda directamente del objeto del contrato):</span><br>
                <span class="underline" style="width: 100%; min-height: 20px; display: block;">{{ $pep_data['proposito_relacion'] ?? 'Préstamo' }}</span>
            </td>
        </tr>
        
        <!-- Row 10: PEP Section -->
        <tr>
            <td class="number-cell">10</td>
            <td class="content-cell">
                <span class="bold">10.1. Indicar si es o ha sido PEP: ¿Ha cumplido, en los últimos 5 años, funciones públicas en un organismo público o funciones prominentes en una organización internacional? (marque con una "X" según corresponda): 
                SI SOY ( @if(($pep_data['es_pep'] ?? '') == 'si_soy') X @endif ) 
                SI HE SIDO ( @if(($pep_data['es_pep'] ?? '') == 'si_he_sido') X @endif ) 
                NO SOY ( @if(($pep_data['es_pep'] ?? 'no_soy') == 'no_soy') X @endif ) 
                NO HE SIDO ( @if(($pep_data['es_pep'] ?? 'no_he_sido') == 'no_he_sido') X @endif )</span><br>
                <span class="bold">¿Ha sido colaborador directo de la máxima autoridad en dichas instituciones? 
                SI SOY ( @if(($pep_data['es_colaborador_pep'] ?? '') == 'si_soy') X @endif ) 
                SI HE SIDO ( @if(($pep_data['es_colaborador_pep'] ?? '') == 'si_he_sido') X @endif ) 
                NO SOY ( @if(($pep_data['es_colaborador_pep'] ?? '') == 'no_soy') X @endif ) 
                NO HE SIDO ( @if(($pep_data['es_colaborador_pep'] ?? 'no_he_sido') == 'no_he_sido') X @endif )</span><br>
                <span class="bold">Si marcó "Sí soy" o "Si he sido" complete la información siguiente:</span><br><br>
                
                <span class="bold">Cargo:</span> <span class="underline" style="width: 200px;">{{ $pep_data['cargo_pep'] ?? '' }}</span>
                <span class="bold">Nombre de la institución (organismo público u organización internacional):</span> <span class="underline" style="width: 200px;">{{ $pep_data['institucion_pep'] ?? '' }}</span><br><br>
                
                <span class="bold">10.2. De ser PEP, indicar los nombres y apellidos de sus:</span><br>
                <span class="bold">(1) Parientes hasta el 2do grado de consanguinidad</span> <span class="small-text">(Padre, Madre, Abuelos, Abuelas, Hermanos, Hermanas)</span> <span class="bold">y 2do de afinidad</span> <span class="small-text">(suegros, yerno, nuera, cuñados, nueras o cuñadas de cónyuge)</span><br>
                <span class="bold">(2) Cónyuge o conviviente:</span><br><br>
                
                <span class="bold">10.3. Indicar si es pariente de PEP hasta el 2do. grado de consanguinidad</span> <span class="small-text">(Padre, Madre, Abuelos, Abuelas, apellidos, hermanos, hermanas)</span> <span class="bold">2do de afinidad</span> <span class="small-text">(suegros, yerno, nuera, cuñados, nueras o cuñadas de cónyuge)</span> <span class="bold">y cónyuge o conviviente (marque con una "X" según corresponda): 
                SI SOY ( @if(($pep_data['es_pariente_pep'] ?? '') == 'si_soy') X @endif ) 
                NO SOY ( @if(($pep_data['es_pariente_pep'] ?? 'no_soy') == 'no_soy') X @endif )</span><br>
                <span class="bold">Si marcó "Si SOY" especifique los datos siguientes:</span><br><br>
                
                <table style="width: 100%; border-collapse: collapse; margin-top: 5px;">
                    <tr>
                        <td style="border: 1px solid #000; padding: 3px; text-align: center; font-weight: bold;">Nombres y Apellidos del PEP</td>
                        <td style="border: 1px solid #000; padding: 3px; text-align: center; font-weight: bold;">Indicar Parentesco</td>
                    </tr>
                    @if(($pep_data['es_pariente_pep'] ?? '') == 'si_soy' && !empty($pep_data['parientes_pep']))
                        @foreach($pep_data['parientes_pep'] as $pariente)
                        <tr>
                            <td style="border: 1px solid #000; padding: 3px;">{{ $pariente['nombre_pariente'] ?? '' }}</td>
                            <td style="border: 1px solid #000; padding: 3px;">{{ $pariente['parentesco'] ?? '' }}</td>
                        </tr>
                        @endforeach
                        @for($i = count($pep_data['parientes_pep']); $i < 3; $i++)
                        <tr>
                            <td style="border: 1px solid #000; padding: 3px; height: 20px;"></td>
                            <td style="border: 1px solid #000; padding: 3px; height: 20px;"></td>
                        </tr>
                        @endfor
                    @else
                        <tr>
                            <td style="border: 1px solid #000; padding: 3px; height: 20px;"></td>
                            <td style="border: 1px solid #000; padding: 3px; height: 20px;"></td>
                        </tr>
                        <tr>
                            <td style="border: 1px solid #000; padding: 3px; height: 20px;"></td>
                            <td style="border: 1px solid #000; padding: 3px; height: 20px;"></td>
                        </tr>
                        <tr>
                            <td style="border: 1px solid #000; padding: 3px;">.......</td>
                            <td style="border: 1px solid #000; padding: 3px;"></td>
                        </tr>
                    @endif
                </table>
            </td>
        </tr>
        
        <!-- Row 11: Beneficiario -->
        <tr>
            <td class="number-cell">11</td>
            <td class="content-cell">
                <span class="center-text">IDENTIFICACIÓN DEL BENEFICIARIO DE LA OPERACIÓN</span>
                
                <span class="bold">Realiza esta operación a favor de (marque con una "X" según corresponda):</span><br>
                <span class="bold">1. De mi mismo</span> ( @if(($pep_data['operacion_favor'] ?? 'mi_mismo') == 'mi_mismo') X @else &nbsp; @endif ) 
                <span class="bold">2. De un tercero persona natural</span> ( @if(($pep_data['operacion_favor'] ?? '') == 'tercero_natural') X @else &nbsp; @endif ) 
                <span class="bold">3. Persona jurídica</span> ( @if(($pep_data['operacion_favor'] ?? '') == 'persona_juridica') X @else &nbsp; @endif ) 
                <span class="bold">4. Ente jurídico</span> ( @if(($pep_data['operacion_favor'] ?? '') == 'ente_juridico') X @else &nbsp; @endif )<br><br>
                
                <span class="bold">Si marcó la opción 2, complete la información del numeral 11.2. Si marcó la opción 3, complete la información del numeral 11.3. Si marcó la opción 4, complete la información del numeral 11.4.</span><br><br>
                
                <span class="bold">11.1. Si realiza la operación a favor de sí mismo, complete la información siguiente:</span><br><br>
                
                <span class="bold">i) Origen de los fondos/activos involucrados en la operación, cuando esta se realice en efectivo e iguale o supere el umbral para efectos del RO:</span><br>
                @if(($pep_data['operacion_favor'] ?? 'mi_mismo') == 'mi_mismo')
                <span class="underline" style="width: 100%; min-height: 20px; display: block;">{{ $pep_data['observaciones'] ?? 'No aplica' }}</span>
                @else
                <span class="underline" style="width: 100%; min-height: 20px; display: block;"></span>
                @endif
            </td>
        </tr>
        
        <!-- Row 12: Tercero persona natural -->
        <tr>
            <td class="number-cell">12</td>
            <td class="content-cell">
                <span class="bold">11.2. Si realiza la operación a favor de un tercero persona natural, complete la información siguiente:</span><br>
                <span class="bold">i) Nombres y apellido del tercero persona natural:</span> <span class="underline" style="width: 250px;">{{ ($pep_data['operacion_favor'] ?? '') == 'tercero_natural' ? ($pep_data['tercero_nombres'] ?? '') : '' }}</span><br>
                <span class="bold">ii) Tipo y número de documento de identidad:</span> <span class="underline" style="width: 200px;">{{ ($pep_data['operacion_favor'] ?? '') == 'tercero_natural' ? ($pep_data['tercero_documento'] ?? '') : '' }}</span><br>
                <span class="bold">iii) Datos de la representación (Marque con una "X" según corresponda): Poder por Escritura Pública ( ) Mandato ( )</span><br>
                <span class="bold">iv) Indicar si es o ha sido PEP ¿Ha cumplido, en los últimos 5 años, funciones públicas en un organismo público o funciones prominentes en una organización internacional? (marque con una "X" según corresponda): SI SOY ( ) SI HA SIDO ( ) NO ES ( ) NO HA SIDO ( )</span><br>
                <span class="bold">Si marcó "Si es" o "Si ha sido" complete la información siguiente:</span><br>
                <span class="bold">- Cargo:</span> <span class="underline" style="width: 250px;"></span><br>
                <span class="bold">v) Origen de los fondos/activos involucrados en la operación, cuando esta se realice en efectivo o iguale o supere el umbral para efectos del RO.</span>
            </td>
        </tr>
        
        <!-- Row 13: Persona jurídica -->
        <tr>
            <td class="number-cell">13</td>
            <td class="content-cell">
                <span class="bold">11.3. Si realiza la operación a favor de tercero persona jurídica o ente jurídico, en los casos aplicable a este último, complete la información siguiente:</span><br>
                <span class="bold">i) Denominación o Razón Social:</span> <span class="underline" style="width: 300px;">{{ in_array($pep_data['operacion_favor'] ?? '', ['persona_juridica', 'ente_juridico']) ? ($pep_data['razon_social'] ?? '') : '' }}</span><br>
                <span class="bold">ii) Número de RUC, de ser el caso:</span> <span class="underline" style="width: 150px;">{{ in_array($pep_data['operacion_favor'] ?? '', ['persona_juridica', 'ente_juridico']) ? ($pep_data['ruc'] ?? '') : '' }}</span><br>
                <span class="bold">iii) Datos de la representación (Marque con una "X" según corresponda): Poder por acta ( ) Poder por Escritura Pública ( ) Mandato ( )</span><br>
                <span class="bold">iv) Origen de los fondos/activos involucrados en la operación, cuando esta se realice en efectivo o iguale o supere el umbral para efectos del RO.</span><br>
                <span class="bold">v) Identificación del Beneficiario Final del Beneficiario de la operación, conforme al artículo 4 del Decreto Supremo N° 1372 y sus modificatorias; según corresponda (Nombres y Apellidos):</span><br>
                <span class="underline" style="width: 100%; height: 15px; display: block;"></span><br>
                <span class="bold">Afirmo y ratifico todo lo manifestado en la presente declaración jurada</span>
            </td>
        </tr>
        
        <!-- Signature section -->
        <tr>
            <td colspan="2" style="text-align: center; padding: 20px;">
                <span class="bold">FECHA (día/mes/año):</span> <span class="underline">{{ $fecha_generacion ?? now()->format('d/m/Y') }}</span>
                <span style="margin-left: 100px;" class="bold">FIRMA</span><br><br>
                <div style="margin-top: 30px;">
                    <div style="border-top: 1px solid #000; width: 200px; margin: 0 auto;"></div>
                    <div style="margin-top: 5px;">{{ strtoupper($persona->nombre ?? '') }} {{ strtoupper($persona->apellidos ?? '') }}</div>
                    <div>DNI: {{ $persona->DNI ?? '' }}</div>
                </div>
            </td>
        </tr>
        
        <!-- Footer -->
        <tr>
            <td colspan="2" class="small-text" style="text-align: center; padding: 5px;">
                Nota: Para ser completada por el sujeto obligado y, en su caso, se deberá solicitar a la UIF-Perú los antecedentes de supervisión. No enviar a la UIF-Perú, salvo solicitud expresa.
            </td>
        </tr>
    </table>
</body>
</html>
