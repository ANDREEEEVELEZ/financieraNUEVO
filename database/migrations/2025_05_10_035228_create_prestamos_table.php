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
        Schema::create('prestamos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grupo_id')->constrained('grupos')->onDelete('cascade'); 
            $table->string('tasa_interes')->nullable();
            $table->string('monto_prestado_total')->nullable();
            $table->string('monto_devolver')->nullable();
            $table->string('cantidad_cuotas')->nullable();
            $table->date('fecha_prestamo')->nullable();
            $table->string('frecuencia')->nullable();
            $table->string('estado')->nullable();
            $table->string('calificacion')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prestamos');
    }
};
