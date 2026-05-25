<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('metas_mensuales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asesor_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedSmallInteger('anio');
            $table->unsignedTinyInteger('mes'); // 1–12
            $table->decimal('meta_monto', 12, 2);
            $table->timestamps();
            $table->unique(['asesor_id', 'anio', 'mes'], 'metas_mensuales_asesor_anio_mes_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('metas_mensuales');
    }
};
