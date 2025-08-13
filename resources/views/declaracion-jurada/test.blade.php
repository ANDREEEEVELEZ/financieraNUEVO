<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Declaracion Jurada</title>
    <style>
        body { font-family: Arial; font-size: 12px; margin: 20px; }
        .header { text-align: center; font-weight: bold; border: 2px solid black; padding: 10px; margin-bottom: 20px; }
        .row { border: 1px solid black; padding: 8px; margin-bottom: 1px; }
        .label { font-weight: bold; }
    </style>
</head>
<body>
    <div class="header">DECLARACION JURADA - CONOCIMIENTO DEL CLIENTE</div>
    
    <div class="row">
        <span class="label">1. Nombres:</span> {{ $nombres ?? '' }}
        <span class="label" style="margin-left: 50px;">Apellidos:</span> {{ $apellidos ?? '' }}
    </div>
    
    <div class="row">
        <span class="label">2. DNI:</span> {{ $dni ?? '' }}
    </div>
    
    <div class="row">
        <span class="label">3. Nacionalidad:</span> {{ $nacionalidad ?? 'PERUANA' }}
    </div>
    
    <div class="row">
        <span class="label">4. Estado Civil:</span> {{ $estado_civil ?? '' }}
    </div>
    
    <div class="row">
        <span class="label">5. Conyuge:</span> {{ $conyuge_nombres ?? '' }}
    </div>
    
    <div class="row">
        <span class="label">6. Domicilio:</span> {{ $domicilio ?? '' }}, {{ $distrito ?? '' }}
    </div>
    
    <div class="row">
        <span class="label">7. Ocupacion:</span> {{ $ocupacion ?? '' }}
    </div>
    
    <div class="row">
        <span class="label">8. Celular:</span> {{ $celular ?? '' }}
        <span class="label" style="margin-left: 30px;">Email:</span> {{ $correo ?? '' }}
    </div>
    
    <div class="row">
        <span class="label">9. Proposito:</span> PRESTAMO
    </div>
    
    <div class="row">
        <span class="label">10. PEP:</span> {{ $es_pep ?? 'NO SOY' }}
    </div>
    
    <div class="row">
        <span class="label">11. Actua por:</span> {{ $actua_por ?? 'MI MISMO' }}
    </div>
    
    <div style="margin-top: 50px; text-align: center;">
        <p>{{ $lugar ?? 'Sullana' }}, {{ $fecha_hoy ?? date('d/m/Y') }}</p>
        <br><br>
        <div style="border-top: 1px solid black; width: 200px; margin: 0 auto;"></div>
        <p>Firma del Declarante</p>
        <p>{{ $nombres ?? '' }} {{ $apellidos ?? '' }}</p>
        <p>DNI: {{ $dni ?? '' }}</p>
    </div>
</body>
</html>
