<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Elimina la tabla legacy `detalles_pago`.
 *
 * El sistema V1 usaba `detalles_pago → prestamo_individual` para el desglose de pagos.
 * El sistema V2 usa `aplicacion_pago → cuota_individual` con desglose de capital/interés/mora.
 * Esta migración cierra definitivamente el cable suelto del modelo legacy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('detalles_pago');
    }

    /**
     * Restaurar la tabla en caso de rollback (estructura mínima sin datos).
     */
    public function down(): void
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
};
