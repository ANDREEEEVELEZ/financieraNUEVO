<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Declaracion Jurada PEP - Simple</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; }
        .header { text-align: center; font-weight: bold; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        td { border: 1px solid black; padding: 5px; }
        .numero { width: 30px; text-align: center; font-weight: bold; }
        .contenido { width: auto; }
    </style>
</head>
<body>
    <div class="header">
        FORMATO DE DECLARACION JURADA DE CONOCIMIENTO DEL CLIENTE<br>
        BAJO EL REGIMEN GENERAL - PERSONA NATURAL
    </div>

    <table>
        <tr>
            <td class="numero">1</td>
            <td class="contenido">
                <strong>Nombres:</strong> {{ strtoupper($clienteLimpio->nombres ?? 'NO DEFINIDO') }}<br>
                <strong>Apellidos:</strong> {{ strtoupper($clienteLimpio->apellidos ?? 'NO DEFINIDO') }}
            </td>
        </tr>
    </table>

    <table>
        <tr>
            <td class="numero">2</td>
            <td class="contenido">
                <strong>DNI:</strong> {{ $clienteLimpio->DNI ?? 'NO DEFINIDO' }}
            </td>
        </tr>
    </table>

    <table>
        <tr>
            <td class="numero">7</td>
            <td class="contenido">
                <strong>Ocupacion:</strong> {{ strtoupper($clienteLimpio->ocupacion ?? 'NO DEFINIDO') }}
            </td>
        </tr>
    </table>

    <p><strong>Fecha:</strong> {{ $fechaActual ?? date('d/m/Y') }}</p>
</body>
</html>
