<?php

require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Prestamo;
use App\Models\Grupo;

try {
    echo "📋 PRÉSTAMOS DEL GRUPO 'LAS EMPRENDEDORAS':\n\n";
    
    $grupo = Grupo::where('nombre_grupo', 'LAS EMPRENDEDORAS')->first();
    
    if ($grupo) {
        $prestamos = Prestamo::where('grupo_id', $grupo->id)
            ->orderBy('created_at', 'desc')
            ->get();
        
        foreach ($prestamos as $prestamo) {
            echo "💰 Préstamo ID: {$prestamo->id}\n";
            echo "   - Descripción: " . ($prestamo->descripcion ?? 'Sin descripción') . "\n";
            echo "   - Es retanqueo: " . ($prestamo->es_retanqueo ? '✅ Sí' : '❌ No') . "\n";
            echo "   - Estado: {$prestamo->estado}\n";
            echo "   - Monto: S/ " . number_format($prestamo->monto_prestado_total, 2) . "\n";
            echo "   - Fecha: {$prestamo->created_at->format('d/m/Y H:i')}\n";
            
            // Como aparecería en la tabla
            if ($prestamo->es_retanqueo && $prestamo->descripcion) {
                echo "   - En tabla aparece como: '{$prestamo->descripcion}'\n";
            } else {
                echo "   - En tabla aparece como: '{$grupo->nombre_grupo}'\n";
            }
            echo "\n";
        }
    } else {
        echo "❌ Grupo no encontrado\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
