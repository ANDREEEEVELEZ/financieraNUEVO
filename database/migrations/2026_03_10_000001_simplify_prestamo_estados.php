<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Fase 0.1 — Simplificación de estados del préstamo.
 *
 * Reduce el enum de 11 estados a 10 estados funcionales:
 *   - Elimina transiciones disfrazadas de estado (Por Firmar, Por Desembolsar).
 *   - Absorbe estados legacy (Desembolsado, Ejecutado) en 'Activo'.
 *   - Convierte 'Parcialmente_Retanqueado' en flag booleano.
 *   - Agrega nuevos estados: Al_Día, En_Mora, Cancelado, Reformulado.
 *
 * Nuevo flujo: Pendiente → Aprobado → Firmado → Activo → Al_Día/En_Mora → Finalizado/Cancelado
 */
return new class extends Migration {
    public function up(): void
    {
        // Paso 1: Agregar columna boolean para retanqueo parcial
        if (!Schema::hasColumn('prestamos', 'es_parcialmente_retanqueado')) {
            Schema::table('prestamos', function (Blueprint $table) {
                $table->boolean('es_parcialmente_retanqueado')->default(false)->after('es_retanqueo');
            });
        }

        // Paso 2: Expandir enum temporalmente para incluir todos los estados
        // (viejos + nuevos) antes de migrar datos
        DB::statement("
            ALTER TABLE prestamos MODIFY COLUMN estado ENUM(
                'Pendiente', 'Aprobado', 'Por Firmar', 'Firmado',
                'Por Desembolsar', 'Desembolsado', 'Ejecutado',
                'Activo', 'Al_Día', 'En_Mora',
                'Rechazado', 'Reformulado',
                'Finalizado', 'Cancelado',
                'Parcialmente_Retanqueado'
            ) NOT NULL DEFAULT 'Pendiente'
        ");

        // Paso 3: Marcar préstamos parcialmente retanqueados antes de migrar
        DB::table('prestamos')
            ->where('estado', 'Parcialmente_Retanqueado')
            ->update(['es_parcialmente_retanqueado' => true]);

        // Paso 4: Migrar estados legacy → estados funcionales
        DB::table('prestamos')
            ->where('estado', 'Por Firmar')
            ->update(['estado' => 'Aprobado']);

        DB::table('prestamos')
            ->where('estado', 'Por Desembolsar')
            ->update(['estado' => 'Firmado']);

        DB::table('prestamos')
            ->whereIn('estado', ['Desembolsado', 'Ejecutado', 'Parcialmente_Retanqueado'])
            ->update(['estado' => 'Activo']);

        // Paso 5: Establecer enum definitivo (10 estados funcionales)
        DB::statement("
            ALTER TABLE prestamos MODIFY COLUMN estado ENUM(
                'Pendiente', 'Aprobado', 'Firmado', 'Activo',
                'Al_Día', 'En_Mora', 'Rechazado', 'Reformulado',
                'Finalizado', 'Cancelado'
            ) NOT NULL DEFAULT 'Pendiente'
        ");
    }

    public function down(): void
    {
        // Restaurar enum original con todos los estados legacy
        DB::statement("
            ALTER TABLE prestamos MODIFY COLUMN estado ENUM(
                'Pendiente', 'Aprobado', 'Por Firmar', 'Firmado',
                'Por Desembolsar', 'Desembolsado', 'Ejecutado',
                'Activo', 'Rechazado', 'Finalizado',
                'Parcialmente_Retanqueado'
            ) NOT NULL DEFAULT 'Pendiente'
        ");

        // Restaurar Parcialmente_Retanqueado desde el flag
        DB::table('prestamos')
            ->where('es_parcialmente_retanqueado', true)
            ->update(['estado' => 'Parcialmente_Retanqueado']);

        // Remover columna boolean
        Schema::table('prestamos', function (Blueprint $table) {
            $table->dropColumn('es_parcialmente_retanqueado');
        });
    }
};
