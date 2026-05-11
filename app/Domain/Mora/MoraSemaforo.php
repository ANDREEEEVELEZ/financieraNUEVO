<?php

namespace App\Domain\Mora;

/**
 * Value Object: Semáforo de días de atraso.
 *
 * Encapsula la lógica de clasificación de mora en niveles semánticos
 * según los días de atraso. Los umbrales son inyectados externamente
 * (desde BusinessRuleService) para ser configurables por el Súper Admin.
 *
 * Niveles:
 *   ⚪ NINGUNA    — 0 días (al día)
 *   🟡 LEVE       — 1–6 días
 *   🟠 MEDIA      — 7–14 días
 *   🔴 CRITICA    — 15–29 días
 *   🔴 GRAVE      — 30–59 días
 *   ⚫ RECUPERACION — 60+ días (habilita pago por recuperación)
 */
final class MoraSemaforo
{
    // Niveles del semáforo
    public const NINGUNA     = 'ninguna';
    public const LEVE        = 'leve';       // 1–6 días
    public const MEDIA       = 'media';      // 7–14 días
    public const CRITICA     = 'critica';    // 15–29 días
    public const GRAVE       = 'grave';      // 30–59 días
    public const RECUPERACION = 'recuperacion'; // 60+ días

    // Colores UI (Filament badge)
    public const COLORES = [
        self::NINGUNA      => 'success',
        self::LEVE         => 'warning',
        self::MEDIA        => 'warning',
        self::CRITICA      => 'danger',
        self::GRAVE        => 'danger',
        self::RECUPERACION => 'gray',
    ];

    // Iconos UI
    public const ICONOS = [
        self::NINGUNA      => 'heroicon-o-check-circle',
        self::LEVE         => 'heroicon-o-exclamation-circle',
        self::MEDIA        => 'heroicon-o-exclamation-triangle',
        self::CRITICA      => 'heroicon-o-x-circle',
        self::GRAVE        => 'heroicon-o-fire',
        self::RECUPERACION => 'heroicon-o-archive-box-x-mark',
    ];

    private function __construct(
        public readonly string $nivel,
        public readonly int    $diasAtraso,
    ) {}

    /**
     * Fábrica: clasifica los días de atraso en un nivel del semáforo.
     *
     * @param int $diasAtraso       Días de atraso calculados.
     * @param int $umbralMedia      Umbral para nivel MEDIA (default: 7).
     * @param int $umbralCritica    Umbral para nivel CRITICA (default: 15).
     * @param int $umbralGrave      Umbral para nivel GRAVE (default: 30).
     * @param int $umbralRecuperacion Umbral para RECUPERACION (default: 60).
     */
    public static function clasificar(
        int $diasAtraso,
        int $umbralMedia       = 7,
        int $umbralCritica     = 15,
        int $umbralGrave       = 30,
        int $umbralRecuperacion = 60,
    ): self {
        $nivel = match (true) {
            $diasAtraso <= 0                      => self::NINGUNA,
            $diasAtraso < $umbralMedia            => self::LEVE,
            $diasAtraso < $umbralCritica          => self::MEDIA,
            $diasAtraso < $umbralGrave            => self::CRITICA,
            $diasAtraso < $umbralRecuperacion     => self::GRAVE,
            default                               => self::RECUPERACION,
        };

        return new self($nivel, $diasAtraso);
    }

    /** ¿Está al día? */
    public function estaAlDia(): bool
    {
        return $this->nivel === self::NINGUNA;
    }

    /** ¿Habilita el flujo de pago por recuperación? */
    public function requiereRecuperacion(): bool
    {
        return $this->nivel === self::RECUPERACION;
    }

    /** ¿Debe disparar una alerta de cobranza al asesor? */
    public function requiereAccion(): bool
    {
        return $this->nivel !== self::NINGUNA;
    }

    /** Color del badge para Filament. */
    public function color(): string
    {
        return self::COLORES[$this->nivel] ?? 'gray';
    }

    /** Icono Heroicon para Filament. */
    public function icono(): string
    {
        return self::ICONOS[$this->nivel] ?? 'heroicon-o-question-mark-circle';
    }

    /** Etiqueta legible para la UI. */
    public function etiqueta(): string
    {
        return match ($this->nivel) {
            self::NINGUNA      => 'Al día',
            self::LEVE         => "Leve ({$this->diasAtraso}d)",
            self::MEDIA        => "Media ({$this->diasAtraso}d) — Alertar",
            self::CRITICA      => "Crítica ({$this->diasAtraso}d) — Gestionar",
            self::GRAVE        => "Grave ({$this->diasAtraso}d) — Urgente",
            self::RECUPERACION => "Recuperación ({$this->diasAtraso}d) — Acción legal",
            default            => 'Desconocido',
        };
    }

    public function toArray(): array
    {
        return [
            'nivel'       => $this->nivel,
            'dias_atraso' => $this->diasAtraso,
            'color'       => $this->color(),
            'icono'       => $this->icono(),
            'etiqueta'    => $this->etiqueta(),
        ];
    }
}
