<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Declaración Jurada de Conocimiento del Cliente</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            line-height: 1.2;
            margin: 0;
            padding: 15px;
            color: #000;
        }
        
        .header {
            text-align: center;
            margin-bottom: 15px;
        }
        
        .logo {
            float: left;
            width: 80px;
            height: 60px;
        }
        
        .title {
            background-color: #d3d3d3;
            padding: 8px;
            font-weight: bold;
            text-align: center;
            font-size: 12px;
            margin-bottom: 5px;
        }
        
        .subtitle {
            text-align: center;
            font-size: 10px;
            font-style: italic;
            margin-bottom: 15px;
        }
        
        .section {
            border: 1px solid #000;
            margin-bottom: 3px;
            page-break-inside: avoid;
        }
        
        .section-header {
            background-color: #e6e6e6;
            padding: 5px;
            font-weight: bold;
            font-size: 10px;
            border-bottom: 1px solid #000;
        }
        
        .row {
            display: table;
            width: 100%;
            border-collapse: collapse;
        }
        
        .cell {
            display: table-cell;
            padding: 5px;
            border-right: 1px solid #000;
            vertical-align: top;
        }
        
        .cell:last-child {
            border-right: none;
        }
        
        .cell-number {
            width: 30px;
            text-align: center;
            font-weight: bold;
            background-color: #f5f5f5;
        }
        
        .cell-content {
            width: auto;
        }
        
        .filled-data {
            background-color: #f0f8ff;
            padding: 2px 4px;
            border: 1px solid #ccc;
            display: inline-block;
            min-width: 100px;
        }
        
        .empty-line {
            border-bottom: 1px solid #000;
            min-height: 15px;
            display: inline-block;
            width: 200px;
            margin: 0 5px;
        }
        
        .checkbox {
            display: inline-block;
            width: 12px;
            height: 12px;
            border: 1px solid #000;
            margin-right: 5px;
            text-align: center;
            line-height: 10px;
        }
        
        .checkbox.checked::after {
            content: "X";
            font-weight: bold;
        }
        
        .small-text {
            font-size: 9px;
        }
        
        .footer {
            margin-top: 20px;
            text-align: center;
        }
        
        .signature-line {
            border-bottom: 1px solid #000;
            width: 300px;
            margin: 20px auto;
            height: 50px;
        }
        
        .date-section {
            text-align: right;
            margin-top: 20px;
        }
        
        .page-break {
            page-break-before: always;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">
            <!-- Logo placeholder -->
            <div style="border: 1px solid #ccc; width: 80px; height: 60px; text-align: center; line-height: 60px; font-size: 8px;">
                LOGO SBS
            </div>
        </div>
        <div style="clear: both;"></div>
    </div>

    <div class="title">
        FORMATO DE DECLARACIÓN JURADA DE CONOCIMIENTO DEL CLIENTE BAJO EL RÉGIMEN GENERAL – PERSONA NATURAL
    </div>
    
    <div class="subtitle">
        (Información mínima para ser llenada por el cliente del sujeto obligado)
    </div>
    
    <p style="margin-bottom: 15px;">Por el presente documento, declaro bajo juramento, lo siguiente:</p>

    <!-- Sección 1: Información Personal -->
    <div class="section">
        <div class="row">
            <div class="cell cell-number">1</div>
            <div class="cell cell-content">
                <strong>Nombres:</strong> <span class="filled-data">{{ $nombres }}</span>
                <span style="margin-left: 50px;"><strong>Apellidos:</strong></span> <span class="filled-data">{{ $apellidos }}</span>
            </div>
        </div>
    </div>

    <!-- Sección 2: Documento de Identidad -->
    <div class="section">
        <div class="row">
            <div class="cell cell-number">2</div>
            <div class="cell cell-content">
                <strong>Tipo y número de documento de identidad (marque con una "X" según corresponda):</strong><br>
                DNI (<span class="checkbox checked"></span>) Pasaporte (<span class="checkbox"></span>) Carné de Extranjería (<span class="checkbox"></span>) Otro (Indique): <span class="empty-line"></span><br>
                N°: <span class="filled-data">{{ $dni }}</span>
            </div>
        </div>
    </div>

    <!-- Sección 3: Nacionalidad -->
    <div class="section">
        <div class="row">
            <div class="cell cell-number">3</div>
            <div class="cell cell-content">
                <strong>Nacionalidad (en el caso de extranjero):</strong> <span class="filled-data">{{ $nacionalidad }}</span>
            </div>
        </div>
    </div>

    <!-- Sección 4: Estado Civil -->
    <div class="section">
        <div class="row">
            <div class="cell cell-number">4</div>
            <div class="cell cell-content">
                <strong>Estado civil (marque con una "X" según corresponda):</strong> 
                soltero(a) (<span class="checkbox{{ $estado_civil == 'Soltero' ? ' checked' : '' }}"></span>), 
                casado(a) (<span class="checkbox{{ $estado_civil == 'Casado' ? ' checked' : '' }}"></span>), 
                viudo(a) (<span class="checkbox{{ $estado_civil == 'Viudo' ? ' checked' : '' }}"></span>), 
                divorciado/a (<span class="checkbox{{ $estado_civil == 'Divorciado' ? ' checked' : '' }}"></span>) 
                Conviviente (<span class="checkbox{{ $estado_civil == 'Conviviente' ? ' checked' : '' }}"></span>)
            </div>
        </div>
    </div>

    <!-- Sección 5: Cónyuge -->
    <div class="section">
        <div class="row">
            <div class="cell cell-number">5</div>
            <div class="cell cell-content">
                <strong>Nombres y apellidos del cónyuge o conviviente:</strong> <span class="empty-line" style="width: 300px;"></span>
            </div>
        </div>
    </div>

    <!-- Sección 6: Domicilio -->
    <div class="section">
        <div class="row">
            <div class="cell cell-number">6</div>
            <div class="cell cell-content">
                <strong>Domicilio (indicar tipo y nombre de la vía):</strong> Jr. / Av. / Calle / Pasaje / Óvalo: <span class="filled-data">{{ $domicilio }}</span> N°: <span class="empty-line" style="width: 50px;"></span> Dpto.-Int. N°: <span class="empty-line" style="width: 50px;"></span><br>
                Urb. - Complejo - Zona – Sector: <span class="empty-line" style="width: 150px;"></span> 
                Distrito: <span class="filled-data">{{ $distrito }}</span> 
                Provincia: <span class="empty-line" style="width: 100px;"></span> 
                Departamento: <span class="empty-line" style="width: 100px;"></span>
            </div>
        </div>
    </div>

    <!-- Sección 7: Ocupación -->
    <div class="section">
        <div class="row">
            <div class="cell cell-number">7</div>
            <div class="cell cell-content">
                <strong>Ocupación:</strong> <span class="filled-data">{{ $ocupacion }}</span>
            </div>
        </div>
    </div>

    <!-- Sección 8: Contacto -->
    <div class="section">
        <div class="row">
            <div class="cell cell-number">8</div>
            <div class="cell cell-content">
                <strong>N° Teléfono Fijo (indicar código de ciudad):</strong> <span class="empty-line" style="width: 120px;"></span>
                <strong>Celular:</strong> <span class="filled-data">{{ $celular }}</span>
                <strong>Correo electrónico:</strong> <span class="filled-data">{{ $correo }}</span>
            </div>
        </div>
    </div>

    <!-- Sección 9: Propósito de la relación -->
    <div class="section">
        <div class="row">
            <div class="cell cell-number">9</div>
            <div class="cell cell-content">
                <strong>Propósito de la relación comercial o de negocio (siempre que ésta se desprenda directamente del objeto del contrato):</strong><br>
                <span class="empty-line" style="width: 100%; height: 40px; display: block;"></span>
            </div>
        </div>
    </div>

    <!-- Sección 10: Información PEP -->
    <div class="section">
        <div class="section-header">
            10.1. Indicar si es o ha sido PEP: ¿Ha cumplido, en los últimos 5 años, i) funciones públicas en un organismo público o ii) funciones prominentes en una organización internacional? (marque con una "X" según corresponda):
        </div>
        <div class="row">
            <div class="cell cell-number">10</div>
            <div class="cell cell-content">
                SÍ SOY (<span class="checkbox"></span>) SÍ HE SIDO (<span class="checkbox"></span>) NO SOY (<span class="checkbox checked"></span>) NO HE SIDO (<span class="checkbox checked"></span>)<br>
                ¿Ha sido colaborador directo de la máxima autoridad en dichas instituciones? SÍ SOY (<span class="checkbox"></span>) SÍ HE SIDO (<span class="checkbox"></span>) NO SOY (<span class="checkbox checked"></span>) NO HE SIDO (<span class="checkbox checked"></span>)<br>
                Si marcó "Si soy" o "Si he sido", complete la información siguiente:<br>
                Cargo: <span class="empty-line" style="width: 200px;"></span> Nombre de la institución (organismo público o organización internacional): <span class="empty-line" style="width: 250px;"></span>
            </div>
        </div>
    </div>

    <!-- Page break for second page -->
    <div class="page-break"></div>

    <!-- Sección 10.2: Familiares PEP -->
    <div class="section">
        <div class="section-header">
            10.2. De ser PEP, indicar los nombres y apellidos de sus:
        </div>
        <div class="row">
            <div class="cell cell-content">
                <strong>(1) Parientes hasta el 2do grado de consanguinidad</strong> <span class="small-text">(Padre, Madre, Hjasjr, Abuelos/as, nietos/as, hermanos/as)</span> <strong>y 2do de afinidad</strong> <span class="small-text">(suegros/a, yerno, nuera, cuñados/as, abuelos del cónyuge, nietos/as del cónyuge)</span><br>
                <strong>(2) Cónyuge o conviviente:</strong><br><br>
                
                <strong>10.3. Indicar si es pariente de PEP hasta el 2do. grado de consanguinidad</strong> <span class="small-text">(Padre, Madre, Hijastro, Abuelos, nietos/as, hermanos/as)</span> <strong>; 2do de afinidad</strong> <span class="small-text">(suegros, yerno, nuera, cuñados, abuelos del cónyuge, nietos del cónyuge)</span> <strong>y cónyuge o conviviente (marque con una "X" según corresponda):</strong> SÍ SOY (<span class="checkbox"></span>) NO SOY (<span class="checkbox checked"></span>)<br>
                Si marcó "SÍ SOY" especifique los datos siguientes:<br>
                
                <table style="width: 100%; border-collapse: collapse; margin-top: 10px;">
                    <tr style="background-color: #f0f0f0;">
                        <td style="border: 1px solid #000; padding: 5px; text-align: center;"><strong>Nombres y Apellidos del PEP</strong></td>
                        <td style="border: 1px solid #000; padding: 5px; text-align: center;"><strong>Indicar Parentesco</strong></td>
                    </tr>
                    <tr>
                        <td style="border: 1px solid #000; padding: 15px; height: 30px;"></td>
                        <td style="border: 1px solid #000; padding: 15px; height: 30px;"></td>
                    </tr>
                    <tr>
                        <td style="border: 1px solid #000; padding: 15px; height: 30px;"></td>
                        <td style="border: 1px solid #000; padding: 15px; height: 30px;"></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>

    <!-- Sección 11: Identidad del Beneficiario -->
    <div class="section">
        <div class="section-header">
            IDENTIDAD DEL BENEFICIARIO DE LA OPERACIÓN
        </div>
        <div class="row">
            <div class="cell cell-content">
                <strong>Realizo esta operación a favor de (marque con una "X" según corresponda):</strong><br>
                <strong>1. De mí mismo</strong> (<span class="checkbox checked"></span>) <strong>2. De un tercero persona natural</strong> (<span class="checkbox"></span>)<br>
                <strong>3. Persona jurídica</strong> (<span class="checkbox"></span>) <strong>4. Ente jurídico</strong> (<span class="checkbox"></span>)<br><br>
                
                Si marcó la opción 2, complete la información del numeral 11.2. Si marcó la opción 3, complete la información del numeral 11.3. Si marcó la opción 4, complete la información del numeral 11.4.
            </div>
        </div>
    </div>

    <!-- Sección 11.1 -->
    <div class="section">
        <div class="row">
            <div class="cell cell-number">11.1</div>
            <div class="cell cell-content">
                <strong>Si realiza la operación a favor de sí mismo, complete la información siguiente:</strong><br>
                i) Origen de los fondos/activos involucrados en la operación, cuando ésta se realice en efectivo e iguale o supere el umbral para efectos del RO: NO APLICA<br><br>
                <span style="color: green; font-weight: bold;">✓ Esta sección se completa automáticamente con los datos del cliente actual</span>
            </div>
        </div>
    </div>

    <!-- Sección 11.2 -->
    <div class="section">
        <div class="row">
            <div class="cell cell-number">11.2</div>
            <div class="cell cell-content">
                <strong>Si realiza la operación a favor de un tercero persona natural, complete la información siguiente:</strong><br>
                i) Nombres y apellido del tercero persona natural: <span class="empty-line" style="width: 300px;"></span><br>
                ii) Tipo y número de documento de identidad: <span class="empty-line" style="width: 200px;"></span><br>
                iii) Datos de la representación: (Marque con una "X" según corresponda): Poder por Escritura Pública (<span class="checkbox"></span>) Mandato (<span class="checkbox"></span>)<br>
                iv) Indicar si es o ha sido PEP: ¿Ha cumplido, en los últimos 5 años: i) funciones públicas en un organismo público o ii) funciones prominentes en una organización internacional? (marque con una "X" según corresponda): SÍ ES (<span class="checkbox"></span>) SÍ HA SIDO (<span class="checkbox"></span>) NO ES (<span class="checkbox"></span>) NO HA SIDO (<span class="checkbox"></span>)<br>
                Si marcó "Si es" o "Si ha sido", complete la información siguiente:<br>
                - Cargo: <span class="empty-line" style="width: 200px;"></span> - Nombre de la institución (organismo público o organización internacional): <span class="empty-line" style="width: 250px;"></span><br>
                v) Origen de los fondos/activos involucrados en la operación, cuando ésta se realice en efectivo e iguale o supere el umbral para efectos del RO:
            </div>
        </div>
    </div>

    <!-- Sección 11.3 -->
    <div class="section">
        <div class="row">
            <div class="cell cell-number">11.3</div>
            <div class="cell cell-content">
                <strong>Si realiza la operación a favor de un tercero persona jurídica o ente jurídico, en lo que resulte aplicable a esta último, complete la información siguiente:</strong><br>
                i) Denominación o Razón Social: <span class="empty-line" style="width: 300px;"></span><br>
                ii) Número de RUC, de ser el caso: <span class="empty-line" style="width: 150px;"></span><br>
                iii) Datos de la representación: (Marque con una "X" según corresponda): Poder por acta (<span class="checkbox"></span>) Poder por escritura Pública (<span class="checkbox"></span>) Mandato (<span class="checkbox"></span>)<br>
                iv) Origen de los fondos/activos involucrados en la operación, cuando ésta se realice en efectivo e iguale o supere el umbral para efectos del RO:<br>
                v) Identificación del Beneficiario Final del Beneficiario de la operación, conforme al artículo 4 del Decreto Legislativo N° 1372 y sus modificatorias, según corresponde (Nombres y Apellidos):
            </div>
        </div>
    </div>

    <!-- Sección 12: Declaración Final -->
    <div class="section">
        <div class="row">
            <div class="cell cell-number">12</div>
            <div class="cell cell-content">
                <strong>Afirmo y ratifico todo lo manifestado en la presente declaración jurada:</strong><br><br>
                <strong>Observaciones adicionales:</strong><br>
                <div style="border: 1px solid #000; height: 60px; margin-top: 10px;"></div>
            </div>
        </div>
    </div>

    <!-- Footer con fecha y firma -->
    <div class="footer">
        <div class="date-section">
            <strong>FECHA (día/mes/año):</strong> <span style="border-bottom: 1px solid #000; padding: 0 10px;">{{ $fecha_actual }}</span> / <span style="border-bottom: 1px solid #000; padding: 0 10px;">______</span> / <span style="border-bottom: 1px solid #000; padding: 0 10px;">______</span>
            <span style="margin-left: 50px;"><strong>FIRMA</strong></span>
        </div>
        
        <div class="signature-line"></div>
        
        <div style="text-align: center; margin-top: 10px;">
            <strong>{{ strtoupper($nombres) }} {{ strtoupper($apellidos) }}</strong><br>
            <strong>DNI: {{ $dni }}</strong>
        </div>
        
        <div style="margin-top: 30px; font-size: 9px; text-align: center;">
            <strong>Nota:</strong> Para ser conservado por el sujeto obligado, y en su caso remitida a solicitud de la UIF-Perú en actividades de supervisión. No se envía a la UIF-Perú, salvo solicitud expresa.
        </div>
    </div>

</body>
</html>
