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
        // Primero actualizamos los préstamos que estén en estado 'Aprobado'
        // a 'Ejecutado' si ya tienen cuotas o pagos registrados
        DB::table('prestamos')
            ->where('estado', 'Aprobado')
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('cuotas_grupales')
                    ->whereColumn('prestamos.id', 'cuotas_grupales.prestamo_id');
            })
            ->update(['estado' => 'Ejecutado']);

        // Actualizamos los préstamos que estén en estado 'Ejecutado' a 'Activo'
        // si tienen al menos un pago registrado pero no están completamente pagados
        DB::table('prestamos')
            ->where('estado', 'Ejecutado')
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('cuotas_grupales')
                    ->whereColumn('prestamos.id', 'cuotas_grupales.prestamo_id')
                    ->where('estado_pago', 'pagado');
            })
            ->update(['estado' => 'Activo']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revertir los cambios si es necesario
        DB::table('prestamos')
            ->whereIn('estado', ['Ejecutado', 'Activo'])
            ->update(['estado' => 'Aprobado']);
    }
};