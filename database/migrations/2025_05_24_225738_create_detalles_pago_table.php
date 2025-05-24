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
        Schema::create('detalles_pago', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pago_id')->constrained('pagos')->onDelete('cascade');
             $table->foreignId('prestamo_individual_id')->constrained('prestamo_individual')->onDelete('cascade');
             $table->decimal('monto_pagado', 10, 2)->nullable();
             $table->enum('estado_pago_individual', ['Pagada', 'Parcial', 'Mora']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('detalle_pago');
    }
};
