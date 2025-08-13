<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Declaracion Jurada</title>
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
        
        .checkbox.checked::after {
            content: "X";
            font-size: 8px;
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
    </style>
</head>
<body>
    <div class="header">
        FORMATO DE DECLARACION JURADA DE CONOCIMIENTO DEL CLIENTE BAJO EL REGIMEN GENERAL - PERSONA NATURAL
    </div>
    
    <div class="subtitle">
        (Informacion minima para ser llenada por el cliente del sujeto obligado)
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
    
    <!-- Fila 2: Tipo y numero de documento -->
    <div class="row">
        <div class="row-number">2</div>
        <div class="row-content">
            <span class="field-label">Tipo y numero de documento de identidad (marque con una "X" segun corresponda):</span><br>
            <span class="field-label">DNI</span> 
            <span class="checkbox {{ $tipo_documento == 'DNI' ? 'checked' : '' }}"></span>
            <span class="field-label">Pasaporte</span> 
            <span class="checkbox {{ $tipo_documento == 'Pasaporte' ? 'checked' : '' }}"></span>
            <span class="field-label">Carne de Extranjeria</span> 
            <span class="checkbox {{ $tipo_documento == 'Carnet_Extranjeria' ? 'checked' : '' }}"></span>
            <span class="field-label">Otro (Indique):</span> 
            <span class="checkbox {{ $tipo_documento == 'Otro' ? 'checked' : '' }}"></span>
            <span style="margin-left: 20px;" class="field-label">N:</span> 
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
            <span class="field-label">Estado civil (marque con una "X" segun corresponda) soltero/a</span> 
            <span class="checkbox {{ strtolower($estado_civil) == 'soltero' ? 'checked' : '' }}"></span>
            <span class="field-label">casado/a</span> 
            <span class="checkbox {{ strtolower($estado_civil) == 'casado' ? 'checked' : '' }}"></span>
            <span class="field-label">viudo/a</span> 
            <span class="checkbox {{ strtolower($estado_civil) == 'viudo' ? 'checked' : '' }}"></span>
            <span class="field-label">divorciado/a</span> 
            <span class="checkbox {{ strtolower($estado_civil) == 'divorciado' ? 'checked' : '' }}"></span>
        </div>
    </div>
    
    <!-- Fila 5: Conyuge -->
    <div class="row">
        <div class="row-number">5</div>
        <div class="row-content">
            <span class="field-label">Nombres y apellidos del conyuge o conviviente:</span> 
            <span class="field-value">{{ $conyuge_nombres }}</span>
        </div>
    </div>
    
    <!-- Fila 6: Domicilio -->
    <div class="row">
        <div class="row-number">6</div>
        <div class="row-content">
            <span class="field-label">Domicilio (indicar tipo y nombre de la via):</span> 
            <span class="field-value">{{ $tipo_via }} / Av. / Calle / Pasaje / Ovalo</span><br>
            <span class="field-value">{{ $nombre_via }}</span> 
            <span class="field-label">Urb. Complejo Zona - Sector:</span> 
            <span class="field-value">{{ $complejo_zona_sector }}</span> 
            <span class="field-label">Distrito:</span><br>
            <span class="field-value">{{ $distrito }}</span> 
            <span class="field-label">Int:</span> 
            <span class="field-value">{{ $interior }}</span> 
            <span class="field-label">Dpto./Int. N:</span> 
            <span class="field-value">{{ $departamento_numero }}</span><br>
            <span class="field-label">Provincia:</span> 
            <span class="field-value">{{ $provincia }}</span> 
            <span class="field-label">Departamento:</span> 
            <span class="field-value">{{ $departamento }}</span>
        </div>
    </div>
    
    <!-- Fila 7: Ocupacion -->
    <div class="row">
        <div class="row-number">7</div>
        <div class="row-content">
            <span class="field-label">Ocupacion:</span> 
            <span class="field-value">{{ $ocupacion }}</span>
        </div>
    </div>
    
    <!-- Fila 8: Telefonos -->
    <div class="row">
        <div class="row-number">8</div>
        <div class="row-content">
            <span class="field-label">N Telefono Fijo (indicar codigo de cuidada):</span> 
            <span class="field-value">{{ $telefono_fijo }}</span> 
            <span class="field-label">Celular:</span> 
            <span class="field-value">{{ $celular }}</span> 
            <span class="field-label">Correo electronico:</span><br>
            <span class="field-value">{{ $correo }}</span>
        </div>
    </div>
    
    <!-- Fila 9: Proposito -->
    <div class="row">
        <div class="row-number">9</div>
        <div class="row-content">
            <span class="field-label">Proposito de la relacion comercial o de negocio (siempre que esta se desprenda directamente del objeto del contrato):</span><br>
            <span class="field-value">{{ $proposito_relacion }}</span>
        </div>
    </div>
    
    <!-- Fila 10: PEP -->
    <div class="row">
        <div class="row-number">10</div>
        <div class="row-content">
            <span class="field-label">10.1 Usted es o ha sido una Persona Expuesta Politicamente (PEP)?</span><br>
            <span class="field-label">NO SOY</span> 
            <span class="checkbox {{ $es_pep == 'NO_SOY' ? 'checked' : '' }}"></span>
            <span class="field-label">NO HE SIDO</span> 
            <span class="checkbox {{ $es_pep == 'NO_HE_SIDO' ? 'checked' : '' }}"></span>
            <span class="field-label">SOY</span> 
            <span class="checkbox {{ $es_pep == 'SOY' ? 'checked' : '' }}"></span>
            <span class="field-label">HE SIDO</span> 
            <span class="checkbox {{ $es_pep == 'HE_SIDO' ? 'checked' : '' }}"></span><br><br>
            
            @if($es_pep != 'NO_SOY' && $es_pep != 'NO_HE_SIDO')
                <span class="field-label">10.2 Si respondio SOY o HE SIDO, indique el cargo publico que ocupa o ha ocupado y la entidad:</span><br>
                <span class="field-value">{{ $cargo_publico }}</span> - <span class="field-value">{{ $entidad_publica }}</span><br>
                <span class="field-label">Desde:</span> <span class="field-value">{{ $fecha_inicio_cargo }}</span> 
                <span class="field-label">Hasta:</span> <span class="field-value">{{ $fecha_fin_cargo }}</span><br><br>
                
                <span class="field-label">10.3 Tiene algun parentesco hasta el segundo grado de consanguinidad, primer grado de afinidad o parentesco por adopcion con alguna PEP?</span><br>
                <span class="field-value">{{ $parentesco_pep }}</span>
            @endif
        </div>
    </div>
    
    <!-- Fila 11: Representacion -->
    <div class="row">
        <div class="row-number">11</div>
        <div class="row-content">
            <span class="field-label">Actua por cuenta propia o de tercera persona?</span><br>
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
                <span class="field-label">11.2 Tipo y numero de documento de identidad del representado:</span> <span class="field-value">{{ $representado_documento }}</span><br>
                <span class="field-label">11.3 El representado es o ha sido una Persona Expuesta Politicamente (PEP)?</span>
            @endif
        </div>
    </div>
    
    <!-- Seccion de firma -->
    <div class="signature-section">
        <p>{{ $lugar }}, {{ $fecha_hoy }}</p>
        <div class="signature-line"></div>
        <p><strong>Firma del Declarante</strong></p>
        <p>{{ $nombres }} {{ $apellidos }}</p>
        <p>DNI: {{ $dni }}</p>
    </div>
</body>
</html>
