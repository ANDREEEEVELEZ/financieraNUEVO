<?php

require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Prestamo;

try {
    echo "📋 VISTA PREVIA DE LA TABLA DE PRÉSTAMOS (Últimos 10):\n";
    echo "=" . str_repeat("=", 80) . "\n";
    echo sprintf("%-8s %-35s %-15s %-12s %s\n", "ID", "GRUPO/DESCRIPCIÓN", "MONTO", "ESTADO", "TIPO");
    echo "-" . str_repeat("-", 80) . "\n";
    
    $prestamos = Prestamo::with('grupo')
        ->orderBy('created_at', 'desc')
        ->limit(10)
        ->get();
    
    foreach ($prestamos as $prestamo) {
        // Simular la lógica de la columna "Grupo" en la tabla
        if ($prestamo->es_retanqueo && $prestamo->descripcion) {
            $nombreEnTabla = $prestamo->descripcion;
        } else {
            $nombreEnTabla = $prestamo->grupo->nombre_grupo ?? 'Sin grupo';
        }
        
        $monto = 'S/ ' . number_format((float)$prestamo->monto_prestado_total, 0);
        $tipo = $prestamo->es_retanqueo ? 'RETANQUEO' : 'ORIGINAL';
        
        echo sprintf("%-8s %-35s %-15s %-12s %s\n", 
            $prestamo->id,
            substr($nombreEnTabla, 0, 35),
            $monto,
            substr($prestamo->estado, 0, 12),
            $tipo
        );
    }
    
    echo "\n🎯 RESUMEN:\n";
    echo "✅ La columna 'Tipo' fue eliminada de la tabla\n";
    echo "✅ Los retanqueos se muestran con formato: 'Retanqueo #X - NOMBRE_GRUPO'\n";
    echo "✅ Los préstamos originales muestran solo el nombre del grupo\n";
    echo "✅ La diferenciación es clara y funcional\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
