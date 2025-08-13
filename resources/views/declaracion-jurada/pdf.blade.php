<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Declaración Jurada</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 10px;
            line-height: 1.2;
            margin: 0;
            padding: 20px;
        }
        
        .header {
            text-align: center;
            font-weight: bold;
            font-size: 12px;
            margin-bottom: 10px;
            border: 2px solid black;
            padding: 10px;
        }
        
        .subtitle {
            text-align: center;
            font-size: 9px;
            margin-bottom: 20px;
        }
        
        .row {
            border: 1px solid black;
            padding: 8px;
            margin-bottom: 0;
            min-height: 25px;
            display: flex;
            align-items: flex-start;
        }
        
        .row-number {
            font-weight: bold;
            width: 20px;
            flex-shrink: 0;
        }
        
        .row-content {
            flex: 1;
            line-height: 1.3;
        }
        
        .field-label {
            font-weight: bold;
        }
        
        .field-value {
            text-decoration: none;
            border-bottom: none;
            font-weight: normal;
            display: inline;
        }
        
        .checkbox {
            display: inline-block;
            width: 12px;
            height: 12px;
            border: 1px solid black;
            margin: 0 3px;
            text-align: center;
            line-height: 10px;
            font-weight: bold;
        }
        
        .checkbox.checked {
            background-color: transparent;
        }
        
        .checkbox.checked::after {
            content: "X";
            font-size: 8px;
        }
        
        .underline {
            border-bottom: 1px solid black;
            display: inline-block;
            min-width: 100px;
            padding-bottom: 1px;
        }
        
        .signature-section {
            margin-top: 30px;
            text-align: center;
        }
        
        .signature-line {
            border-top: 1px solid black;
            width: 200px;
            margin: 30px auto 5px auto;
        }
        
        .no-border-bottom {
            border-bottom: none !important;
        }
    </style>
