<?php

require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Prestamo;
use App\Models\Retanqueo;
use App\Models\CuotasGrupales;
use App\Models\PrestamoIndividual;

echo "🔍 BUSCANDO CASOS PROBLEMÁTICOS DE RETANQUEO\n";
echo "=============================================\n\n";

try {
    // Buscar préstamos que tienen retanqueos ejecutados pero no están finalizados
    $prestamos = Prestamo::whereHas('retanqueoComoAntiguo', function($query) {
        $query->where('estado_retanqueo', 'ejecutado');
    })
    ->where('estado', '!=', 'Finalizado')
    ->with(['retanqueoComoAntiguo.retanqueosIndividuales.cliente.persona', 'grupo', 'cuotasGrupales', 'prestamoIndividual'])
    ->get();

    echo "📊 PRÉSTAMOS ENCONTRADOS: " . $prestamos->count() . "\n\n";

    foreach ($prestamos as $prestamo) {
        echo "=" . str_repeat("=", 50) . "\n";
        echo "📋 PRÉSTAMO ID: {$prestamo->id}\n";
        echo "   - Estado: {$prestamo->estado}\n";
        echo "   - Grupo: {$prestamo->grupo->nombre_grupo}\n";

        // Analizar cuotas
        $totalCuotas = $prestamo->cuotasGrupales->count();
        $cuotasPagadas = $prestamo->cuotasGrupales->where('estado_pago', 'pagado')->count();
        $saldoPendienteCuotas = $prestamo->cuotasGrupales->sum('saldo_pendiente');
        
        echo "\n📊 CUOTAS GRUPALES:\n";
        echo "   - Total: {$totalCuotas}\n";
        echo "   - Pagadas: {$cuotasPagadas}\n";
        echo "   - Pendientes: " . ($totalCuotas - $cuotasPagadas) . "\n";
        echo "   - Saldo pendiente total: S/ " . number_format($saldoPendienteCuotas, 2) . "\n";

        // Analizar retanqueo
        $retanqueo = $prestamo->retanqueoComoAntiguo;
        if ($retanqueo) {
            $totalIntegrantes = $retanqueo->retanqueosIndividuales->count();
            $queRetanquean = $retanqueo->retanqueosIndividuales->whereIn('participacion_tipo', ['retanquea', 'nueva'])->count();
            $noRetanquean = $retanqueo->retanqueosIndividuales->where('participacion_tipo', 'no_retanquea')->count();
            
            echo "\n🔄 RETANQUEO:\n";
            echo "   - Total integrantes: {$totalIntegrantes}\n";
            echo "   - Retanquean: {$queRetanquean}\n";
            echo "   - NO retanquean: {$noRetanquean}\n";
            echo "   - Monto usado cobertura: S/ " . number_format((float)$retanqueo->monto_usado_para_cubrir_antiguo, 2) . "\n";
            echo "   - Saldo restante: S/ " . number_format((float)$retanqueo->saldo_restante_prestamo_antiguo, 2) . "\n";
        }

        // Analizar préstamos individuales
        echo "\n👥 PRÉSTAMOS INDIVIDUALES:\n";
        foreach ($prestamo->prestamoIndividual as $individual) {
            $cliente = $individual->cliente;
            $persona = $cliente->persona;
            echo "   - {$persona->nombre} {$persona->apellidos}:\n";
            echo "     * Estado: {$individual->estado}\n";
            echo "     * Monto: S/ " . number_format((float)$individual->monto_prestado_individual, 2) . "\n";
            echo "     * A devolver: S/ " . number_format((float)$individual->monto_devolver_individual, 2) . "\n";
            
            // Verificar si este cliente no retanqueó
            if ($retanqueo) {
                $retanqueoIndividual = $retanqueo->retanqueosIndividuales
                    ->where('cliente_id', $cliente->id)->first();
                if ($retanqueoIndividual) {
                    echo "     * Participación en retanqueo: {$retanqueoIndividual->participacion_tipo}\n";
                }
            }
        }

        // Probar nueva lógica
        echo "\n🧪 NUEVA LÓGICA:\n";
        $tieneDeudaPendiente = $prestamo->tieneIntegrantesNoRetanqueadosConDeudaPendiente();
        echo "   - ¿Tiene integrantes no retanqueados con deuda?: " . ($tieneDeudaPendiente ? "SÍ" : "NO") . "\n";
        
        // Verificar si debería finalizar
        if ($cuotasPagadas === $totalCuotas) {
            echo "   - ⚠️ ALERTA: Todas las cuotas grupales están pagadas\n";
            if ($tieneDeudaPendiente) {
                echo "   - ✅ PROTECCIÓN ACTIVA: No se finalizará automáticamente\n";
            } else {
                echo "   - ✅ PUEDE FINALIZAR: No hay deuda pendiente individual\n";
            }
        }

        echo "\n";
    }

    // Buscar casos donde ya se finalizó incorrectamente
    echo "\n🚨 CASOS YA FINALIZADOS INCORRECTAMENTE:\n";
    echo "==========================================\n";
    
    $finalizadosIncorrectos = Prestamo::whereHas('retanqueoComoAntiguo', function($query) {
        $query->where('estado_retanqueo', 'ejecutado')
            ->whereHas('retanqueosIndividuales', function($q) {
                $q->where('participacion_tipo', 'no_retanquea');
            });
    })
    ->where('estado', 'Finalizado')
    ->whereHas('prestamoIndividual', function($query) {
        $query->where('estado', '!=', 'Finalizado');
    })
    ->with(['retanqueoComoAntiguo.retanqueosIndividuales.cliente.persona', 'grupo', 'prestamoIndividual'])
    ->get();

    echo "📊 FINALIZADOS INCORRECTAMENTE: " . $finalizadosIncorrectos->count() . "\n\n";

    foreach ($finalizadosIncorrectos as $prestamo) {
        echo "❌ PRÉSTAMO ID: {$prestamo->id} - {$prestamo->grupo->nombre_grupo}\n";
        
        $individualesNoFinalizados = $prestamo->prestamoIndividual->where('estado', '!=', 'Finalizado');
        foreach ($individualesNoFinalizados as $individual) {
            $cliente = $individual->cliente;
            $persona = $cliente->persona;
            echo "   - {$persona->nombre}: Estado individual '{$individual->estado}'\n";
        }
        echo "\n";
    }

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
