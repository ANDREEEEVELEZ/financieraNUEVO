<?php

require_once 'vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// Test básico para verificar codificación UTF-8
$options = new Options();
$options->set('defaultFont', 'Arial');
$options->set('isHtml5ParserEnabled', false);
$options->set('isPhpEnabled', false);
$options->set('isRemoteEnabled', false);

$dompdf = new Dompdf($options);

$html = '<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Test Encoding</title>
</head>
<body>
    <h1>Test de codificación</h1>
    <p>Texto normal: ABCD 123</p>
    <p>Caracteres especiales: No &nbsp; underline</p>
    <p>Documento funcional</p>
</body>
</html>';

try {
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    
    echo "✓ PDF generado correctamente sin errores de codificación\n";
    
    // Guardar el archivo para verificar
    file_put_contents('test_encoding.pdf', $dompdf->output());
    echo "✓ Archivo guardado como test_encoding.pdf\n";
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}
