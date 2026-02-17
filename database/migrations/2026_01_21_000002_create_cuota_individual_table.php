<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Cuotas individuales - Trazabilidad completa por cliente.
     * Cada cliente tiene sus propias cuotas aunque sea préstamo grupal.
     * Esto permite saber exactamente cuánto debe cada persona.
     */
    public function up(): void
    {
        Schema::create('cuota_individual', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prestamo_id')->constrained('prestamos')->onDelete('cascade');
            $table->foreignId('cliente_id')->constrained('clientes')->onDelete('restrict');

            $table->unsignedSmallInteger('numero_cuota');

            // Montos originales (no cambian)
            $table->decimal('monto_capital_original', 12, 2);
            $table->decimal('monto_interes_original', 12, 2);
            $table->decimal('monto_seguro', 12, 2)->default(0);

            // Saldos (se actualizan con pagos)
            $table->decimal('saldo_capital', 12, 2);
            $table->decimal('saldo_interes', 12, 2);

            $table->date('fecha_vencimiento');
            $table->enum('estado', ['pendiente', 'pagada', 'vencida', 'cancelada'])->default('pendiente');

            $table->timestamps();

            // Índices para consultas rápidas
            $table->index(['cliente_id', 'estado']);
            $table->index(['prestamo_id', 'numero_cuota']);
            $table->index('fecha_vencimiento');

            // Restricción: no puede haber dos cuotas con el mismo número para el mismo cliente en el mismo préstamo
            $table->unique(['prestamo_id', 'cliente_id', 'numero_cuota']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cuota_individual');
    }
};
