<?php

namespace App\Contracts;

/**
 * Contract for the centralised cache service.
 *
 * All callers must depend on this interface, not on the concrete CacheService.
 * Category-A callers (DI-resolved): constructor injection.
 * Category-B callers (Filament): app(CacheServiceInterface::class) at call site.
 */
interface CacheServiceInterface
{
    // ── Asesores ─────────────────────────────────────────────────────────────

    public function getAsesoresActivos();

    public function getAsesorByUserId(int $userId);

    // ── Grupos ────────────────────────────────────────────────────────────────

    public function getGruposActivosPorAsesor(int $asesorId);

    public function getGruposActivos();

    // ── Productos financieros ─────────────────────────────────────────────────

    public function getProductosFinancieros();

    // ── Ciclos y montos ───────────────────────────────────────────────────────

    public function getMontosPorCiclo();

    public function getSegurosPorMonto();

    // ── Estadísticas del dashboard ────────────────────────────────────────────

    public function getEstadisticasDashboard(?int $asesorId = null);

    public function getDashboardStatsAsesor(int $asesorId): array;

    public function getDashboardStatsJO(): array;

    public function getDashboardStatsJC(): array;

    public function getDashboardStatsAdmin(): array;

    // ── Invalidación de caché ─────────────────────────────────────────────────

    public function invalidateDashboardCache(?int $asesorId = null): void;

    public function invalidateAsesorCache(int $asesorId): void;

    public function invalidateUserCache(int $userId): void;

    public function invalidateGruposCache(): void;

    public function invalidateProductosCache(): void;

    public function invalidateStatsCache(): void;

    public function clearAllCache(): void;

    // ── Utilidades ────────────────────────────────────────────────────────────

    public function getCacheInfo(): array;
}
