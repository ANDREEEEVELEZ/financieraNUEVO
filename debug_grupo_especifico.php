<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->boot();

use App\Models\Grupo;
use App\Models\Prestamo;
use App\Models\CuotasGrupales;

echo "=== DEBUGGING ESPECÍFICO PARA RETANQUEO ===\n\n";

// Buscar el grupo específico
$grupo = Grupo::where('nombre_grupo', 'PRUEBA INCREMENTO CICLO')->first();

if (!$grupo) {
    echo "❌ Grupo 'PRUEBA INCREMENTO CICLO' no encontrado\n";
    exit;
}

echo "✅ Grupo encontrado: {$grupo->nombre_grupo} (ID: {$grupo->id})\n\n";

// Buscar todos los préstamos del grupo
$prestamos = $grupo->prestamos()->get();
echo "📋 Total préstamos del grupo: {$prestamos->count()}\n\n";

foreach ($prestamos as $prestamo) {
    echo "📄 Préstamo ID: {$prestamo->id}\n";
    echo "   Estado: {$prestamo->estado}\n";
    echo "   Es retanqueo: " . ($prestamo->es_retanqueo ? 'Sí' : 'No') . "\n";
    echo "   Descripción: {$prestamo->descripcion}\n";
    
    // Si es parcialmente retanqueado, ver las cuotas
    if ($prestamo->estado === 'Parcialmente_Retanqueado') {
        echo "\n   🔍 ANÁLISIS DE CUOTAS (Préstamo Parcialmente Retanqueado):\n";
        
        $todasLasCuotas = $prestamo->cuotasGrupales()->orderBy('numero_cuota')->get();
        echo "   Total cuotas: {$todasLasCuotas->count()}\n";
        
        foreach ($todasLasCuotas as $cuota) {
            $saldo = $cuota->saldoPendiente();
            echo "   Cuota {$cuota->numero_cuota}:\n";
            echo "     - Estado cuota: {$cuota->estado_cuota_grupal}\n";
            echo "     - Estado pago: {$cuota->estado_pago}\n";
            echo "     - Monto cuota: S/ {$cuota->monto_cuota_grupal}\n";
            echo "     - Saldo pendiente: S/ {$cuota->saldo_pendiente}\n";
            echo "     - Saldo calculado: S/ {$saldo}\n";
            echo "     - Tiene mora: " . ($cuota->mora ? 'Sí (S/ ' . abs($cuota->mora->monto_mora_calculado) . ')' : 'No') . "\n";
            
            // Verificar pagos de esta cuota
            $pagos = $cuota->pagos()->where('estado_pago', 'Aprobado')->get();
            if ($pagos->count() > 0) {
                echo "     - Pagos aprobados: {$pagos->count()}\n";
                foreach ($pagos as $pago) {
                    echo "       * Pago ID {$pago->id}: S/ {$pago->monto_pagado}\n";
                }
            }
            
            if ($saldo > 0) {
                echo "     ✅ CUOTA ELEGIBLE PARA PAGO\n";
            }
            echo "\n";
        }
        
        // Simular la lógica del formulario
        echo "   🎯 SIMULANDO LÓGICA DEL FORMULARIO:\n";
        $cuotasElegibles = $todasLasCuotas->filter(function ($cuota) {
            return $cuota->estado_pago !== 'pagado' && $cuota->saldoPendiente() > 0;
        });
        
        echo "   Cuotas elegibles: {$cuotasElegibles->count()}\n";
        if ($cuotasElegibles->count() > 0) {
            $primeraCuota = $cuotasElegibles->first();
            echo "   Primera cuota a mostrar en formulario: {$primeraCuota->numero_cuota}\n";
            echo "   Monto que debería aparecer: S/ {$primeraCuota->monto_cuota_grupal}\n";
            echo "   Saldo que debería aparecer: S/ {$primeraCuota->saldoPendiente()}\n";
        }
    }
    echo "\n" . str_repeat("-", 50) . "\n\n";
}

echo "=== FIN DEL ANÁLISIS ===\n";
