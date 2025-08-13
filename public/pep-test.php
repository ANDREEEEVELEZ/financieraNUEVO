<?php

// Archivo de prueba para verificar que el editor funciona
// Ubicación: /pep-test

use App\Models\Cliente;

// Buscar un cliente PEP para prueba
$clientePep = Cliente::where('condicion_personal', 'PEP')
    ->where('estado_cliente', 'Activo')
    ->with('persona')
    ->first();

if (!$clientePep) {
    echo "<h1>❌ No hay clientes PEP activos para probar</h1>";
    echo "<p>Necesitas crear un cliente con condición_personal = 'PEP' y estado_cliente = 'Activo'</p>";
    exit;
}

echo "<h1>✅ Cliente PEP encontrado para prueba</h1>";
echo "<h2>Datos del cliente:</h2>";
echo "<ul>";
echo "<li><strong>ID:</strong> {$clientePep->id}</li>";
echo "<li><strong>Nombre:</strong> {$clientePep->persona->nombre} {$clientePep->persona->apellidos}</li>";
echo "<li><strong>DNI:</strong> {$clientePep->persona->DNI}</li>";
echo "<li><strong>Condición:</strong> {$clientePep->condicion_personal}</li>";
echo "<li><strong>Estado:</strong> {$clientePep->estado_cliente}</li>";
echo "</ul>";

echo "<h2>🔗 Enlaces de prueba:</h2>";
echo "<ul>";
echo "<li><a href='/pep-editor/{$clientePep->id}' target='_blank'>🎯 Abrir Editor PEP Interactivo</a></li>";
echo "<li><a href='/dashboard/clientes' target='_blank'>📋 Ir a Lista de Clientes</a></li>";
echo "</ul>";

echo "<h2>📝 Instrucciones:</h2>";
echo "<ol>";
echo "<li>Haz clic en el enlace 'Abrir Editor PEP Interactivo'</li>";
echo "<li>Deberías ver el documento de declaración jurada</li>";
echo "<li>Prueba hacer clic en los campos editables</li>";
echo "<li>Verifica que los checkboxes funcionen</li>";
echo "<li>Prueba generar el PDF final</li>";
echo "</ol>";
?>
