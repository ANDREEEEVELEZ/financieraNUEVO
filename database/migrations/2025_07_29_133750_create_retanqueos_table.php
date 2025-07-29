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

        $table->foreignId('prestamo_id')
            ->constrained('prestamos')
            ->cascadeOnDelete();

        $table->foreignId('prestamo_nuevo_id')
            ->nullable()
            ->constrained('prestamos')
            ->nullOnDelete();
        $table->integer('total_cuotas_cubiertas_antiguo')->nullable();
        // Total del nuevo préstamo
        $table->decimal('monto_retanqueo', 10, 2)->nullable();
        // Suma de aportes que se usan para cubrir el préstamo antiguo
        $table->decimal('monto_usado_para_cubrir_antiguo', 10, 2)->default(0);
        // Total a entregar (monto_retanqueo - monto_usado_para_cubrir_antiguo)
        $table->decimal('monto_desembolsar', 10, 2)->default(0);
        $table->decimal('monto_cuota', 10, 2)->nullable();
        $table->integer('cantidad_cuotas_nuevo')->nullable();
        // Saldo que queda del antiguo DESPUÉS de cubrir
        $table->decimal('saldo_restante_prestamo_antiguo', 10, 2)->default(0);
        // 1 = se cubre todo el antiguo, 0 = queda saldo
        $table->tinyInteger('prestamo_antiguo_estado')->default(0);
        $table->date('fecha_aceptacion')->nullable();
        $table->string('estado_retanqueo');
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
