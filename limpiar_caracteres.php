<?php

$archivo = 'resources/views/pdf/declaracion-jurada.blade.php';
$contenido = file_get_contents($archivo);

// Array de reemplazos para caracteres especiales
$reemplazos = [
    'á' => 'a',
    'é' => 'e', 
    'í' => 'i',
    'ó' => 'o',
    'ú' => 'u',
    'ñ' => 'n',
    'Á' => 'A',
    'É' => 'E',
    'Í' => 'I', 
    'Ó' => 'O',
    'Ú' => 'U',
    'Ñ' => 'N',
    'ü' => 'u',
    'Ü' => 'U',
    // Palabras específicas que causan problemas
    'Propósito' => 'Proposito',
    'propósito' => 'proposito',
    'relación' => 'relacion',
    'Relación' => 'Relacion',
    'número' => 'numero',
    'Número' => 'Numero',
    'Teléfono' => 'Telefono',
    'teléfono' => 'telefono',
    'cónyuge' => 'conyuge',
    'Cónyuge' => 'Conyuge',
    'Óvalo' => 'Ovalo',
    'óvalo' => 'ovalo',
    'Ocupación' => 'Ocupacion',
    'ocupación' => 'ocupacion',
    'máxima' => 'maxima',
    'Máxima' => 'Maxima',
    'función' => 'funcion',
    'Función' => 'Funcion',
    'funciones' => 'funciones',
    'Funciones' => 'Funciones',
    'organización' => 'organizacion',
    'Organización' => 'Organizacion',
    'público' => 'publico',
    'Público' => 'Publico',
    'últimos' => 'ultimos',
    'Últimos' => 'Ultimos',
    'años' => 'anos',
    'Años' => 'Anos',
    'según' => 'segun',
    'Según' => 'Segun',
    'marcó' => 'marco',
    'Marcó' => 'Marco',
    'opción' => 'opcion',
    'Opción' => 'Opcion',
    'información' => 'informacion',
    'Información' => 'Informacion',
    'jurídico' => 'juridico',
    'Jurídico' => 'Juridico',
    'jurídica' => 'juridica',
    'Jurídica' => 'Juridica',
    'sí' => 'si',
    'Sí' => 'Si'
];

// Aplicar reemplazos
foreach ($reemplazos as $buscar => $reemplazar) {
    $contenido = str_replace($buscar, $reemplazar, $contenido);
}

// Guardar archivo limpio
file_put_contents($archivo, $contenido);

echo "Archivo limpiado exitosamente. Se realizaron " . count($reemplazos) . " tipos de reemplazos.\n";
echo "Verificando contenido...\n";

// Verificar si quedan caracteres problemáticos
$caracteresProblematicos = ['á', 'é', 'í', 'ó', 'ú', 'ñ', 'Á', 'É', 'Í', 'Ó', 'Ú', 'Ñ'];
$encontrados = [];

foreach ($caracteresProblematicos as $caracter) {
    if (strpos($contenido, $caracter) !== false) {
        $encontrados[] = $caracter;
    }
}

if (empty($encontrados)) {
    echo "✓ No se encontraron caracteres especiales restantes.\n";
} else {
    echo "⚠ Caracteres especiales restantes: " . implode(', ', $encontrados) . "\n";
}

?>
