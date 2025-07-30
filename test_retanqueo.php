<?php

require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\RetanqueoService;
use App\Models\Retanqueo;

try {
    $service = new RetanqueoService();
    $retanqueo = Retanqueo::find(3);
    
    if ($retanqueo) {
        echo "🔄 Aprobando retanqueo 3...\n";
        $service->aprobarRetanqueo(3);
        echo "✅ Retanqueo 3 aprobado\n";
        
        echo "🚀 Ejecutando retanqueo 3...\n";
        $service->ejecutarRetanqueo(3);
        echo "✅ Retanqueo 3 ejecutado\n";
        
        $retanqueoFresh = $retanqueo->fresh();
        $nuevo = $retanqueoFresh->prestamoNuevo;
        
        if ($nuevo) {
            echo "📋 Nuevo préstamo creado:\n";
            echo "   - ID: {$nuevo->id}\n";
            echo "   - Descripción: {$nuevo->descripcion}\n";
            echo "   - Es retanqueo: " . ($nuevo->es_retanqueo ? 'Sí' : 'No') . "\n";
            echo "   - Grupo: {$nuevo->grupo->nombre_grupo}\n";
        } else {
            echo "❌ No se encontró el nuevo préstamo\n";
        }
    } else {
        echo "❌ Retanqueo no encontrado\n";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
