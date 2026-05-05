<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Tabla de configuración de productos financieros.
     * Patrón Strategy: permite crear nuevos productos desde el panel sin tocar código.
     */
    public function up(): void
    {
        Schema::create('producto_financiero', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 20)->unique(); // CG-BÁSICO, CI-EMPRENDEDOR
            $table->string('nombre', 100);
            $table->enum('tipo', ['grupal', 'individual', 'hipotecario'])->default('grupal');
            $table->decimal('tasa_interes', 5, 4); // 0.1000 = 10%
            $table->decimal('tasa_mora', 5, 4)->default(0.05); // 0.0500 = 5%
            $table->boolean('permite_condonacion_mora')->default(true);
            $table->decimal('monto_minimo', 12, 2)->default(1000);
            $table->decimal('monto_maximo', 12, 2)->default(50000);
            $table->unsignedTinyInteger('plazo_minimo_meses')->default(3);
            $table->unsignedTinyInteger('plazo_maximo_meses')->default(24);
            $table->json('config_json')->nullable(); // Reglas adicionales
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('producto_financiero');
    }
};
