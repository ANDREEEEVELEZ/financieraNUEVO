<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 0.4 — Tabla para registrar reagrupaciones parciales.
 *
 * Cuando un grupo quiere renovar/retanquear pero 1-2 integrantes
 * no han cancelado su cuota, se permite:
 *   - Tipo 'retanqueo': Los integrantes al día forman un nuevo grupo;
 *     se descuenta la última cuota pendiente del nuevo monto.
 *   - Tipo 'renovacion': Los integrantes al día forman un nuevo grupo;
 *     nuevo préstamo sin descuentos.
 *
 * Los integrantes morosos permanecen en el grupo original con su deuda.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('reagrupaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grupo_origen_id')
                ->constrained('grupos')
                ->comment('Grupo original del que salen integrantes');
            $table->foreignId('grupo_nuevo_id')
                ->constrained('grupos')
                ->comment('Nuevo grupo creado con integrantes al día');
            $table->foreignId('prestamo_origen_id')
                ->constrained('prestamos')
                ->comment('Préstamo del grupo original');
            $table->foreignId('ejecutado_por')
                ->constrained('users')
                ->comment('Usuario que ejecutó la reagrupación (JC o JO)');
            $table->enum('tipo', ['retanqueo', 'renovacion'])
                ->comment('Tipo de reagrupación: retanqueo descuenta última cuota, renovacion no');
            $table->decimal('monto_descuento', 12, 2)->default(0)
                ->comment('Monto descontado en caso de retanqueo (última cuota)');
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->index('grupo_origen_id', 'reag_grupo_origen_index');
            $table->index('grupo_nuevo_id', 'reag_grupo_nuevo_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reagrupaciones');
    }
};
