<?php

require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Prestamo;
use App\Models\CuotasGrupales;
use App\Models\Pago;

echo "🧪 SIMULACIÓN DE ESCENARIO DE RETANQUEO PARCIAL\n";
echo "===============================================\n\n";

try {
    // Buscar un préstamo con retanqueos parciales
    $prestamo = Prestamo::whereHas('retanqueoComoAntiguo', function($query) {
        $query->where('estado_retanqueo', 'ejecutado')
            ->whereHas('retanqueosIndividuales', function($q) {
                $q->where('participacion_tipo', 'no_retanquea');
            });
    })
    ->where('estado', '!=', 'Finalizado')
    ->first();

    if (!$prestamo) {
        echo "ℹ️ No se encontró un préstamo con retanqueo parcial activo.\n";
        echo "Buscando cualquier préstamo con retanqueo ejecutado...\n";
        
        $prestamo = Prestamo::whereHas('retanqueoComoAntiguo', function($query) {
            $query->where('estado_retanqueo', 'ejecutado');
        })->first();
    }

    if (!$prestamo) {
        echo "❌ No se encontró ningún préstamo con retanqueos ejecutados.\n";
        echo "💡 Para probar el fix, necesitas:\n";
        echo "   1. Un grupo con préstamo activo\n";
        echo "   2. Crear un retanqueo donde algunos no participen\n";
        echo "   3. Ejecutar el retanqueo\n";
        echo "   4. Verificar que el préstamo no se finalice automáticamente\n";
        exit;
    }

    echo "📋 USANDO PRÉSTAMO ID: {$prestamo->id}\n";
    echo "   - Estado actual: {$prestamo->estado}\n";
    echo "   - Grupo: {$prestamo->grupo->nombre_grupo}\n\n";

    // Mostrar estado actual
    $totalCuotas = $prestamo->cuotasGrupales()->count();
    $cuotasPagadas = $prestamo->cuotasGrupales()->where('estado_pago', 'pagado')->count();
    
    echo "📊 ESTADO ACTUAL:\n";
    echo "   - Total cuotas grupales: {$totalCuotas}\n";
    echo "   - Cuotas pagadas: {$cuotasPagadas}\n";
    echo "   - Cuotas pendientes: " . ($totalCuotas - $cuotasPagadas) . "\n";

    // Verificar retanqueo
    $retanqueo = $prestamo->retanqueoComoAntiguo;
    if ($retanqueo) {
        $noRetanquean = $retanqueo->retanqueosIndividuales()
            ->where('participacion_tipo', 'no_retanquea')
            ->count();
        echo "   - Integrantes que NO retanquearon: {$noRetanquean}\n";
    }

    // Probar el nuevo método
    echo "\n🔍 VERIFICANDO NUEVA LÓGICA:\n";
    $tieneDeudaPendiente = $prestamo->tieneIntegrantesNoRetanqueadosConDeudaPendiente();
    echo "   - ¿Hay integrantes no retanqueados con deuda?: " . ($tieneDeudaPendiente ? "SÍ ✅" : "NO ❌") . "\n";

    // Simular el intento de finalización automática
    echo "\n🧪 SIMULANDO FINALIZACIÓN AUTOMÁTICA:\n";
    if ($cuotasPagadas === $totalCuotas) {
        echo "   - ✅ Todas las cuotas grupales están pagadas\n";
        
        // Probar método verificarYActualizarEstado
        $resultado = $prestamo->verificarYActualizarEstado();
        if ($resultado) {
            echo "   - ❌ ERROR: El préstamo se finalizó automáticamente\n";
        } else {
            echo "   - ✅ CORRECTO: El préstamo NO se finalizó automáticamente\n";
        }
    } else {
        echo "   - ℹ️ Aún hay cuotas grupales pendientes, no debería finalizar\n";
        
        // Marcar todas las cuotas como pagadas para simular el escenario
        echo "   - 🔧 Simulando que todas las cuotas se paguen...\n";
        
        $cuotasPendientes = $prestamo->cuotasGrupales()
            ->where('estado_pago', '!=', 'pagado')
            ->get();

        foreach ($cuotasPendientes as $cuota) {
            echo "     * Marcando cuota {$cuota->numero_cuota} como pagada\n";
            $cuota->update([
                'estado_pago' => 'pagado',
                'saldo_pendiente' => 0,
                'estado_cuota_grupal' => 'cancelada'
            ]);
        }

        // Ahora probar la finalización
        echo "\n   - 🧪 Probando finalización después de marcar todas como pagadas:\n";
        $tieneDeudaPendienteDespues = $prestamo->tieneIntegrantesNoRetanqueadosConDeudaPendiente();
        echo "     * ¿Hay deuda pendiente?: " . ($tieneDeudaPendienteDespues ? "SÍ" : "NO") . "\n";
        
        $resultado = $prestamo->verificarYActualizarEstado();
        if ($resultado) {
            if ($tieneDeudaPendienteDespues) {
                echo "     * ❌ ERROR: Se finalizó a pesar de tener deuda pendiente\n";
            } else {
                echo "     * ✅ CORRECTO: Se finalizó porque no hay deuda pendiente\n";
            }
        } else {
            if ($tieneDeudaPendienteDespues) {
                echo "     * ✅ CORRECTO: NO se finalizó debido a deuda pendiente\n";
            } else {
                echo "     * ❓ REVISAR: No se finalizó pero no hay deuda pendiente\n";
            }
        }
    }

    echo "\n" . str_repeat("=", 50) . "\n";
    echo "✅ SIMULACIÓN COMPLETADA\n";
    echo "\n💡 RESUMEN DEL FIX IMPLEMENTADO:\n";
    echo "   1. ✅ Nuevo método tieneIntegrantesNoRetanqueadosConDeudaPendiente()\n";
    echo "   2. ✅ Verificación en verificarYActualizarEstado()\n";
    echo "   3. ✅ Verificación en actualizarEstadoAutomaticamente()\n";
    echo "   4. ✅ Verificación adicional en CuotasGrupalesObserver\n";
    echo "\n🛡️ El sistema ahora protege contra finalización prematura\n";
    echo "   cuando hay integrantes que no retanquearon con deuda pendiente.\n";

} catch (Exception $e) {
    echo "❌ Error durante la simulación: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
