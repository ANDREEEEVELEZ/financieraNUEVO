<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 0.3 — Tabla para registrar separaciones de clientes morosos.
 *
 * Cuando un integrante de un préstamo grupal está en mora,
 * JC o JO pueden separarlo del grupo. Este registro mantiene:
 *   - La deuda de capital y mora del cliente separado
 *   - La penalización aplicada al grupo
 *   - El motivo y quién ejecutó la acción
 *   - Trazabilidad completa para auditoría
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('separaciones_clientes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prestamo_origen_id')
                ->constrained('prestamos')
                ->comment('Préstamo grupal del que se separa el cliente');
            $table->foreignId('grupo_origen_id')
                ->constrained('grupos')
                ->comment('Grupo al que pertenecía el cliente');
            $table->foreignId('cliente_id')
                ->constrained('clientes')
                ->comment('Cliente separado del grupo');
            $table->foreignId('ejecutado_por')
                ->constrained('users')
                ->comment('Usuario que ejecutó la separación (JC o JO)');
            $table->decimal('deuda_capital', 12, 2)
                ->comment('Deuda de capital pendiente del cliente al momento de la separación');
            $table->decimal('deuda_mora', 12, 2)->default(0)
                ->comment('Deuda de mora acumulada del cliente al momento de la separación');
            $table->decimal('penalizacion_grupo', 12, 2)->default(0)
                ->comment('Monto de penalización aplicada al grupo por la separación');
            $table->text('motivo')->nullable()
                ->comment('Justificación de la separación');
            $table->enum('estado', ['ejecutada', 'revertida'])->default('ejecutada')
                ->comment('Estado de la separación');
            $table->timestamps();

            $table->index('prestamo_origen_id', 'sep_prestamo_index');
            $table->index('cliente_id', 'sep_cliente_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('separaciones_clientes');
    }
};
