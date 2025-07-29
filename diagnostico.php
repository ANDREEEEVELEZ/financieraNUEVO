<?php

use App\Models\Prestamo;
use App\Models\Pago;
use App\Models\Mora;

echo "=== DIAGNÓSTICO DE NOTIFICACIONES ===\n\n";

// Verificar préstamos
echo "PRÉSTAMOS:\n";
$prestamos = Prestamo::select('estado')
    ->selectRaw('count(*) as total')
    ->groupBy('estado')
    ->get();

foreach ($prestamos as $prestamo) {
    echo "- {$prestamo->estado}: {$prestamo->total} préstamos\n";
}

echo "\nPréstamos recientes (últimos 5):\n";
$prestamosRecientes = Prestamo::orderBy('created_at', 'desc')->take(5)->get();
foreach ($prestamosRecientes as $prestamo) {
    echo "- ID {$prestamo->id}: Estado '{$prestamo->estado}' - Creado: {$prestamo->created_at}\n";
}

// Verificar pagos
echo "\n\nPAGOS:\n";
$pagos = Pago::select('estado_pago')
    ->selectRaw('count(*) as total')
    ->groupBy('estado_pago')
    ->get();

foreach ($pagos as $pago) {
    echo "- {$pago->estado_pago}: {$pago->total} pagos\n";
}

// Verificar moras
echo "\n\nMORAS:\n";
$moras = Mora::select('estado_mora')
    ->selectRaw('count(*) as total')
    ->groupBy('estado_mora')
    ->get();

foreach ($moras as $mora) {
    echo "- {$mora->estado_mora}: {$mora->total} moras\n";
}

echo "\n=== FIN DIAGNÓSTICO ===\n";
