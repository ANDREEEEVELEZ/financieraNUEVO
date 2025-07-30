<?php

require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\RetanqueoService;
use App\Models\Retanqueo;

try {
    $service = new RetanqueoService();
    $retanqueo = Retanqueo::find(4);
    
    if ($retanqueo) {
        echo "🔄 Aprobando retanqueo 4...\n";
        $service->aprobarRetanqueo(4);
        echo "✅ Retanqueo 4 aprobado\n";
        
        echo "🚀 Ejecutando retanqueo 4...\n";
        $service->ejecutarRetanqueo(4);
        echo "✅ Retanqueo 4 ejecutado\n";
        
        $retanqueoFresh = $retanqueo->fresh();
        $prestamoOriginal = $retanqueoFresh->prestamoAntiguo;
        $nuevo = $retanqueoFresh->prestamoNuevo;
        
        echo "\n📋 RESULTADO:\n";
        echo "   - Préstamo original (ID: {$prestamoOriginal->id}): {$prestamoOriginal->estado}\n";
        
        if ($nuevo) {
            echo "   - Nuevo préstamo (ID: {$nuevo->id}): {$nuevo->descripcion}\n";
        }
        
        // Verificar cuotas pendientes del original
        $cuotasPendientes = $prestamoOriginal->cuotasGrupales()
            ->where('estado_pago', '!=', 'pagado')
            ->where('saldo_pendiente', '>', 0)
            ->count();
        
        echo "   - Cuotas pendientes en préstamo original: {$cuotasPendientes}\n";
        
        if ($prestamoOriginal->estado === 'Parcialmente_Retanqueado') {
            echo "🎯 ✅ Perfecto! El préstamo original quedó en estado 'Parcialmente_Retanqueado'\n";
        } else {
            echo "❌ El préstamo original no quedó en el estado esperado\n";
        }
        
    } else {
        echo "❌ Retanqueo no encontrado\n";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
