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
        Schema::create('grupos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_grupo')->nullable();
            $table->string('numero_integrantes')->nullable();
            $table->date('fecha_registro')->nullable();
            $table->string('calificacion_grupo')->nullable();
            $table->string('estado_grupo')->nullable();
            $table->foreignId('asesor_id')->nullable()->constrained('asesores')->onDelete('cascade');
            $table->timestamps();
            $table->index('asesor_id', 'grupos_asesor_idx');
            $table->index('estado_grupo', 'grupos_estado_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grupos');
    }
};
