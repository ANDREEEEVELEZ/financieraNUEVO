<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Refactoriza los estados del préstamo:
     * 1. Renombra 'Por Desembolsar' → 'Por Firmar'
     * 2. Agrega nuevo estado 'Firmado'
     * 3. Agrega nuevo estado 'Por Desembolsar' (después de Firmado)
     * 
     * Nuevo flujo: Aprobado → Por Firmar → Firmado → Por Desembolsar → Desembolsado
     */
    public function up(): void
    {
        // Paso 1: Primero expandir el enum para incluir TODOS los estados (viejos y nuevos)
        DB::statement("
            ALTER TABLE prestamos 
            MODIFY COLUMN estado ENUM(
                'Pendiente',
                'Aprobado',
                'Por Desembolsar',
                'Por Firmar',
                'Firmado',
                'Desembolsado',
                'Ejecutado',
                'Activo',
                'Rechazado',
                'Finalizado',
                'Parcialmente_Retanqueado'
            ) NOT NULL DEFAULT 'Pendiente'
        ");

        // Paso 2: Ahora migrar préstamos existentes 'Por Desembolsar' → 'Por Firmar'
        DB::statement("
            UPDATE prestamos
            SET estado = 'Por Firmar'
            WHERE estado = 'Por Desembolsar'
        ");

        // Paso 3: Finalmente, reordenar el enum sin el viejo 'Por Desembolsar' en la posición original
        // Ahora 'Por Desembolsar' está después de 'Firmado'
        DB::statement("
            ALTER TABLE prestamos 
            MODIFY COLUMN estado ENUM(
                'Pendiente',
                'Aprobado',
                'Por Firmar',
                'Firmado',
                'Por Desembolsar',
                'Desembolsado',
                'Ejecutado',
                'Activo',
                'Rechazado',
                'Finalizado',
                'Parcialmente_Retanqueado'
            ) NOT NULL DEFAULT 'Pendiente'
        ");
    }

    /**
     * Rollback: Volver al enum anterior.
     */
    public function down(): void
    {
        // Revertir estados nuevos
        DB::statement("
            UPDATE prestamos
            SET estado = 'Aprobado'
            WHERE estado IN ('Por Firmar', 'Firmado')
        ");

        // Restaurar enum original (sin 'Firmado' y con 'Por Desembolsar' en posición original)
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
    }
};
