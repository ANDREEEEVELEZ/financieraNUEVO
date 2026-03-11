<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
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
     *
     * BUGFIX: dropIndex(['col']) genera nombres incorrectos para índices
     * compuestos. Se especifican los nombres exactos que Laravel/MySQL crean
     * automáticamente en up() con el formato: {tabla}_{columnas}_index.
     */
    public function down(): void
    {
        Schema::table('retanqueos', function (Blueprint $table) {
            $table->dropIndex('retanqueos_estado_retanqueo_index');
            $table->dropIndex('retanqueos_fecha_aceptacion_index');
            $table->dropIndex('retanqueos_prestamo_id_estado_retanqueo_index');
            $table->dropIndex('retanqueos_created_at_index');
        });

        Schema::table('retanqueos_individual', function (Blueprint $table) {
            $table->dropIndex('retanqueos_individual_participacion_tipo_index');
            $table->dropIndex('retanqueos_individual_estado_retanqueo_individual_index');
            $table->dropIndex('retanqueos_individual_retanqueo_id_participacion_tipo_index');
            $table->dropIndex('retanqueos_individual_cliente_id_participacion_tipo_index');
        });
    }
};
