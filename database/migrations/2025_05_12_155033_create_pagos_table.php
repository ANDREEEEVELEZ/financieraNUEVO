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
        Schema::create('pagos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cuota_grupal_id')->nullable()->constrained('cuotas_grupales')->onDelete('cascade');
            $table->string('tipo_pago')->nullable();
            $table->string('codigo_operacion')->nullable();
            $table->decimal('monto_pagado', 10, 2)->nullable();
            $table->decimal('monto_mora_pagada', 10, 2)->nullable();
            $table->dateTime('fecha_pago')->nullable();
            $table->string('estado_pago')->nullable();
            $table->string('observaciones')->nullable();
            $table->timestamps();
            $table->index(['cuota_grupal_id', 'estado_pago'], 'pagos_cuota_estado_idx');
            $table->index('fecha_pago', 'pagos_fecha_idx');
            $table->index('estado_pago', 'pagos_estado_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pagos');
    }
};
