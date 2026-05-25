<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('metricas_diarias_asesor', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asesor_id')->constrained('users')->cascadeOnDelete();
            $table->date('fecha');
            $table->decimal('cobrado', 12, 2)->default(0);
            $table->unsignedInteger('clientes_count')->default(0);
            $table->unsignedInteger('cuotas_vigentes')->default(0);
            $table->unsignedInteger('cuotas_pagadas')->default(0);
            $table->unsignedInteger('cuotas_mora')->default(0);
            $table->timestamps();
            $table->unique(['asesor_id', 'fecha'], 'metricas_diarias_asesor_asesor_fecha_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('metricas_diarias_asesor');
    }
};
