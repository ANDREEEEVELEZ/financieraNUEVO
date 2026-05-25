<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cuota_individual', function (Blueprint $table) {
            if (!Schema::hasIndex('cuota_individual', 'cuota_ind_fvenc_estado_idx')) {
                $table->index(['fecha_vencimiento', 'estado'], 'cuota_ind_fvenc_estado_idx');
            }
        });

        Schema::table('pagos', function (Blueprint $table) {
            if (!Schema::hasIndex('pagos', 'pagos_estado_fecha_idx')) {
                $table->index(['estado_pago', 'fecha_pago'], 'pagos_estado_fecha_idx');
            }
        });

        Schema::table('aplicacion_pago', function (Blueprint $table) {
            if (!Schema::hasIndex('aplicacion_pago', 'aplicacion_pago_cuota_pago_idx')) {
                $table->index(['cuota_id', 'pago_id'], 'aplicacion_pago_cuota_pago_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cuota_individual', function (Blueprint $table) {
            $table->dropIndexIfExists('cuota_ind_fvenc_estado_idx');
        });

        Schema::table('pagos', function (Blueprint $table) {
            $table->dropIndexIfExists('pagos_estado_fecha_idx');
        });

        Schema::table('aplicacion_pago', function (Blueprint $table) {
            $table->dropIndexIfExists('aplicacion_pago_cuota_pago_idx');
        });
    }
};
