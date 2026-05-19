<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Composite index para el patrón asesor_id + estado_grupo
        // Usado en: getDashboardStatsAsesor, getGruposActivosPorAsesor, CuotasResource, CuotasVigentesWidget
        Schema::table('grupos', function (Blueprint $table) {
            $table->index(['asesor_id', 'estado_grupo'], 'grupos_asesor_estado_idx');
        });

        // Índice explícito en moras.cuota_grupal_id
        // InnoDB crea un índice implícito en FKs, pero no siempre se garantiza
        // en migraciones con foreignId()->nullable(). Este lo hace explícito y visible.
        Schema::table('moras', function (Blueprint $table) {
            $table->index('cuota_grupal_id', 'moras_cuota_grupal_idx');
        });

        // Índice en prestamos.tipo (grupal / individual)
        // Necesario para filtros de préstamos individuales (separación de morosos)
        Schema::table('prestamos', function (Blueprint $table) {
            $table->index('tipo', 'prestamos_tipo_idx');
        });
    }

    public function down(): void
    {
        Schema::table('grupos', function (Blueprint $table) {
            $table->dropIndex('grupos_asesor_estado_idx');
        });

        Schema::table('moras', function (Blueprint $table) {
            $table->dropIndex('moras_cuota_grupal_idx');
        });

        Schema::table('prestamos', function (Blueprint $table) {
            $table->dropIndex('prestamos_tipo_idx');
        });
    }
};
