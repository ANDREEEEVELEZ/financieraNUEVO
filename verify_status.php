<?php

require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Prestamo;
use App\Models\Retanqueo;

try {
    echo "🔍 VERIFICANDO ESTADO DEL PRÉSTAMO ORIGINAL DESPUÉS DEL RETANQUEO:\n\n";
    
    $retanqueo = Retanqueo::find(3);
    
    if ($retanqueo) {
        $prestamoOriginal = $retanqueo->prestamoAntiguo;
        $prestamoNuevo = $retanqueo->prestamoNuevo;
        
        echo "📊 RETANQUEO ID: {$retanqueo->id}\n";
        echo "   - Estado retanqueo: {$retanqueo->estado_retanqueo}\n";
        echo "   - Prestamo antiguo estado flag: {$retanqueo->prestamo_antiguo_estado}\n\n";
        
        echo "💰 PRÉSTAMO ORIGINAL (ID: {$prestamoOriginal->id}):\n";
        echo "   - Estado: {$prestamoOriginal->estado}\n";
        echo "   - Monto original: S/ " . number_format($prestamoOriginal->monto_prestado_total, 2) . "\n";
        
        // Verificar participantes
        $participantes = $retanqueo->retanqueosIndividuales;
        $queRetanquean = $participantes->whereIn('participacion_tipo', ['retanquea', 'nueva'])->count();
        $totalParticipantes = $participantes->count();
        
        echo "   - Participantes que retanquearon: {$queRetanquean} de {$totalParticipantes}\n";
        
        // Verificar cuotas pendientes
        $cuotasPendientes = $prestamoOriginal->cuotasGrupales()
            ->where('estado_pago', '!=', 'pagado')
            ->where('saldo_pendiente', '>', 0)
            ->count();
        
        echo "   - Cuotas pendientes: {$cuotasPendientes}\n";
        
        if ($queRetanquean < $totalParticipantes && $cuotasPendientes > 0) {
            echo "   - ✅ Correcto: Debería estar en 'Parcialmente_Retanqueado'\n";
        } elseif ($cuotasPendientes === 0) {
            echo "   - ✅ Correcto: Debería estar 'Finalizado'\n";
        }
        
        echo "\n🆕 PRÉSTAMO NUEVO (ID: {$prestamoNuevo->id}):\n";
        echo "   - Descripción: {$prestamoNuevo->descripcion}\n";
        echo "   - Estado: {$prestamoNuevo->estado}\n";
        echo "   - Monto: S/ " . number_format($prestamoNuevo->monto_prestado_total, 2) . "\n";
        
    } else {
        echo "❌ Retanqueo no encontrado\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
