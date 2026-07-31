<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Columna de configuración de estrategia de mora por producto financiero
     * (App\Domain\Mora\Strategies\MoraCalculationStrategy). Nullable: el
     * resolver ProductoFinanciero::moraStrategy() ya sabe caer en el
     * comportamiento legacy (grupal → flat, individual → porcentual) si esta
     * columna no está seteada.
     */
    public function up(): void
    {
        Schema::table('producto_financiero', function (Blueprint $table) {
            $table->enum('tipo_calculo_mora', ['flat_por_integrante', 'porcentual_sobre_saldo'])
                ->nullable()
                ->after('tipo')
                ->comment('Estrategia de cálculo de mora seleccionada para este producto.');
        });

        // Backfill: preserva el comportamiento actual exacto para filas existentes.
        DB::table('producto_financiero')
            ->where('tipo', 'grupal')
            ->update(['tipo_calculo_mora' => 'flat_por_integrante']);

        DB::table('producto_financiero')
            ->where('tipo', '!=', 'grupal')
            ->update(['tipo_calculo_mora' => 'porcentual_sobre_saldo']);
    }

    public function down(): void
    {
        Schema::table('producto_financiero', function (Blueprint $table) {
            $table->dropColumn('tipo_calculo_mora');
        });
    }
};
