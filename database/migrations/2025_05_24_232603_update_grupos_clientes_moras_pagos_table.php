<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Quitar campo monto_mora de moras si existe
        if (Schema::hasColumn('moras', 'monto_mora')) {
            Schema::table('moras', function (Blueprint $table) {
                $table->dropColumn('monto_mora');
            });
        }

        // Agregar monto_mora_pagada a pagos si no existe
        if (!Schema::hasColumn('pagos', 'monto_mora_pagada')) {
            Schema::table('pagos', function (Blueprint $table) {
                $table->decimal('monto_mora_pagada', 10, 2)->nullable()->after('monto_pagado');
            });
        }

        // Agregar asesor_id nullable a grupos si no existe
        if (!Schema::hasColumn('grupos', 'asesor_id')) {
            Schema::table('grupos', function (Blueprint $table) {
                $table->foreignId('asesor_id')->nullable()->constrained('asesores')->onDelete('cascade');
            });
        }

        // Agregar asesor_id nullable a clientes si no existe
        if (!Schema::hasColumn('clientes', 'asesor_id')) {
            Schema::table('clientes', function (Blueprint $table) {
                $table->foreignId('asesor_id')
                      ->nullable()
                      ->constrained('asesores')
                      ->onDelete('set null');
            });
        }
    }

    public function down()
    {
        // Restaurar monto_mora en moras si no existe
        if (!Schema::hasColumn('moras', 'monto_mora')) {
            Schema::table('moras', function (Blueprint $table) {
                $table->decimal('monto_mora', 10, 2)->nullable();
            });
        }

        // Eliminar monto_mora_pagada de pagos si existe
        if (Schema::hasColumn('pagos', 'monto_mora_pagada')) {
            Schema::table('pagos', function (Blueprint $table) {
                $table->dropColumn('monto_mora_pagada');
            });
        }

        // Eliminar asesor_id de grupos si existe
        if (Schema::hasColumn('grupos', 'asesor_id')) {
            Schema::table('grupos', function (Blueprint $table) {
                $table->dropForeign(['asesor_id']);
                $table->dropColumn('asesor_id');
            });
        }

        // Eliminar asesor_id de clientes si existe
        if (Schema::hasColumn('clientes', 'asesor_id')) {
            Schema::table('clientes', function (Blueprint $table) {
                $table->dropForeign(['asesor_id']);
                $table->dropColumn('asesor_id');
            });
        }
    }
};
