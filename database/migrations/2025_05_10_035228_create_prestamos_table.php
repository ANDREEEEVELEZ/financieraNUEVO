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
            // TEXT, not string/VARCHAR(255): this column holds an `encrypted`
            // Eloquent cast (Prestamo::$casts, Requirement 3.2). Laravel's
            // encrypted-cast ciphertext (base64 JSON, random IV per write) can
            // exceed 255 bytes for longer account numbers — measured ~228
            // bytes for a 10-34 char plaintext, ~288 bytes for 40 chars —
            // so VARCHAR(255) risks silent truncation. Rewritten in place
            // (pre-prod, no migration history to preserve) rather than adding
            // an incremental ALTER TABLE migration.
            $table->text('numero_cuenta_desembolso')->nullable();
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
