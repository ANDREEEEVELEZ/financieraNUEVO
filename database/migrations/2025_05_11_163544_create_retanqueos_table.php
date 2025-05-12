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
        Schema::create('retanqueos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prestamo_id')->constrained()->onDelete('cascade');
            $table->foreignId('grupo_id')->constrained()->onDelete('cascade');
            $table->foreignId('asesore_id')->constrained()->onDelete('cascade');
            $table->string('monto_retanqueado')->nullable();
            $table->string('monto_devolver')->nullable();
            $table->string('monto_desembolsar')->nullable();
            $table->string('cantidad_cuotas_retanqueo')->nullable();
            $table->string('aceptado')->nullable();
            $table->datetime('fecha_aceptacion')->nullable();
            $table->string('estado_retanqueo')->nullable();
            


            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('retanqueos');
    }
};
