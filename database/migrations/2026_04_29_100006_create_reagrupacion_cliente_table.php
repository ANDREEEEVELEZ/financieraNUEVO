<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabla pivot para reagrupaciones.
     *
     * Reemplaza los campos JSON `clientes_trasladados` y `clientes_retenidos`
     * en la tabla `reagrupaciones` por filas tipadas con discriminador `tipo`.
     *
     * ADR-006: una sola tabla pivot con discriminador ENUM en lugar de dos tablas.
     */
    public function up(): void
    {
        Schema::create('reagrupacion_cliente', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('reagrupacion_id')
                ->constrained('reagrupaciones')
                ->onDelete('cascade');
            $table->foreignId('cliente_id')
                ->constrained('clientes')
                ->onDelete('cascade');
            $table->enum('tipo', ['trasladado', 'retenido']);
            $table->timestamps();

            $table->unique(['reagrupacion_id', 'cliente_id']);
            $table->index(['reagrupacion_id', 'tipo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reagrupacion_cliente');
    }
};
