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
        Schema::create('moras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cuota_grupal_id')->constrained('cuotas_grupales')->onDelete('cascade');
            $table->integer('dias_atraso')->nullable();
            $table->decimal('monto_mora')->nullable();
            $table->enum('estado_mora', ['pendiente', 'pagada', 'parcialmente_pagada'])->default('pendiente');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('moras');
    }
};
