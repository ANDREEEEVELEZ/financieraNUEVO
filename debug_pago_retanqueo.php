<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->boot();

use App\Models\Grupo;
use App\Models\Prestamo;

echo "=== DEBUGGING PAGO RETANQUEO ===\n\n";

// Buscar grupos con préstamos parcialmente retanqueados
$gruposRetanqueados = Grupo::whereHas('prestamos', function($query) {
    $query->where('estado', 'Parcialmente_Retanqueado');
})->with(['prestamos' => function($query) {
    $query->where('estado', 'Parcialmente_Retanqueado');
}])->get();

echo "Grupos con préstamos parcialmente retanqueados: " . $gruposRetanqueados->count() . "\n\n";

foreach ($gruposRetanqueados as $grupo) {
    echo "Grupo: {$grupo->nombre_grupo}\n";
    
    foreach ($grupo->prestamos as $prestamo) {
        if ($prestamo->estado === 'Parcialmente_Retanqueado') {
            echo "  Préstamo ID: {$prestamo->id} - Estado: {$prestamo->estado}\n";
            
            // Verificar cuotas
            $cuotas = $prestamo->cuotasGrupales()->where('estado_pago', '!=', 'pagado')->get();
            echo "  Total cuotas no pagadas: {$cuotas->count()}\n";
            
            foreach ($cuotas as $cuota) {
                $saldo = $cuota->saldoPendiente();
                if ($saldo > 0) {
                    echo "    Cuota {$cuota->numero_cuota} - Estado: {$cuota->estado_cuota_grupal} - Estado Pago: {$cuota->estado_pago} - Saldo: S/ {$saldo}\n";
                }
            }
            echo "\n";
        }
    }
}

// Si no hay datos locales, mostrar estructura esperada
if ($gruposRetanqueados->count() == 0) {
    echo "No hay datos locales. La corrección aplicada funciona así:\n\n";
    echo "ANTES (problemático):\n";
    echo "- Solo buscaba cuotas con estado 'vigente' o 'mora'\n";
    echo "- En retanqueos, algunas cuotas tienen estado 'cancelada' pero saldo pendiente\n\n";
    
    echo "DESPUÉS (corregido):\n";
    echo "- Para préstamos 'Parcialmente_Retanqueado': busca TODAS las cuotas con saldo pendiente\n";
    echo "- Para préstamos normales: mantiene lógica original\n";
    echo "- Esto permite que los formularios de pago muestren datos correctos\n";
}
