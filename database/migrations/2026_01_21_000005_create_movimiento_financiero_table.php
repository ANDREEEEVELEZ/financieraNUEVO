<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Movimientos financieros - Unifica ingresos y egresos en una sola tabla.
     * Elimina la redundancia de tener tablas separadas.
     * Usa polimorfismo para referenciar la fuente (Préstamo, Pago, etc).
     */
    public function up(): void
    {
        Schema::create('movimiento_financiero', function (Blueprint $table) {
            $table->id();

            $table->enum('tipo', ['ingreso', 'egreso']);
            $table->enum('concepto', [
                'desembolso',           // Egreso: dinero sale para préstamo
                'pago_cuota',           // Ingreso: cliente paga cuota
                'pago_mora',            // Ingreso: cliente paga mora
                'gasto_operativo',      // Egreso: gastos de la empresa
                'comision_asesor',      // Egreso: pago a asesores
                'ajuste_positivo',      // Ingreso: corrección manual
                'ajuste_negativo',      // Egreso: corrección manual
                'otro'
            ]);

            $table->decimal('monto', 12, 2);
            $table->date('fecha');

            // Referencia polimórfica (puede ser Prestamo, Pago, o null)
            $table->string('referencia_tipo', 100)->nullable(); // App\Models\Prestamo, etc.
            $table->unsignedBigInteger('referencia_id')->nullable();

            $table->text('descripcion')->nullable();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->onDelete('set null');

            $table->timestamps();

            // Índices
            $table->index(['tipo', 'fecha']);
            $table->index(['referencia_tipo', 'referencia_id']);
            $table->index('concepto');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimiento_financiero');
    }
};
