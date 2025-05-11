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
        Schema::create('asesores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('persona_id')->nullable()->constrained('personas')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->string('codigo_asesor')->nullable();
            $table->date('fecha_ingreso')->nullable();
            $table->string('estado_asesor')->nullable();

            $table->timestamps();

            // Índices para mejorar rendimiento
            $table->index('persona_id');
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asesores');
    }
};