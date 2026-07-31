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
            $table->foreignId('cuota_grupal_id')->nullable()->constrained('cuotas_grupales')->onDelete('cascade');
            $table->date('fecha_atraso')->nullable();
            $table->unsignedInteger('dias_atraso')->default(0);
            $table->decimal('monto_mora_generada', 10, 2)->default(0.00);
            $table->decimal('monto_mora_pagada', 10, 2)->default(0.00);
            $table->enum('estado_mora', ['pendiente', 'pagada', 'parcialmente_pagada'])->default('pendiente');
            $table->date('fecha_snapshot')->nullable();
            $table->timestamps();

            $table->index('estado_mora', 'moras_estado_idx');
            $table->index('fecha_snapshot', 'moras_fecha_snapshot_idx');
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
