<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$user = \App\Models\User::first();
echo "Usuario: " . $user->name . PHP_EOL;
echo "Roles: " . $user->getRoleNames()->implode(', ') . PHP_EOL;

if($user->hasRole('Asesor')) {
    $asesor = \App\Models\Asesor::where('user_id', $user->id)->first();
    echo "Asesor ID: " . ($asesor ? $asesor->id : 'No encontrado') . PHP_EOL;
}

echo "Puede ver todo: " . ($user->hasAnyRole(['super_admin', 'Jefe de operaciones', 'Jefe de creditos']) ? 'Sí' : 'No') . PHP_EOL;

// Test recent activity
$canViewAllActivity = $user->hasAnyRole(['super_admin', 'Jefe de operaciones', 'Jefe de creditos']);
$asesorId = null;
if ($user->hasRole('Asesor')) {
    $asesor = \App\Models\Asesor::where('user_id', $user->id)->first();
    $asesorId = $asesor ? $asesor->id : null;
}

echo PHP_EOL . "=== TESTING RECENT ACTIVITY ===" . PHP_EOL;
echo "Can view all activity: " . ($canViewAllActivity ? 'YES' : 'NO') . PHP_EOL;
echo "Asesor ID: " . ($asesorId ?? 'NULL') . PHP_EOL;

// Test clients
$clientesQuery = \App\Models\Cliente::with('persona');
if (!$canViewAllActivity && $asesorId) {
    $clientesQuery->where('asesor_id', $asesorId);
}
$clientes = $clientesQuery->get();
echo "Clientes encontrados: " . $clientes->count() . PHP_EOL;

// Test groups
$gruposQuery = \App\Models\Grupo::query();
if (!$canViewAllActivity && $asesorId) {
    $gruposQuery->where('asesor_id', $asesorId);
}
$grupos = $gruposQuery->get();
echo "Grupos encontrados: " . $grupos->count() . PHP_EOL;

// Test recent clients
$clientesRecientes = $clientes->sortByDesc('created_at')->take(3);
echo PHP_EOL . "Clientes recientes:" . PHP_EOL;
foreach ($clientesRecientes as $cliente) {
    $nombre = optional($cliente->persona)->nombre . ' ' . optional($cliente->persona)->apellidos;
    echo "- {$nombre} (creado: {$cliente->created_at})" . PHP_EOL;
}
