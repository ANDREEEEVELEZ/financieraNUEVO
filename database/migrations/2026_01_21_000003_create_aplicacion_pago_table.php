<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Aplicación de pagos - Trazabilidad completa de cómo se distribuyó cada pago.
     * Cada registro indica cuánto de un pago fue a capital, interés y mora de una cuota específica.
     */
    public function up(): void
    {
        Schema::create('aplicacion_pago', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pago_id')->constrained('pagos')->onDelete('cascade');
            $table->foreignId('cuota_id')->constrained('cuota_individual')->onDelete('restrict');

            $table->decimal('monto_aplicado_capital', 12, 2)->default(0);
            $table->decimal('monto_aplicado_interes', 12, 2)->default(0);
            $table->decimal('monto_aplicado_mora', 12, 2)->default(0);

            $table->timestamp('fecha_aplicacion')->useCurrent();

            $table->timestamps();

            // Índices
            $table->index('pago_id');
            $table->index('cuota_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aplicacion_pago');
    }
};
