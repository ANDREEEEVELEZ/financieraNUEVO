<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->boot();

echo "=== SIMULACIÓN DE RETANQUEO PROPORCIONAL ===\n\n";

// Simulación con datos de ejemplo
$totalIntegrantes = 3;
$integrantesQueRetanquean = 2; // 2 de 3 retanquean
$porcentajeCobertura = $integrantesQueRetanquean / $totalIntegrantes;

echo "📊 CONFIGURACIÓN:\n";
echo "Total integrantes: {$totalIntegrantes}\n";
echo "Integrantes que retanquean: {$integrantesQueRetanquean}\n";
echo "Porcentaje de cobertura: " . round($porcentajeCobertura * 100, 2) . "%\n\n";

// Simular cuotas pendientes
$cuotasSimuladas = [
    ['numero' => 18, 'saldo_actual' => 900.00],
    ['numero' => 19, 'saldo_actual' => 900.00],
    ['numero' => 20, 'saldo_actual' => 900.00]
];

$montoDisponibleParaCubrir = 1800.00; // Ejemplo: monto del retanqueo para cubrir deuda antigua

echo "💰 MONTO DISPONIBLE PARA COBERTURA: S/ " . number_format($montoDisponibleParaCubrir, 2) . "\n\n";

echo "🔄 PROCESANDO CUOTAS:\n";
echo str_repeat("-", 80) . "\n";

$montoRestante = $montoDisponibleParaCubrir;

foreach ($cuotasSimuladas as $cuota) {
    if ($montoRestante <= 0) break;
    
    $saldoActual = $cuota['saldo_actual'];
    
    // Calcular cuánto corresponde cubrir de esta cuota (proporcionalmente)
    $montoProporcionalACubrir = round($saldoActual * $porcentajeCobertura, 2);
    
    // No cubrir más de lo que tenemos disponible
    $montoACubrir = min($montoProporcionalACubrir, $montoRestante);
    
    $nuevoSaldo = round($saldoActual - $montoACubrir, 2);
    
    echo "Cuota {$cuota['numero']}:\n";
    echo "  Saldo original: S/ " . number_format($saldoActual, 2) . "\n";
    echo "  Cobertura proporcional ({$porcentajeCobertura}): S/ " . number_format($montoProporcionalACubrir, 2) . "\n";
    echo "  Monto a cubrir: S/ " . number_format($montoACubrir, 2) . "\n";
    echo "  Nuevo saldo: S/ " . number_format($nuevoSaldo, 2) . "\n";
    echo "  Estado: " . ($nuevoSaldo <= 0 ? "PAGADO COMPLETAMENTE" : "SALDO PENDIENTE PARA CLIENTE QUE NO RETANQUEÓ") . "\n";
    echo "\n";
    
    $montoRestante -= $montoACubrir;
}

echo str_repeat("-", 80) . "\n";
echo "💡 RESULTADO:\n";
echo "- Los clientes que retanquearon cubrieron su parte proporcional\n";
echo "- El cliente que NO retanqueó debe pagar el saldo restante de cada cuota\n";
echo "- El formulario de pagos ahora DEBERÍA mostrar los saldos pendientes correctos\n\n";

echo "🎯 PARA PROBAR:\n";
echo "1. Ejecuta un nuevo retanqueo con esta lógica\n";
echo "2. Ve al formulario de pagos\n";
echo "3. Selecciona el grupo parcialmente retanqueado\n";
echo "4. Debería mostrar las cuotas con saldo pendiente para el cliente que no retanqueó\n";
