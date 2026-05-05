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
        Schema::create('prestamo_individual', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prestamo_id')->constrained('prestamos')->onDelete('cascade');
            $table->foreignId('cliente_id')->constrained('clientes')->onDelete('cascade');
            $table->decimal('monto_prestado_individual', 10, 2)->nullable();
            $table->decimal('monto_cuota_prestamo_individual', 10, 2)->nullable();
            $table->decimal('monto_devolver_individual', 10, 2)->nullable();
            $table->decimal('seguro', 10, 2)->nullable();
            $table->decimal('interes', 10, 2)->nullable();
            $table->string('estado')->nullable();
            $table->timestamps();
            $table->index(['prestamo_id', 'cliente_id'], 'prestamo_individual_prestamo_cliente_idx');
            $table->index('cliente_id', 'prestamo_individual_cliente_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prestamo_individual');
    }
};
