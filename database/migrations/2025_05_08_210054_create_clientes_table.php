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
        Schema::create('clientes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('persona_id')->constrained('personas')->onDelete('cascade');
            $table->foreignId('asesor_id')->nullable()->constrained('asesores')->onDelete('set null');
            $table->string('infocorp')->nullable();
            $table->string('ciclo')->nullable();
            $table->string('condicion_vivienda')->nullable();
            $table->string('actividad')->nullable();
            $table->string('condicion_personal')->nullable();
            $table->string('estado_cliente')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clientes');
    }
};
