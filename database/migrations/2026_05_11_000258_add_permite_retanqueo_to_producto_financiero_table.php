<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('producto_financiero', function (Blueprint $table) {
            $table->boolean('permite_retanqueo')
                ->default(false)
                ->after('permite_condonacion_mora')
                ->comment('El Súper Admin habilita/deshabilita el retanqueo para este producto.');
        });
    }

    public function down(): void
    {
        Schema::table('producto_financiero', function (Blueprint $table) {
            $table->dropColumn('permite_retanqueo');
        });
    }
};
