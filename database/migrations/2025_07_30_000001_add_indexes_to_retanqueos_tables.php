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
     * NOTA: down() vacío intencionalmente.
     *
     * Los índices compuestos ([prestamo_id, estado_retanqueo] y
     * [retanqueo_id, participacion_tipo]) son utilizados por constraints
     * de foreign key. MySQL error 1553 impide dropearlos mientras exista
     * la FK. En migrate:reset, son eliminados automáticamente cuando la
     * migración de creación de tabla ejecuta su down() con DROP TABLE.
     */
    public function down(): void
    {
        // Vacío intencionalmente — ver comentario arriba.
    }
};
