<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Prestamo;
use App\Models\Grupo;

class TestNotificationSeeder extends Seeder
{
    public function run()
    {
        // Obtener el primer grupo disponible
        $grupo = Grupo::first();
        
        if (!$grupo) {
            echo "No hay grupos disponibles para crear préstamo de prueba\n";
            return;
        }
        
        // Crear un préstamo en estado Pendiente
        $prestamo = Prestamo::create([
            'grupo_id' => $grupo->id,
            'estado' => 'Pendiente',
            'monto_prestado_total' => 1000.00,
            'monto_devolver' => 1100.00,
            'tasa_interes' => 10,
            'cantidad_cuotas' => 10,
            'fecha_prestamo' => now(),
            'frecuencia' => 'semanal',
            'calificacion' => 'A',
        ]);
        
        echo "Préstamo de prueba creado:\n";
        echo "- ID: {$prestamo->id}\n";
        echo "- Grupo: {$grupo->nombre_grupo}\n";
        echo "- Estado: {$prestamo->estado}\n";
        echo "- Monto: S/ {$prestamo->monto_prestado_total}\n";
        echo "- Fecha: {$prestamo->created_at}\n";
    }
}
