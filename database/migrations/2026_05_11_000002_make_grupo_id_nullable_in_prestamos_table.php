<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El campo grupo_id debe ser nullable para soportar préstamos individuales
 * generados por separación de morosos (tipo='individual', grupo_id=null).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prestamos', function (Blueprint $table) {
            $table->foreignId('grupo_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('prestamos', function (Blueprint $table) {
            $table->foreignId('grupo_id')->nullable(false)->change();
        });
    }
};
