<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
     * BUGFIX v2: Se usa DB::statement con "DROP INDEX IF EXISTS" de MySQL
     * porque dropIndex() falla si el índice no existe en la BD.
     * Esto puede ocurrir si el up() falló parcialmente en entornos previos.
     */
    public function down(): void
    {
        // Índices de tabla 'retanqueos'
        DB::statement('ALTER TABLE `retanqueos` DROP INDEX IF EXISTS `retanqueos_estado_retanqueo_index`');
        DB::statement('ALTER TABLE `retanqueos` DROP INDEX IF EXISTS `retanqueos_fecha_aceptacion_index`');
        DB::statement('ALTER TABLE `retanqueos` DROP INDEX IF EXISTS `retanqueos_prestamo_id_estado_retanqueo_index`');
        DB::statement('ALTER TABLE `retanqueos` DROP INDEX IF EXISTS `retanqueos_created_at_index`');

        // Índices de tabla 'retanqueos_individual'
        DB::statement('ALTER TABLE `retanqueos_individual` DROP INDEX IF EXISTS `retanqueos_individual_participacion_tipo_index`');
        DB::statement('ALTER TABLE `retanqueos_individual` DROP INDEX IF EXISTS `retanqueos_individual_estado_retanqueo_individual_index`');
        DB::statement('ALTER TABLE `retanqueos_individual` DROP INDEX IF EXISTS `retanqueos_individual_retanqueo_id_participacion_tipo_index`');
        DB::statement('ALTER TABLE `retanqueos_individual` DROP INDEX IF EXISTS `retanqueos_individual_cliente_id_participacion_tipo_index`');
    }
};
