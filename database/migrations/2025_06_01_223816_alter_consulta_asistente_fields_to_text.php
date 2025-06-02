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
        Schema::table('consultas_asistente', function (Blueprint $table) {
            $table->text('consulta')->change();
            $table->text('respuesta')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
     public function down(): void
    {
        Schema::table('consultas_asistente', function (Blueprint $table) {
            $table->string('consulta', 255)->change();
            $table->string('respuesta', 255)->change();
        });
    }
};
