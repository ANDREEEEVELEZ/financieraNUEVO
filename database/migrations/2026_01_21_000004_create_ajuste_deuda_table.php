<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Ajustes de deuda - Registro de condonaciones, descuentos y refinanciamientos.
     * Permite auditar quién autorizó cada ajuste y por qué.
     */
    public function up(): void
    {
        Schema::create('ajuste_deuda', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cuota_id')->constrained('cuota_individual')->onDelete('cascade');
            $table->foreignId('usuario_id')->constrained('users')->onDelete('restrict');

            $table->enum('tipo', [
                'condonacion_mora',
                'descuento_capital',
                'descuento_interes',
                'refinanciamiento',
                'castigo'
            ]);

            $table->decimal('monto_ajuste', 12, 2);
            $table->text('justificacion');
            $table->json('metadata')->nullable(); // Documentos de aprobación, etc.

            $table->timestamps();

            // Índices
            $table->index(['cuota_id', 'tipo']);
            $table->index('usuario_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ajuste_deuda');
    }
};
