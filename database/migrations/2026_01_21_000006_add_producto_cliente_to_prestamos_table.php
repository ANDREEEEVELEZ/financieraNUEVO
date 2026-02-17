<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Modifica la tabla prestamos para soportar:
     * 1. Productos financieros configurables
     * 2. Préstamos individuales (sin grupo)
     */
    public function up(): void
    {
        Schema::table('prestamos', function (Blueprint $table) {
            // Agregar referencia a producto financiero
            $table->foreignId('producto_id')
                ->nullable()
                ->after('id')
                ->constrained('producto_financiero')
                ->onDelete('restrict');

            // Agregar cliente_id para préstamos individuales
            $table->foreignId('cliente_id')
                ->nullable()
                ->after('grupo_id')
                ->constrained('clientes')
                ->onDelete('restrict');

            // Hacer grupo_id nullable (para préstamos individuales)
            $table->unsignedBigInteger('grupo_id')->nullable()->change();

            // Índice para búsquedas por producto
            $table->index('producto_id');
        });
    }

    public function down(): void
    {
        Schema::table('prestamos', function (Blueprint $table) {
            $table->dropForeign(['producto_id']);
            $table->dropForeign(['cliente_id']);
            $table->dropColumn(['producto_id', 'cliente_id']);
        });
    }
};
