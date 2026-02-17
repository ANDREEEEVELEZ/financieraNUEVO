<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Actualiza el enum de estados para agregar 'Por Desembolsar' y 'Desembolsado'.
     * Migra préstamos existentes con cuotas de 'Aprobado' a 'Desembolsado'.
     */
    public function up(): void
    {
        // Paso 1: Migrar préstamos "Aprobado" con cuotas a "Desembolsado"
        DB::statement("
            UPDATE prestamos p
            SET estado = 'Desembolsado'
            WHERE estado = 'Aprobado'
            AND EXISTS (
                SELECT 1 FROM cuota_individual ci
                WHERE ci.prestamo_id = p.id
                LIMIT 1
            )
        ");

        // Paso 2: Actualizar el enum con los nuevos estados
        DB::statement("
            ALTER TABLE prestamos 
            MODIFY COLUMN estado ENUM(
                'Pendiente',
                'Aprobado',
                'Por Desembolsar',
                'Desembolsado',
                'Ejecutado',
                'Activo',
                'Rechazado',
                'Finalizado',
                'Parcialmente_Retanqueado'
            ) NOT NULL DEFAULT 'Pendiente'
        ");

        // Paso 3: Los préstamos "Aprobado" SIN cuotas pasan a "Por Desembolsar"
        DB::statement("
            UPDATE prestamos
            SET estado = 'Por Desembolsar'
            WHERE estado = 'Aprobado'
        ");
    }

    /**
     * Rollback: Volver al enum original.
     */
    public function down(): void
    {
        // Revertir estados nuevos a 'Aprobado'
        DB::statement("
            UPDATE prestamos
            SET estado = 'Aprobado'
            WHERE estado IN ('Por Desembolsar', 'Desembolsado')
        ");

        // Restaurar enum original
        DB::statement("
            ALTER TABLE prestamos 
            MODIFY COLUMN estado ENUM(
                'Pendiente',
                'Aprobado',
                'Ejecutado',
                'Activo',
                'Rechazado',
                'Finalizado',
                'Parcialmente_Retanqueado'
            ) NOT NULL DEFAULT 'Pendiente'
        ");
    }
};
