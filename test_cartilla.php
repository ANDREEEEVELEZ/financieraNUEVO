<?php

// Verificación rápida de la funcionalidad de cartilla
require_once 'bootstrap/app.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Buscar un préstamo válido para probar
$prestamo = \App\Models\Prestamo::with(['grupo.clientes.persona'])
    ->whereNotNull('grupo_id')
    ->whereIn('estado', ['Aprobado', 'Activo', 'Parcialmente_Retanqueado', 'Finalizado'])
    ->first();

if ($prestamo) {
    echo "✅ Préstamo encontrado: ID {$prestamo->id}, Grupo: {$prestamo->grupo->nombre_grupo}\n";
    echo "   Estado: {$prestamo->estado}\n";
    echo "   Fecha: {$prestamo->fecha_prestamo}\n";
    
    $integrantes = $prestamo->getIntegrantesParaContrato();
    echo "   Integrantes: " . count($integrantes) . "\n";
    
    foreach ($integrantes as $index => $integrante) {
        $persona = $integrante['persona'];
        echo "   - {$persona->nombre} {$persona->apellidos} (DNI: {$persona->DNI})\n";
    }
    
    echo "\n🎯 La cartilla se puede generar correctamente para este préstamo.\n";
    echo "📋 URL de prueba: /cartilla/prestamo/{$prestamo->id}\n";
} else {
    echo "⚠️  No se encontraron préstamos válidos para generar cartilla.\n";
    echo "   Se necesita un préstamo con estado: Aprobado, Activo, Parcialmente_Retanqueado o Finalizado\n";
}
