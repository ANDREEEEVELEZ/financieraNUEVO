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
            $table->decimal('monto_prestado_individual')->nullable();
            $table->decimal('monto_cuota_prestamo_individual')->nullable();
            $table->decimal('monto_devolver_individual')->nullable();
            $table->decimal('seguro')->nullable();
            $table->decimal('interes')->nullable();
            $table->decimal('estado')->nullable();


            $table->timestamps();
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
