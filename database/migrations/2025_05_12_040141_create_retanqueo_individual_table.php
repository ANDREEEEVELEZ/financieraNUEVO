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
        Schema::create('retanqueo_individual', function (Blueprint $table) {
            $table->id();
            $table->foreignId('retanqueo_id')->constrained('retanqueos')->onDelete('cascade'); 
            $table->foreignId('cliente_id')->constrained('clientes')->onDelete('cascade'); 
            $table->decimal('monto_solicitado')->nullable();
            $table->decimal('monto_desembolsar')->nullable();
            $table->decimal('monto_cuota_retanqueo')->nullable();
            $table->decimal('estado_retanqueo_individual')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('retanqueo_individual');
    }
};
