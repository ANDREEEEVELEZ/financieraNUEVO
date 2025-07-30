<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Esta migración es principalmente para documentar el nuevo estado
        // No necesitamos cambiar la estructura de la tabla
        // El campo 'estado' ya existe y puede contener el valor 'Parcialmente_Retanqueado'
        
        // Actualizar algunos préstamos para mostrar el nuevo estado usando una subconsulta válida
        DB::statement("
            UPDATE prestamos 
            SET estado = 'Parcialmente_Retanqueado' 
            WHERE estado = 'Aprobado' 
            AND id IN (
                SELECT prestamo_id 
                FROM retanqueos
                WHERE estado_retanqueo = 'ejecutado'
                AND prestamo_antiguo_estado = 0
            )
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revertir préstamos que tenían el estado Parcialmente_Retanqueado a Aprobado
        DB::statement("
            UPDATE prestamos 
            SET estado = 'Aprobado' 
            WHERE estado = 'Parcialmente_Retanqueado'
        ");
    }
};
