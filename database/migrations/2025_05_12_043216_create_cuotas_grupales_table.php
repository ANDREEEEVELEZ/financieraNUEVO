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
        Schema::create('cuotas_grupales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prestamo_id')->constrained('prestamos')->onDelete('cascade');
            $table->decimal('numero_cuota')->nullable();
            $table->decimal('monto_cuota_grupal')->nullable();
            $table->date('fecha_vencimiento')->nullable();
            $table->string('estado_cuota_grupal')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cuotas_grupales');
    }
};
