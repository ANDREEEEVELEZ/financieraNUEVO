<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Migration de optimización de performance
 * 
 * Agrega índices a las tablas más consultadas del sistema para mejorar
 * el rendimiento de las consultas frecuentes.
 * 
 * Verifica la existencia de cada tabla e índice antes de modificar para evitar errores.
 */
return new class extends Migration {
    /**
     * Helper para verificar si un índice existe
     */
    private function indexExists(string $table, string $indexName): bool
    {
        if (!Schema::hasTable($table)) {
            return false;
        }

        $indexes = Schema::getIndexes($table);
        foreach ($indexes as $index) {
            if ($index['name'] === $indexName) {
                return true;
            }
        }
        return false;
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Índices para tabla prestamos
        if (Schema::hasTable('prestamos')) {
            Schema::table('prestamos', function (Blueprint $table) {
                if (!$this->indexExists('prestamos', 'prestamos_grupo_estado_idx')) {
                    $table->index(['grupo_id', 'estado'], 'prestamos_grupo_estado_idx');
                }
                if (!$this->indexExists('prestamos', 'prestamos_estado_idx')) {
                    $table->index('estado', 'prestamos_estado_idx');
                }
                if (!$this->indexExists('prestamos', 'prestamos_fecha_idx')) {
                    $table->index('fecha_prestamo', 'prestamos_fecha_idx');
                }
            });
        }

        // Índices para tabla cuotas_grupales
        if (Schema::hasTable('cuotas_grupales')) {
            Schema::table('cuotas_grupales', function (Blueprint $table) {
                if (!$this->indexExists('cuotas_grupales', 'cuotas_grupales_prestamo_estado_idx')) {
                    $table->index(['prestamo_id', 'estado_cuota_grupal'], 'cuotas_grupales_prestamo_estado_idx');
                }
                if (!$this->indexExists('cuotas_grupales', 'cuotas_grupales_vencimiento_pago_idx')) {
                    $table->index(['fecha_vencimiento', 'estado_pago'], 'cuotas_grupales_vencimiento_pago_idx');
                }
                if (!$this->indexExists('cuotas_grupales', 'cuotas_grupales_estado_pago_idx')) {
                    $table->index('estado_pago', 'cuotas_grupales_estado_pago_idx');
                }
                if (!$this->indexExists('cuotas_grupales', 'cuotas_grupales_estado_cuota_idx')) {
                    $table->index('estado_cuota_grupal', 'cuotas_grupales_estado_cuota_idx');
                }
            });
        }

        // Índices para tabla pagos
        if (Schema::hasTable('pagos')) {
            Schema::table('pagos', function (Blueprint $table) {
                if (!$this->indexExists('pagos', 'pagos_cuota_estado_idx')) {
                    $table->index(['cuota_grupal_id', 'estado_pago'], 'pagos_cuota_estado_idx');
                }
                if (!$this->indexExists('pagos', 'pagos_fecha_idx')) {
                    $table->index('fecha_pago', 'pagos_fecha_idx');
                }
                if (!$this->indexExists('pagos', 'pagos_estado_idx')) {
                    $table->index('estado_pago', 'pagos_estado_idx');
                }
            });
        }

        // Índices para tabla clientes
        if (Schema::hasTable('clientes')) {
            Schema::table('clientes', function (Blueprint $table) {
                if (!$this->indexExists('clientes', 'clientes_asesor_idx')) {
                    $table->index('asesor_id', 'clientes_asesor_idx');
                }
                if (!$this->indexExists('clientes', 'clientes_estado_idx')) {
                    $table->index('estado_cliente', 'clientes_estado_idx');
                }
            });
        }

        // Índices para tabla grupos
        if (Schema::hasTable('grupos')) {
            Schema::table('grupos', function (Blueprint $table) {
                if (!$this->indexExists('grupos', 'grupos_asesor_idx')) {
                    $table->index('asesor_id', 'grupos_asesor_idx');
                }
                if (!$this->indexExists('grupos', 'grupos_estado_idx')) {
                    $table->index('estado_grupo', 'grupos_estado_idx');
                }
            });
        }

        // Índices para tabla prestamo_individual
        if (Schema::hasTable('prestamo_individual')) {
            Schema::table('prestamo_individual', function (Blueprint $table) {
                if (!$this->indexExists('prestamo_individual', 'prestamo_individual_prestamo_cliente_idx')) {
                    $table->index(['prestamo_id', 'cliente_id'], 'prestamo_individual_prestamo_cliente_idx');
                }
                if (!$this->indexExists('prestamo_individual', 'prestamo_individual_cliente_idx')) {
                    $table->index('cliente_id', 'prestamo_individual_cliente_idx');
                }
            });
        }

        // Índices para tabla moras
        if (Schema::hasTable('moras')) {
            Schema::table('moras', function (Blueprint $table) {
                if (!$this->indexExists('moras', 'moras_estado_idx')) {
                    $table->index('estado_mora', 'moras_estado_idx');
                }
            });
        }

        // Índices para tabla grupo_cliente
        if (Schema::hasTable('grupo_cliente')) {
            Schema::table('grupo_cliente', function (Blueprint $table) {
                if (!$this->indexExists('grupo_cliente', 'grupo_cliente_grupo_estado_idx')) {
                    $table->index(['grupo_id', 'estado_grupo_cliente'], 'grupo_cliente_grupo_estado_idx');
                }
                if (!$this->indexExists('grupo_cliente', 'grupo_cliente_cliente_idx')) {
                    $table->index('cliente_id', 'grupo_cliente_cliente_idx');
                }
            });
        }

        // Índices para tabla detalles_pago (solo si existe)
        if (Schema::hasTable('detalles_pago')) {
            Schema::table('detalles_pago', function (Blueprint $table) {
                if (!$this->indexExists('detalles_pago', 'detalles_pago_pago_idx')) {
                    $table->index('pago_id', 'detalles_pago_pago_idx');
                }
                if (!$this->indexExists('detalles_pago', 'detalles_pago_prestamo_individual_idx')) {
                    $table->index('prestamo_individual_id', 'detalles_pago_prestamo_individual_idx');
                }
            });
        }

        // Índices para tabla ingresos
        if (Schema::hasTable('ingresos')) {
            Schema::table('ingresos', function (Blueprint $table) {
                if (!$this->indexExists('ingresos', 'ingresos_fecha_idx')) {
                    $table->index('fecha_hora', 'ingresos_fecha_idx');
                }
                if (!$this->indexExists('ingresos', 'ingresos_tipo_idx')) {
                    $table->index('tipo_ingreso', 'ingresos_tipo_idx');
                }
            });
        }

        // Índices para tabla egresos
        if (Schema::hasTable('egresos')) {
            Schema::table('egresos', function (Blueprint $table) {
                if (!$this->indexExists('egresos', 'egresos_fecha_idx')) {
                    $table->index('fecha', 'egresos_fecha_idx');
                }
                if (!$this->indexExists('egresos', 'egresos_tipo_idx')) {
                    $table->index('tipo_egreso', 'egresos_tipo_idx');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('prestamos')) {
            Schema::table('prestamos', function (Blueprint $table) {
                if ($this->indexExists('prestamos', 'prestamos_grupo_estado_idx')) {
                    $table->dropIndex('prestamos_grupo_estado_idx');
                }
                if ($this->indexExists('prestamos', 'prestamos_estado_idx')) {
                    $table->dropIndex('prestamos_estado_idx');
                }
                if ($this->indexExists('prestamos', 'prestamos_fecha_idx')) {
                    $table->dropIndex('prestamos_fecha_idx');
                }
            });
        }

        if (Schema::hasTable('cuotas_grupales')) {
            Schema::table('cuotas_grupales', function (Blueprint $table) {
                if ($this->indexExists('cuotas_grupales', 'cuotas_grupales_prestamo_estado_idx')) {
                    $table->dropIndex('cuotas_grupales_prestamo_estado_idx');
                }
                if ($this->indexExists('cuotas_grupales', 'cuotas_grupales_vencimiento_pago_idx')) {
                    $table->dropIndex('cuotas_grupales_vencimiento_pago_idx');
                }
                if ($this->indexExists('cuotas_grupales', 'cuotas_grupales_estado_pago_idx')) {
                    $table->dropIndex('cuotas_grupales_estado_pago_idx');
                }
                if ($this->indexExists('cuotas_grupales', 'cuotas_grupales_estado_cuota_idx')) {
                    $table->dropIndex('cuotas_grupales_estado_cuota_idx');
                }
            });
        }

        if (Schema::hasTable('pagos')) {
            Schema::table('pagos', function (Blueprint $table) {
                if ($this->indexExists('pagos', 'pagos_cuota_estado_idx')) {
                    $table->dropIndex('pagos_cuota_estado_idx');
                }
                if ($this->indexExists('pagos', 'pagos_fecha_idx')) {
                    $table->dropIndex('pagos_fecha_idx');
                }
                if ($this->indexExists('pagos', 'pagos_estado_idx')) {
                    $table->dropIndex('pagos_estado_idx');
                }
            });
        }

        if (Schema::hasTable('clientes')) {
            Schema::table('clientes', function (Blueprint $table) {
                if ($this->indexExists('clientes', 'clientes_asesor_idx')) {
                    $table->dropIndex('clientes_asesor_idx');
                }
                if ($this->indexExists('clientes', 'clientes_estado_idx')) {
                    $table->dropIndex('clientes_estado_idx');
                }
            });
        }

        if (Schema::hasTable('grupos')) {
            Schema::table('grupos', function (Blueprint $table) {
                if ($this->indexExists('grupos', 'grupos_asesor_idx')) {
                    $table->dropIndex('grupos_asesor_idx');
                }
                if ($this->indexExists('grupos', 'grupos_estado_idx')) {
                    $table->dropIndex('grupos_estado_idx');
                }
            });
        }

        if (Schema::hasTable('prestamo_individual')) {
            Schema::table('prestamo_individual', function (Blueprint $table) {
                if ($this->indexExists('prestamo_individual', 'prestamo_individual_prestamo_cliente_idx')) {
                    $table->dropIndex('prestamo_individual_prestamo_cliente_idx');
                }
                if ($this->indexExists('prestamo_individual', 'prestamo_individual_cliente_idx')) {
                    $table->dropIndex('prestamo_individual_cliente_idx');
                }
            });
        }

        if (Schema::hasTable('moras')) {
            Schema::table('moras', function (Blueprint $table) {
                if ($this->indexExists('moras', 'moras_estado_idx')) {
                    $table->dropIndex('moras_estado_idx');
                }
            });
        }

        if (Schema::hasTable('grupo_cliente')) {
            Schema::table('grupo_cliente', function (Blueprint $table) {
                if ($this->indexExists('grupo_cliente', 'grupo_cliente_grupo_estado_idx')) {
                    $table->dropIndex('grupo_cliente_grupo_estado_idx');
                }
                if ($this->indexExists('grupo_cliente', 'grupo_cliente_cliente_idx')) {
                    $table->dropIndex('grupo_cliente_cliente_idx');
                }
            });
        }

        if (Schema::hasTable('detalles_pago')) {
            Schema::table('detalles_pago', function (Blueprint $table) {
                if ($this->indexExists('detalles_pago', 'detalles_pago_pago_idx')) {
                    $table->dropIndex('detalles_pago_pago_idx');
                }
                if ($this->indexExists('detalles_pago', 'detalles_pago_prestamo_individual_idx')) {
                    $table->dropIndex('detalles_pago_prestamo_individual_idx');
                }
            });
        }

        if (Schema::hasTable('ingresos')) {
            Schema::table('ingresos', function (Blueprint $table) {
                if ($this->indexExists('ingresos', 'ingresos_fecha_idx')) {
                    $table->dropIndex('ingresos_fecha_idx');
                }
                if ($this->indexExists('ingresos', 'ingresos_tipo_idx')) {
                    $table->dropIndex('ingresos_tipo_idx');
                }
            });
        }

        if (Schema::hasTable('egresos')) {
            Schema::table('egresos', function (Blueprint $table) {
                if ($this->indexExists('egresos', 'egresos_fecha_idx')) {
                    $table->dropIndex('egresos_fecha_idx');
                }
                if ($this->indexExists('egresos', 'egresos_tipo_idx')) {
                    $table->dropIndex('egresos_tipo_idx');
                }
            });
        }
    }
};
