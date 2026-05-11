<?php

namespace App\Services;

use App\Domain\Mora\MoraSemaforo;
use App\Models\BusinessRuleConfig;
use App\Models\CuotaIndividual;
use Illuminate\Support\Facades\Cache;

/**
 * Servicio de acceso a las reglas de negocio configurables.
 *
 * Lee los valores desde la tabla `business_rule_configs` con caché
 * de corta duración para evitar N+1 en vistas con muchos registros.
 *
 * Toda la lógica de semáforo de mora pasa por aquí para que el
 * Súper Admin pueda cambiar los umbrales sin tocar código.
 */
class BusinessRuleService
{
    private const CACHE_TTL = 300; // 5 minutos

    // ── Acceso genérico ───────────────────────────────────────────────────

    /**
     * Obtiene una regla de negocio por clave con caché.
     */
    public function get(string $clave, mixed $default = null): mixed
    {
        return Cache::remember("business_rule.{$clave}", self::CACHE_TTL, function () use ($clave, $default) {
            $config = BusinessRuleConfig::where('clave', $clave)->first();
            return $config ? $config->valor_casteado : $default;
        });
    }

    /**
     * Invalida la caché de una regla (llamar al actualizar desde el panel).
     */
    public function invalidar(string $clave): void
    {
        Cache::forget("business_rule.{$clave}");
    }

    // ── Reglas de Mora ────────────────────────────────────────────────────

    public function umbralLevesDias(): int
    {
        return (int) $this->get('mora.umbral_leve_dias', 1);
    }

    public function umbralMediaDias(): int
    {
        return (int) $this->get('mora.umbral_media_dias', 7);
    }

    public function umbralCriticaDias(): int
    {
        return (int) $this->get('mora.umbral_critica_dias', 15);
    }

    public function umbralGraveDias(): int
    {
        return (int) $this->get('mora.umbral_grave_dias', 30);
    }

    public function umbralRecuperacionDias(): int
    {
        return (int) $this->get('mora.umbral_recuperacion_dias', 60);
    }

    // ── Reglas de Separación ──────────────────────────────────────────────

    public function cuotasParaSeparar(): int
    {
        return (int) $this->get('separacion.cuotas_para_separar', 1);
    }

    // ── Reglas de Ciclo de Crédito ────────────────────────────────────────

    public function penalizarPorMora(): bool
    {
        return (bool) $this->get('ciclo.penalizar_por_mora', true);
    }

    public function reiniciarCicloUnoEnRecuperacion(): bool
    {
        return (bool) $this->get('ciclo.reiniciar_a_ciclo_uno_por_recuperacion', true);
    }

    // ── Integración con MoraSemaforo ──────────────────────────────────────

    /**
     * Clasifica los días de atraso usando los umbrales configurados.
     * Punto de entrada único para la clasificación de mora en toda la app.
     */
    public function clasificarMora(int $diasAtraso): MoraSemaforo
    {
        return MoraSemaforo::clasificar(
            diasAtraso:          $diasAtraso,
            umbralMedia:         $this->umbralMediaDias(),
            umbralCritica:       $this->umbralCriticaDias(),
            umbralGrave:         $this->umbralGraveDias(),
            umbralRecuperacion:  $this->umbralRecuperacionDias(),
        );
    }

    /**
     * Clasifica la mora de una CuotaIndividual directamente.
     */
    public function clasificarCuota(CuotaIndividual $cuota): MoraSemaforo
    {
        return $this->clasificarMora($cuota->diasAtraso());
    }
}
