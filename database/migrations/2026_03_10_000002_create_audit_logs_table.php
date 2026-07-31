<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 0.2 — Tabla de auditoría polimórfica.
 *
 * Registra acciones críticas del sistema con trazabilidad completa:
 *   - Reversiones de pago (JO)
 *   - Condonaciones de mora (JC, JO)
 *   - Separaciones de clientes morosos (JC, JO)
 *   - Reagrupaciones parciales (JC, JO)
 *   - Cambios de estado de préstamos
 *
 * La relación polimórfica (auditable_type + auditable_id) permite
 * vincular el registro con cualquier modelo del sistema.
 *
 * auditable_type/auditable_id are nullable (laravel-security-hardening,
 * Slice 6 / design D8): an auth event with no resolvable model — a failed
 * login attempt against an email with no matching User — has nothing to
 * attach the polymorphic relation to. Rewritten in place per this project's
 * clean-core convention (no ALTER migration for a pre-prod schema change);
 * see 2026_05_26_172145_make_user_id_nullable_in_audit_logs.php for the
 * unrelated, pre-existing `user_id` nullable change, left untouched.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('accion', 100);          // 'revertir_pago', 'condonar_mora', etc.
            $table->string('auditable_type')->nullable();        // Modelo afectado (App\Models\Pago, etc.) — null for model-less auth events
            $table->unsignedBigInteger('auditable_id')->nullable(); // ID del modelo afectado — null for model-less auth events
            $table->json('datos_anteriores')->nullable(); // Estado previo al cambio
            $table->json('datos_nuevos')->nullable();     // Estado posterior al cambio
            $table->text('motivo')->nullable();            // Justificación obligatoria
            $table->string('ip', 45)->nullable();          // IPv4/IPv6 del usuario
            $table->timestamps();

            // Índices para consultas frecuentes
            $table->index(['auditable_type', 'auditable_id'], 'audit_auditable_index');
            $table->index(['accion', 'created_at'], 'audit_accion_fecha_index');
            $table->index('user_id', 'audit_user_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
