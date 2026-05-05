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
            $table->integer('tasa_interes')->nullable();
            $table->decimal('monto_prestado_total', 10, 2)->nullable();
            $table->decimal('monto_devolver', 10, 2)->nullable();
            $table->integer('cantidad_cuotas')->nullable();
            $table->date('fecha_prestamo')->nullable();
            $table->date('fecha_desembolso')->nullable();
            $table->string('frecuencia')->nullable();
            $table->string('estado')->nullable();
            $table->string('calificacion')->nullable();
            $table->text('descripcion')->nullable();
            $table->boolean('es_retanqueo')->default(false);
            $table->boolean('es_parcialmente_retanqueado')->default(false);
            $table->foreignId('prestamo_origen_id')->nullable()->constrained('prestamos')->onDelete('set null');
            $table->string('titular_cuenta_desembolso')->nullable();
            $table->string('numero_cuenta_desembolso')->nullable();
            $table->foreignId('producto_id')->nullable()->constrained('producto_financiero')->onDelete('restrict');
            $table->timestamps();
            $table->index(['grupo_id', 'estado'], 'prestamos_grupo_estado_idx');
            $table->index('estado', 'prestamos_estado_idx');
            $table->index('fecha_prestamo', 'prestamos_fecha_idx');
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