</head>
<body>
    <div class="header">
        FORMATO DE DECLARACIÓN JURADA DE CONOCIMIENTO DEL CLIENTE BAJO EL RÉGIMEN GENERAL – PERSONA NATURAL
    </div>
    
    <div class="subtitle">
        (Información mínima para ser llenada por el cliente del sujeto obligado)
    </div>
    
    <!-- Fila 1: Nombres y Apellidos -->
    <div class="row">
        <div class="row-number">1</div>
        <div class="row-content">
            <span class="field-label">Nombres:</span> 
            <span class="field-value">{{ $nombres }}</span>
            <span style="margin-left: 200px;" class="field-label">Apellidos:</span> 
            <span class="field-value">{{ $apellidos }}</span>
        </div>
    </div>
    
    <!-- Fila 2: Tipo y número de documento -->
    <div class="row">
        <div class="row-number">2</div>
        <div class="row-content">
            <span class="field-label">Tipo y número de documento de identidad (marque con una "X" según corresponda):</span><br>
            <span class="field-label">DNI</span> 
            <span class="checkbox {{ $tipo_documento == 'DNI' ? 'checked' : '' }}"></span>
            <span class="field-label">Pasaporte</span> 
            <span class="checkbox {{ $tipo_documento == 'Pasaporte' ? 'checked' : '' }}"></span>
            <span class="field-label">Carné de Extranjería</span> 
            <span class="checkbox {{ $tipo_documento == 'Carnet_Extranjeria' ? 'checked' : '' }}"></span>
            <span class="field-label">Otro (Indique):</span> 
            <span class="checkbox {{ $tipo_documento == 'Otro' ? 'checked' : '' }}"></span>
            <span style="margin-left: 20px;" class="field-label">N°:</span> 
            <span class="field-value">{{ $numero_documento }}</span>
        </div>
    </div>
    
    <!-- Fila 3: Nacionalidad -->
    <div class="row">
        <div class="row-number">3</div>
        <div class="row-content">
            <span class="field-label">Nacionalidad (en el caso de extranjero):</span> 
            <span class="field-value">{{ $nacionalidad }}</span>
        </div>
    </div>
    
    <!-- Fila 4: Estado civil -->
    <div class="row">
        <div class="row-number">4</div>
        <div class="row-content">
            <span class="field-label">Estado civil (marque con una "X" según corresponda) soltero/a</span> 
            <span class="checkbox {{ strtolower($estado_civil) == 'soltero' ? 'checked' : '' }}"></span>
            <span class="field-label">casado/a</span> 
            <span class="checkbox {{ strtolower($estado_civil) == 'casado' ? 'checked' : '' }}"></span>
            <span class="field-label">viudo/a</span> 
            <span class="checkbox {{ strtolower($estado_civil) == 'viudo' ? 'checked' : '' }}"></span>
            <span class="field-label">divorciado/a</span> 
            <span class="checkbox {{ strtolower($estado_civil) == 'divorciado' ? 'checked' : '' }}"></span>
        </div>
    </div>
    
    <!-- Fila 5: Cónyuge -->
    <div class="row">
        <div class="row-number">5</div>
        <div class="row-content">
            <span class="field-label">Nombres y apellidos del cónyuge o conviviente:</span> 
            <span class="field-value">{{ $conyuge_nombres }}</span>
        </div>
    </div>
    
    <!-- Fila 6: Domicilio -->
    <div class="row">
        <div class="row-number">6</div>
        <div class="row-content">
            <span class="field-label">Domicilio (indicar tipo y nombre de la vía):</span> 
            <span class="field-value">{{ $tipo_via }} / Av. / Calle / Pasaje / Ovalo</span><br>
            <span class="field-value">{{ $nombre_via }}</span> 
            <span class="field-label">Urb. Complejo Zona - Sector:</span> 
            <span class="field-value">{{ $complejo_zona_sector }}</span> 
            <span class="field-label">Distrito:</span><br>
            <span class="field-value">{{ $distrito }}</span> 
            <span class="field-label">Int:</span> 
            <span class="field-value">{{ $interior }}</span> 
            <span class="field-label">Dpto./Int. N°:</span> 
            <span class="field-value">{{ $departamento_numero }}</span><br>
            <span class="field-label">Provincia:</span> 
            <span class="field-value">{{ $provincia }}</span> 
            <span class="field-label">Departamento:</span> 
            <span class="field-value">{{ $departamento }}</span>
        </div>
    </div>
    
    <!-- Fila 7: Ocupación -->
    <div class="row">
        <div class="row-number">7</div>
        <div class="row-content">
            <span class="field-label">Ocupación:</span> 
            <span class="field-value">{{ $ocupacion }}</span>
        </div>
    </div>
    
    <!-- Fila 8: Teléfonos -->
    <div class="row">
        <div class="row-number">8</div>
        <div class="row-content">
            <span class="field-label">N° Teléfono Fijo (indicar código de cuidada):</span> 
            <span class="field-value">{{ $telefono_fijo }}</span> 
            <span class="field-label">Celular:</span> 
            <span class="field-value">{{ $celular }}</span> 
            <span class="field-label">Correo electrónico:</span><br>
            <span class="field-value">{{ $correo }}</span>
        </div>
    </div>
    
    <!-- Fila 9: Propósito -->
    <div class="row">
        <div class="row-number">9</div>
        <div class="row-content">
            <span class="field-label">Propósito de la relación comercial o de negocio (siempre que esta se desprenda directamente del objeto del contrato):</span><br>
            <span class="field-value">{{ $proposito_relacion }}</span>
        </div>
    </div>
    
    <!-- Fila 10: PEP -->
    <div class="row">
        <div class="row-number">10</div>
        <div class="row-content">
            <span class="field-label">10.1 ¿Usted es o ha sido una Persona Expuesta Políticamente (PEP)?</span><br>
            <span class="field-label">NO SOY</span> 
            <span class="checkbox {{ $es_pep == 'NO_SOY' ? 'checked' : '' }}"></span>
            <span class="field-label">NO HE SIDO</span> 
            <span class="checkbox {{ $es_pep == 'NO_HE_SIDO' ? 'checked' : '' }}"></span>
            <span class="field-label">SOY</span> 
            <span class="checkbox {{ $es_pep == 'SOY' ? 'checked' : '' }}"></span>
            <span class="field-label">HE SIDO</span> 
            <span class="checkbox {{ $es_pep == 'HE_SIDO' ? 'checked' : '' }}"></span><br><br>
            
            @if($es_pep != 'NO_SOY' && $es_pep != 'NO_HE_SIDO')
                <span class="field-label">10.2 Si respondió SOY o HE SIDO, indique el cargo público que ocupa o ha ocupado y la entidad:</span><br>
                <span class="field-value">{{ $cargo_publico }}</span> - <span class="field-value">{{ $entidad_publica }}</span><br>
                <span class="field-label">Desde:</span> <span class="field-value">{{ $fecha_inicio_cargo }}</span> 
                <span class="field-label">Hasta:</span> <span class="field-value">{{ $fecha_fin_cargo }}</span><br><br>
                
                <span class="field-label">10.3 ¿Tiene algún parentesco hasta el segundo grado de consanguinidad, primer grado de afinidad o parentesco por adopción con alguna PEP?</span><br>
                <span class="field-value">{{ $parentesco_pep }}</span>
            @endif
        </div>
    </div>
    
    <!-- Fila 11: Representación -->
    <div class="row">
        <div class="row-number">11</div>
        <div class="row-content">
            <span class="field-label">¿Actúa por cuenta propia o de tercera persona?</span><br>
            <span class="field-label">Por mi mismo</span> 
            <span class="checkbox {{ $actua_por == 'MI_MISMO' ? 'checked' : '' }}"></span>
            <span class="field-label">Por tercera persona</span> 
            <span class="checkbox {{ $actua_por == 'TERCERA_PERSONA' ? 'checked' : '' }}"></span><br><br>
            
            @if($actua_por == 'MI_MISMO')
                <span class="field-label">11.1</span> <span class="field-value">NO APLICA</span><br>
                <span class="field-label">11.2</span> <span class="field-value">NO APLICA</span><br>
                <span class="field-label">11.3</span> <span class="field-value">NO APLICA</span>
            @else
                <span class="field-label">11.1 Nombres y apellidos del representado:</span> <span class="field-value">{{ $representado_nombres }} {{ $representado_apellidos }}</span><br>
                <span class="field-label">11.2 Tipo y número de documento de identidad del representado:</span> <span class="field-value">{{ $representado_documento }}</span><br>
                <span class="field-label">11.3 ¿El representado es o ha sido una Persona Expuesta Políticamente (PEP)?</span>
            @endif
        </div>
    </div>
    
    <!-- Sección de firma -->
    <div class="signature-section">
        <p>{{ $lugar }}, {{ $fecha_hoy }}</p>
        <div class="signature-line"></div>
        <p><strong>Firma del Declarante</strong></p>
        <p>{{ $nombres }} {{ $apellidos }}</p>
        <p>DNI: {{ $dni }}</p>
    </div>
</body>
</html>
