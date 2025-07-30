<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Agregar índices para mejorar el rendimiento en consultas de retanqueos
        Schema::table('retanqueos', function (Blueprint $table) {
            $table->index('estado_retanqueo');
            $table->index('fecha_aceptacion');
            $table->index(['prestamo_id', 'estado_retanqueo']);
            $table->index('created_at');
        });

        Schema::table('retanqueos_individual', function (Blueprint $table) {
            $table->index('participacion_tipo');
            $table->index('estado_retanqueo_individual');
            $table->index(['retanqueo_id', 'participacion_tipo']);
            $table->index(['cliente_id', 'participacion_tipo']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('retanqueos', function (Blueprint $table) {
            $table->dropIndex(['estado_retanqueo']);
            $table->dropIndex(['fecha_aceptacion']);
            $table->dropIndex(['prestamo_id', 'estado_retanqueo']);
            $table->dropIndex(['created_at']);
        });

        Schema::table('retanqueos_individual', function (Blueprint $table) {
            $table->dropIndex(['participacion_tipo']);
            $table->dropIndex(['estado_retanqueo_individual']);
            $table->dropIndex(['retanqueo_id', 'participacion_tipo']);
            $table->dropIndex(['cliente_id', 'participacion_tipo']);
        });
    }
};
