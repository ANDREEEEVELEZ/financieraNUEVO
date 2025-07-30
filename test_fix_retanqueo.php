<?php

require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Prestamo;
use App\Models\Retanqueo;
use App\Models\CuotasGrupales;
use App\Models\Pago;

echo "🔧 SCRIPT DE PRUEBA - FIX RETANQUEO PARCIAL\n";
echo "==========================================\n\n";

try {
    // Buscar un préstamo que tenga retanqueos ejecutados
    $prestamo = Prestamo::whereHas('retanqueoComoAntiguo', function($query) {
        $query->where('estado_retanqueo', 'ejecutado');
    })->first();

    if (!$prestamo) {
        echo "❌ No se encontró un préstamo con retanqueos ejecutados para probar\n";
        exit;
    }

    echo "📋 INFORMACIÓN DEL PRÉSTAMO:\n";
    echo "   - ID: {$prestamo->id}\n";
    echo "   - Estado actual: {$prestamo->estado}\n";
    echo "   - Grupo: {$prestamo->grupo->nombre_grupo}\n";

    // Verificar cuotas
    $totalCuotas = $prestamo->cuotasGrupales()->count();
    $cuotasPagadas = $prestamo->cuotasGrupales()->where('estado_pago', 'pagado')->count();
    
    echo "\n📊 ESTADO DE CUOTAS:\n";
    echo "   - Total cuotas: {$totalCuotas}\n";
    echo "   - Cuotas pagadas: {$cuotasPagadas}\n";
    echo "   - Cuotas pendientes: " . ($totalCuotas - $cuotasPagadas) . "\n";

    // Verificar retanqueos
    $retanqueos = $prestamo->retanqueoComoAntiguo;
    if ($retanqueos) {
        echo "\n🔄 INFORMACIÓN DEL RETANQUEO:\n";
        echo "   - ID Retanqueo: {$retanqueos->id}\n";
        echo "   - Estado: {$retanqueos->estado_retanqueo}\n";
        
        $integrantesTotal = $retanqueos->retanqueosIndividuales()->count();
        $integrantesQueRetanquean = $retanqueos->retanqueosIndividuales()
            ->whereIn('participacion_tipo', ['retanquea', 'nueva'])->count();
        $integrantesNoRetanquean = $retanqueos->retanqueosIndividuales()
            ->where('participacion_tipo', 'no_retanquea')->count();
            
        echo "   - Total integrantes: {$integrantesTotal}\n";
        echo "   - Retanquean: {$integrantesQueRetanquean}\n";
        echo "   - NO retanquean: {$integrantesNoRetanquean}\n";
    }

    // Probar el nuevo método
    echo "\n🧪 PROBANDO NUEVA LÓGICA:\n";
    $tieneDeudaPendiente = $prestamo->tieneIntegrantesNoRetanqueadosConDeudaPendiente();
    echo "   - ¿Tiene integrantes no retanqueados con deuda?: " . ($tieneDeudaPendiente ? "SÍ" : "NO") . "\n";

    // Simular verificación de estado
    echo "\n🔍 SIMULANDO VERIFICACIÓN DE ESTADO:\n";
    if ($totalCuotas > 0 && $cuotasPagadas === $totalCuotas) {
        echo "   - Todas las cuotas están pagadas\n";
        if ($tieneDeudaPendiente) {
            echo "   - ✅ CORRECTO: No se finalizará automáticamente (hay deuda pendiente)\n";
        } else {
            echo "   - ✅ CORRECTO: Se puede finalizar (no hay deuda pendiente)\n";
        }
    } else {
        echo "   - Aún hay cuotas pendientes, no se debe finalizar\n";
    }

    // Mostrar detalles de integrantes que no retanquearon
    if ($retanqueos && $integrantesNoRetanquean > 0) {
        echo "\n👥 DETALLES DE INTEGRANTES QUE NO RETANQUEARON:\n";
        $noRetanqueados = $retanqueos->retanqueosIndividuales()
            ->where('participacion_tipo', 'no_retanquea')
            ->with('cliente.persona')
            ->get();

        foreach ($noRetanqueados as $individual) {
            $cliente = $individual->cliente;
            $persona = $cliente->persona;
            $sigueEnGrupo = $prestamo->grupo->clientes()->where('clientes.id', $cliente->id)->exists();
            
            $prestamoIndividual = $prestamo->prestamoIndividual()
                ->where('cliente_id', $cliente->id)
                ->first();

            echo "   - {$persona->nombre} {$persona->apellidos}:\n";
            echo "     * ¿Sigue en grupo?: " . ($sigueEnGrupo ? "SÍ" : "NO") . "\n";
            if ($prestamoIndividual) {
                echo "     * Estado préstamo individual: {$prestamoIndividual->estado}\n";
                echo "     * Monto individual: S/ " . number_format((float)$prestamoIndividual->monto_prestado_individual, 2) . "\n";
                echo "     * Saldo pendiente: S/ " . number_format((float)$prestamoIndividual->monto_devolver_individual, 2) . "\n";
            } else {
                echo "     * Sin préstamo individual registrado\n";
            }
        }
    }

    echo "\n✅ PRUEBA COMPLETADA\n";

} catch (Exception $e) {
    echo "❌ Error durante la prueba: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
