<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mirrors pagos.origen_pago for query symmetry: lets per-cuota drill-downs
     * label which AplicacionPago rows came from a real cash payment vs a
     * retanqueo coverage event (SDD core-contable-seguridad, Slice C, decision D2).
     */
    public function up(): void
    {
        Schema::table('aplicacion_pago', function (Blueprint $table) {
            $table->enum('tipo_aplicacion', ['cobranza', 'retanqueo'])
                ->default('cobranza')
                ->after('cuota_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('aplicacion_pago', function (Blueprint $table) {
            $table->dropColumn('tipo_aplicacion');
        });
    }
};
