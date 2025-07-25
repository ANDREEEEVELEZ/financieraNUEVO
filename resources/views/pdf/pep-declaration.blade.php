<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Declaración Jurada - PEP</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 1.4;
            margin: 20px;
            color: #000;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .header h1 {
            font-size: 16px;
            font-weight: bold;
            margin: 10px 0;
        }
        .header h2 {
            font-size: 14px;
            font-weight: bold;
            margin: 5px 0;
        }
        .form-section {
            margin-bottom: 20px;
        }
        .form-row {
            display: flex;
            margin-bottom: 8px;
            align-items: center;
        }
        .form-label {
            font-weight: bold;
            margin-right: 10px;
            min-width: 120px;
        }
        .form-input {
            border-bottom: 1px solid #000;
            min-width: 200px;
            padding: 2px 5px;
            display: inline-block;
        }
        .checkbox-group {
            margin: 10px 0;
        }
        .checkbox {
            width: 12px;
            height: 12px;
            border: 1px solid #000;
            display: inline-block;
            margin-right: 5px;
        }
        .signature-section {
            margin-top: 40px;
            text-align: center;
        }
        .signature-line {
            border-top: 1px solid #000;
            width: 250px;
            margin: 50px auto 10px auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
        }
        td {
            padding: 5px;
            vertical-align: top;
        }
        .section-title {
            font-weight: bold;
            font-size: 14px;
            margin: 20px 0 10px 0;
            text-decoration: underline;
        }
        .small-text {
            font-size: 10px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>DECLARACIÓN JURADA</h1>
        <h2>RÉGIMEN GENERAL - PERSONA NATURAL</h2>
        <h2>PERSONAS EXPUESTAS POLÍTICAMENTE (PEP)</h2>
        <p class="small-text">Res. SBS N° 2351-2023</p>
    </div>

    <div class="form-section">
        <table>
            <tr>
                <td style="width: 30%"><strong>Fecha:</strong></td>
                <td style="border-bottom: 1px solid #000; width: 70%">{{ $fecha_generacion }}</td>
            </tr>
        </table>
    </div>

    <div class="section-title">I. DATOS PERSONALES</div>
    
    <table>
        <tr>
            <td style="width: 25%"><strong>Apellidos:</strong></td>
            <td style="border-bottom: 1px solid #000; width: 75%">{{ strtoupper($persona->apellidos) }}</td>
        </tr>
        <tr>
            <td><strong>Nombres:</strong></td>
            <td style="border-bottom: 1px solid #000;">{{ strtoupper($persona->nombre) }}</td>
        </tr>
        <tr>
            <td><strong>DNI:</strong></td>
            <td style="border-bottom: 1px solid #000;">{{ $persona->DNI }}</td>
        </tr>
        <tr>
            <td><strong>Fecha de Nacimiento:</strong></td>
            <td style="border-bottom: 1px solid #000;">{{ \Carbon\Carbon::parse($persona->fecha_nacimiento)->format('d/m/Y') }}</td>
        </tr>
        <tr>
            <td><strong>Estado Civil:</strong></td>
            <td style="border-bottom: 1px solid #000;">{{ $persona->estado_civil }}</td>
        </tr>
        <tr>
            <td><strong>Nacionalidad:</strong></td>
            <td style="border-bottom: 1px solid #000;">PERUANA</td>
        </tr>
    </table>

    <div class="section-title">II. DATOS DE CONTACTO</div>
    
    <table>
        <tr>
            <td style="width: 25%"><strong>Dirección:</strong></td>
            <td style="border-bottom: 1px solid #000; width: 75%">{{ strtoupper($persona->direccion) }}</td>
        </tr>
        <tr>
            <td><strong>Distrito:</strong></td>
            <td style="border-bottom: 1px solid #000;">{{ strtoupper($persona->distrito) }}</td>
        </tr>
        <tr>
            <td><strong>Provincia:</strong></td>
            <td style="border-bottom: 1px solid #000;">SULLANA</td>
        </tr>
        <tr>
            <td><strong>Departamento:</strong></td>
            <td style="border-bottom: 1px solid #000;">PIURA</td>
        </tr>
        <tr>
            <td><strong>Teléfono:</strong></td>
            <td style="border-bottom: 1px solid #000;">{{ $persona->celular }}</td>
        </tr>
        <tr>
            <td><strong>Correo Electrónico:</strong></td>
            <td style="border-bottom: 1px solid #000;">{{ strtolower($persona->correo) }}</td>
        </tr>
    </table>

    <div class="section-title">III. DECLARACIÓN</div>
    
    <p style="text-align: justify; margin-bottom: 15px;">
        Declaro bajo juramento que la información consignada en el presente documento es veraz y completa, 
        y me comprometo a comunicar cualquier cambio que se produzca en la misma.
    </p>

    <div class="checkbox-group">
        <div style="margin-bottom: 10px;">
            <span class="checkbox"></span> 
            <strong>SÍ soy una Persona Expuesta Políticamente (PEP)</strong>
        </div>
        <div style="margin-bottom: 10px;">
            <span class="checkbox"></span> 
            <strong>NO soy una Persona Expuesta Políticamente (PEP)</strong>
        </div>
    </div>

    <div style="margin-top: 20px;">
        <p><strong>Cargo/Función (si aplica):</strong></p>
        <div style="border-bottom: 1px solid #000; height: 25px; margin-bottom: 10px;"></div>
        
        <p><strong>Institución/Entidad (si aplica):</strong></p>
        <div style="border-bottom: 1px solid #000; height: 25px; margin-bottom: 10px;"></div>
        
        <p><strong>Período de ejercicio del cargo (si aplica):</strong></p>
        <table style="margin-top: 5px;">
            <tr>
                <td style="width: 15%">Desde:</td>
                <td style="border-bottom: 1px solid #000; width: 35%; height: 25px;"></td>
                <td style="width: 15%; text-align: center;">Hasta:</td>
                <td style="border-bottom: 1px solid #000; width: 35%; height: 25px;"></td>
            </tr>
        </table>
    </div>

    <div class="section-title">IV. DECLARACIÓN JURADA</div>
    
    <p style="text-align: justify; margin-bottom: 20px;">
        Declaro bajo juramento que los datos consignados en la presente declaración son ciertos y 
        me comprometo a informar inmediatamente cualquier cambio en mi condición de PEP.
    </p>

    <p style="text-align: justify; margin-bottom: 20px;">
        Asimismo, autorizo a la entidad financiera a verificar la información proporcionada y 
        declaro conocer las responsabilidades penales en caso de falsedad.
    </p>

    <div class="signature-section">
        <div style="margin-top: 60px;">
            <div class="signature-line"></div>
            <p><strong>FIRMA DEL DECLARANTE</strong></p>
            <p>{{ strtoupper($persona->nombre) }} {{ strtoupper($persona->apellidos) }}</p>
            <p>DNI: {{ $persona->DNI }}</p>
        </div>
    </div>

    <div style="margin-top: 40px; font-size: 10px; text-align: center; color: #666;">
        <p>Documento generado automáticamente el {{ $fecha_generacion_completa }}</p>
        <p>Sistema EmprendeConmigo - Declaración Jurada PEP</p>
    </div>
</body>
</html>
