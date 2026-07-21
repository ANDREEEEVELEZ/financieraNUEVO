<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Discriminator for the origin of a Pago row: real cash collection ('cobranza')
     * vs retanqueo coverage ledger entry ('retanqueo'). Default 'cobranza' is safe
     * for all existing rows — retanqueo has never created Pago records before this
     * migration (SDD core-contable-seguridad, Slice C, decision D1).
     */
    public function up(): void
    {
        Schema::table('pagos', function (Blueprint $table) {
            $table->enum('origen_pago', ['cobranza', 'retanqueo'])
                ->default('cobranza')
                ->after('cuota_grupal_id');
        });

        Schema::table('pagos', function (Blueprint $table) {
            $table->index(['origen_pago', 'estado_pago'], 'pagos_origen_estado_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pagos', function (Blueprint $table) {
            $table->dropIndex('pagos_origen_estado_idx');
            $table->dropColumn('origen_pago');
        });
    }
};
