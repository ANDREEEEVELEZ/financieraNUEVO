<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Reemplaza 'dias_atraso' (integer) por 'fecha_atraso' (date) en moras.
     */
    public function up(): void
    {
        Schema::table('moras', function (Blueprint $table) {
            if (!Schema::hasColumn('moras', 'fecha_atraso')) {
                $table->date('fecha_atraso')->nullable();
            }
            if (Schema::hasColumn('moras', 'dias_atraso')) {
                $table->dropColumn('dias_atraso');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * BUGFIX: El down() original fallaba con error 1091 si 'fecha_atraso'
     * no existía. Se agregan guards con Schema::hasColumn.
     * También se corrige el tipo de 'dias_atraso' — era integer, no date.
     */
    public function down(): void
    {
        Schema::table('moras', function (Blueprint $table) {
            if (Schema::hasColumn('moras', 'fecha_atraso')) {
                $table->dropColumn('fecha_atraso');
            }
            if (!Schema::hasColumn('moras', 'dias_atraso')) {
                $table->integer('dias_atraso')->nullable();
            }
        });
    }
};
