<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cliente_scorings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->unsignedTinyInteger('score')->default(0);
            $table->string('grado', 1); // A–E
            $table->boolean('vigente')->default(false);
            $table->timestamps();
            $table->index(['cliente_id', 'vigente'], 'cliente_scorings_cliente_vigente_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cliente_scorings');
    }
};
