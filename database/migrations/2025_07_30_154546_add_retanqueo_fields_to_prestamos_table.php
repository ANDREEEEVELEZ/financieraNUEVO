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
        Schema::table('prestamos', function (Blueprint $table) {
            $table->string('descripcion')->nullable()->after('calificacion')
                ->comment('Descripción del préstamo para identificar retanqueos');
            $table->boolean('es_retanqueo')->default(false)->after('descripcion')
                ->comment('Indica si el préstamo proviene de un retanqueo');
            $table->foreignId('prestamo_origen_id')->nullable()->after('es_retanqueo')
                ->constrained('prestamos')->onDelete('set null')
                ->comment('ID del préstamo original en caso de retanqueo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('prestamos', function (Blueprint $table) {
            $table->dropForeign(['prestamo_origen_id']);
            $table->dropColumn(['descripcion', 'es_retanqueo', 'prestamo_origen_id']);
        });
    }
};
