<?php

$archivo = 'resources/views/pdf/declaracion-jurada.blade.php';
$contenido = file_get_contents($archivo);

// Reemplazos específicos para usar datos limpios
$reemplazos = [
    '$cliente->persona->nombre' => '$clienteLimpio->nombres',
    '$cliente->persona->apellidos' => '$clienteLimpio->apellidos',
    '$cliente->persona->DNI' => '$clienteLimpio->DNI',
    '$cliente->persona->direccion' => '$clienteLimpio->direccion',
    '$cliente->persona->distrito' => '$clienteLimpio->direccion', // Distrito también va en dirección
    '$cliente->persona->celular' => '$clienteLimpio->telefono',
    '$cliente->persona->correo' => '$clienteLimpio->telefono', // Email no se usa realmente
    '$datos[' => '$datosLimpios[',
    "strtoupper(\$cliente->persona->nombre . ' ' . \$cliente->persona->apellidos)" => "strtoupper(\$clienteLimpio->nombres . ' ' . \$clienteLimpio->apellidos)"
];

// Aplicar reemplazos
foreach ($reemplazos as $buscar => $reemplazar) {
    $contenido = str_replace($buscar, $reemplazar, $contenido);
}

// Guardar archivo actualizado
file_put_contents($archivo, $contenido);

echo "Template actualizado para usar datos limpios.\n";
echo "Se realizaron " . count($reemplazos) . " tipos de reemplazos.\n";

?>
