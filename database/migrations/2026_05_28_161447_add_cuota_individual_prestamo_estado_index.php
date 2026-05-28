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
        Schema::table('cuota_individual', function (Blueprint $table) {
            if (!Schema::hasIndex('cuota_individual', 'cuota_individual_prestamo_id_estado_index')) {
                $table->index(['prestamo_id', 'estado'], 'cuota_individual_prestamo_id_estado_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cuota_individual', function (Blueprint $table) {
            $table->dropIndexIfExists('cuota_individual_prestamo_id_estado_index');
        });
    }
};
