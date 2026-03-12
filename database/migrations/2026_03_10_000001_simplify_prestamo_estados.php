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
        // Paso 1: Expandir ENUM para incluir todos los estados posibles
        // (nuevos + legacy) antes de migrar datos — evita error de valor inválido
        DB::statement("
            ALTER TABLE prestamos MODIFY COLUMN estado ENUM(
                'Pendiente', 'Aprobado', 'Por Firmar', 'Firmado',
                'Por Desembolsar', 'Desembolsado', 'Ejecutado',
                'Activo', 'Al_Día', 'En_Mora', 'Rechazado', 'Reformulado',
                'Finalizado', 'Cancelado', 'Parcialmente_Retanqueado'
            ) NOT NULL DEFAULT 'Pendiente'
        ");

        // Paso 2: Revertir estados nuevos a sus equivalentes legacy
        DB::table('prestamos')->where('estado', 'En_Mora')->update(['estado' => 'Activo']);
        DB::table('prestamos')->where('estado', 'Al_Día')->update(['estado' => 'Activo']);
        DB::table('prestamos')->where('estado', 'Reformulado')->update(['estado' => 'Rechazado']);
        DB::table('prestamos')->where('estado', 'Cancelado')->update(['estado' => 'Finalizado']);

        // Paso 3: Restaurar Parcialmente_Retanqueado desde el flag boolean
        DB::table('prestamos')
            ->where('es_parcialmente_retanqueado', true)
            ->update(['estado' => 'Parcialmente_Retanqueado']);

        // Paso 4: Restaurar ENUM legacy original
        DB::statement("
            ALTER TABLE prestamos MODIFY COLUMN estado ENUM(
                'Pendiente', 'Aprobado', 'Por Firmar', 'Firmado',
                'Por Desembolsar', 'Desembolsado', 'Ejecutado',
                'Activo', 'Rechazado', 'Finalizado',
                'Parcialmente_Retanqueado'
            ) NOT NULL DEFAULT 'Pendiente'
        ");

        // Paso 5: Remover columna boolean (si existe)
        if (Schema::hasColumn('prestamos', 'es_parcialmente_retanqueado')) {
            Schema::table('prestamos', function (Blueprint $table) {
                $table->dropColumn('es_parcialmente_retanqueado');
            });
        }
    }
};
