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
        Schema::create('retanqueos_individual', function (Blueprint $table) {
            $table->id();

            $table->foreignId('retanqueo_id')
                ->constrained('retanqueos')
                ->cascadeOnDelete();

            $table->foreignId('cliente_id')
                ->constrained('clientes')
                ->cascadeOnDelete();

            // retanquea | no_retanquea | nueva
            $table->string('participacion_tipo', 20);

            // Cuánto de su parte del nuevo se usa para cubrir el préstamo antiguo
            $table->decimal('aporte_cobertura', 10, 2)->default(0);

            // Monto solicitado del nuevo (bruto)
            $table->decimal('monto_solicitado', 10, 2)->default(0);

            // Monto a desembolsar efectivo al cliente (después de cobertura)
            $table->decimal('monto_desembolsar', 10, 2)->default(0);

            // Cuota del nuevo prestamo para el cliente (si aplica)
            $table->decimal('monto_cuota', 10, 2)->nullable();

            // Aceptación del cliente: 0/1
            $table->tinyInteger('aceptacion_cliente')->default(0);

            // Estado del individuo: propuesto | aceptado | rechazado
            $table->string('estado_retanqueo_individual', 30)->default('propuesto');

            $table->timestamps();

            $table->index('participacion_tipo');
            $table->index('estado_retanqueo_individual');
            $table->index(['retanqueo_id', 'participacion_tipo']);
            $table->index(['cliente_id', 'participacion_tipo']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('retanqueos_individual');
    }
};
