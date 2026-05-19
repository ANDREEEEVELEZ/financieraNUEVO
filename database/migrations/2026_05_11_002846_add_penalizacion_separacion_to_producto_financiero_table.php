<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('producto_financiero', function (Blueprint $table) {
            $table->decimal('penalizacion_separacion', 5, 4)
                ->default(0.0000)
                ->after('permite_retanqueo')
                ->comment(
                    'Porcentaje del saldo de capital del moroso que se aplica como penalización al grupo. ' .
                    'Configurable por el Súper Admin por producto. Ej: 0.0500 = 5%. Default: 0 (sin penalización).'
                );
        });
    }

    public function down(): void
    {
        Schema::table('producto_financiero', function (Blueprint $table) {
            $table->dropColumn('penalizacion_separacion');
        });
    }
};
