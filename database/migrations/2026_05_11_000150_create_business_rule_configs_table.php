<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_rule_configs', function (Blueprint $table) {
            $table->id();
            $table->string('clave')->unique()->comment('Identificador de la regla, ej: mora.umbral_media');
            $table->string('valor')->comment('Valor de la regla (siempre string, castear en el modelo)');
            $table->string('tipo')->default('integer')->comment('Tipo del valor: integer, boolean, decimal, string');
            $table->string('descripcion')->nullable()->comment('Descripción legible para el Súper Admin');
            $table->string('grupo')->default('general')->comment('Agrupación en el panel: mora, retanqueo, credito');
            $table->timestamps();
        });

        // Seed: valores por defecto de las reglas de negocio
        DB::table('business_rule_configs')->insert([
            // ── Semáforo de mora ─────────────────────────────────────────
            [
                'clave'       => 'mora.umbral_leve_dias',
                'valor'       => '1',
                'tipo'        => 'integer',
                'descripcion' => 'Días de atraso a partir de los cuales se activa alerta LEVE',
                'grupo'       => 'mora',
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
            [
                'clave'       => 'mora.umbral_media_dias',
                'valor'       => '7',
                'tipo'        => 'integer',
                'descripcion' => 'Días de atraso para alerta MEDIA (cobranza activa del asesor)',
                'grupo'       => 'mora',
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
            [
                'clave'       => 'mora.umbral_critica_dias',
                'valor'       => '15',
                'tipo'        => 'integer',
                'descripcion' => 'Días de atraso para alerta CRÍTICA (notificación a JO/JC)',
                'grupo'       => 'mora',
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
            [
                'clave'       => 'mora.umbral_grave_dias',
                'valor'       => '30',
                'tipo'        => 'integer',
                'descripcion' => 'Días de atraso para alerta GRAVE (evaluación de separación)',
                'grupo'       => 'mora',
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
            [
                'clave'       => 'mora.umbral_recuperacion_dias',
                'valor'       => '60',
                'tipo'        => 'integer',
                'descripcion' => 'Días de atraso para habilitar el flujo de Pago por Recuperación (liquidación/refinanciamiento)',
                'grupo'       => 'mora',
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
            // ── Separación de integrantes ─────────────────────────────────
            [
                'clave'       => 'separacion.cuotas_para_separar',
                'valor'       => '1',
                'tipo'        => 'integer',
                'descripcion' => 'Número de cuotas grupales pendientes de un integrante para permitir su separación',
                'grupo'       => 'separacion',
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
            // ── Ciclos de crédito ─────────────────────────────────────────
            [
                'clave'       => 'ciclo.penalizar_por_mora',
                'valor'       => '1',
                'tipo'        => 'boolean',
                'descripcion' => 'Si es true, un cliente con mora en su préstamo anterior NO sube de ciclo al finalizar',
                'grupo'       => 'ciclo',
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
            [
                'clave'       => 'ciclo.reiniciar_a_ciclo_uno_por_recuperacion',
                'valor'       => '1',
                'tipo'        => 'boolean',
                'descripcion' => 'Si es true, clientes que pasan por recuperación (refinanciamiento) vuelven al Ciclo I',
                'grupo'       => 'ciclo',
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('business_rule_configs');
    }
};
