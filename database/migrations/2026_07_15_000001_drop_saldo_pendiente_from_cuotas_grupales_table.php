<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Elimina la columna legacy write-only cuotas_grupales.saldo_pendiente
     * (SDD core-contable-seguridad, Req 4.1). Desde Slice B1 ningún cálculo de
     * saldo la lee (SaldoCuotaService deriva únicamente de monto_cuota_grupal
     * y los Pago aprobados); todos los lectores/escritores restantes fueron
     * migrados en este mismo slice (tareas 4.5, verificados via grep sweep
     * completo de app/, tests/ y database/) antes de este DROP.
     *
     * Ordering-critical (Req 6.3): el comando retanqueo:auditar-saldos ya
     * corrió contra el estado pre-drop y su evidencia quedó capturada
     * (apply-progress + docblock del comando) antes de aplicar esta migración.
     */
    public function up(): void
    {
        Schema::table('cuotas_grupales', function (Blueprint $table) {
            $table->dropColumn('saldo_pendiente');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cuotas_grupales', function (Blueprint $table) {
            $table->decimal('saldo_pendiente', 8, 2)->nullable();
        });
    }
};
