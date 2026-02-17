<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Servicio de Caché Centralizado
 * 
 * Este servicio maneja el caché de consultas frecuentes para mejorar
 * el rendimiento del frontend. Está diseñado para funcionar tanto
 * con File cache como con Redis.
 * 
 * PREPARACIÓN PARA REDIS:
 * Para migrar a Redis, solo necesita cambiar en .env:
 * - CACHE_STORE=redis
 * - Configurar REDIS_HOST, REDIS_PORT, REDIS_PASSWORD
 * 
 * @version 1.0.0
 */
class CacheService
{
    /**
     * Prefijo para todas las claves de caché de la aplicación
     */
    private const CACHE_PREFIX = 'ec_';

    /**
     * TTL por defecto en segundos (5 minutos)
     */
    private const DEFAULT_TTL = 300;

    /**
     * TTL largo para datos que cambian poco (1 hora)
     */
    private const LONG_TTL = 3600;

    /**
     * TTL corto para datos que cambian frecuentemente (1 minuto)
     */
    private const SHORT_TTL = 60;

    // =====================================================
    // ASESORES
    // =====================================================

    /**
     * Obtiene la lista de asesores activos
     * 
     * @return \Illuminate\Support\Collection
     */
    public static function getAsesoresActivos()
    {
        return Cache::remember(
            self::CACHE_PREFIX . 'asesores_activos',
            self::DEFAULT_TTL,
            function () {
                return \App\Models\Asesor::with('persona:id,nombre,apellidos')
                    ->where('estado', 'Activo')
                    ->get(['id', 'persona_id', 'user_id', 'estado']);
            }
        );
    }

    /**
     * Obtiene un asesor por su user_id (para el usuario logueado)
     * 
     * @param int $userId
     * @return \App\Models\Asesor|null
     */
    public static function getAsesorByUserId(int $userId)
    {
        return Cache::remember(
            self::CACHE_PREFIX . "asesor_user_{$userId}",
            self::DEFAULT_TTL,
            function () use ($userId) {
                return \App\Models\Asesor::where('user_id', $userId)->first();
            }
        );
    }

    // =====================================================
    // GRUPOS
    // =====================================================

    /**
     * Obtiene grupos activos por asesor (para selects)
     * 
     * @param int $asesorId
     * @return \Illuminate\Support\Collection
     */
    public static function getGruposActivosPorAsesor(int $asesorId)
    {
        return Cache::remember(
            self::CACHE_PREFIX . "grupos_asesor_{$asesorId}",
            self::SHORT_TTL,
            function () use ($asesorId) {
                return \App\Models\Grupo::where('asesor_id', $asesorId)
                    ->where('estado_grupo', 'Activo')
                    ->orderBy('nombre_grupo')
                    ->pluck('nombre_grupo', 'id');
            }
        );
    }

    /**
     * Obtiene todos los grupos activos (para admin/jefes)
     * 
     * @return \Illuminate\Support\Collection
     */
    public static function getGruposActivos()
    {
        return Cache::remember(
            self::CACHE_PREFIX . 'grupos_activos',
            self::SHORT_TTL,
            function () {
                return \App\Models\Grupo::where('estado_grupo', 'Activo')
                    ->orderBy('nombre_grupo')
                    ->pluck('nombre_grupo', 'id');
            }
        );
    }

    // =====================================================
    // PRODUCTOS FINANCIEROS
    // =====================================================

    /**
     * Obtiene productos financieros activos
     * Cambia poco, usa TTL largo
     * 
     * @return \Illuminate\Support\Collection
     */
    public static function getProductosFinancieros()
    {
        return Cache::remember(
            self::CACHE_PREFIX . 'productos_financieros',
            self::LONG_TTL,
            function () {
                return \App\Models\ProductoFinanciero::where('activo', true)
                    ->orderBy('nombre')
                    ->get(['id', 'nombre', 'tasa_interes', 'descripcion']);
            }
        );
    }

    // =====================================================
    // CICLOS Y MONTOS (datos estáticos, cache muy largo)
    // =====================================================

    /**
     * Obtiene la configuración de montos por ciclo
     * Estos datos son casi estáticos
     * 
     * @return array
     */
    public static function getMontosPorCiclo()
    {
        return Cache::remember(
            self::CACHE_PREFIX . 'montos_ciclo',
            self::LONG_TTL * 24, // 24 horas
            function () {
                return [
                    'I' => [400],
                    'II' => [400, 500, 600],
                    'III' => [400, 500, 600, 700, 800],
                    'IV' => [400, 500, 600, 700, 800, 900, 1000],
                ];
            }
        );
    }

    /**
     * Obtiene la configuración de seguros por monto
     * 
     * @return array
     */
    public static function getSegurosPorMonto()
    {
        return Cache::remember(
            self::CACHE_PREFIX . 'seguros_monto',
            self::LONG_TTL * 24, // 24 horas
            function () {
                return [
                    400 => 8,
                    500 => 9,
                    600 => 9,
                    700 => 10,
                    800 => 10,
                    900 => 11,
                    1000 => 11,
                    1100 => 12,
                    1200 => 12,
                    1300 => 13,
                    1400 => 13,
                    1500 => 13,
                ];
            }
        );
    }

    // =====================================================
    // ESTADÍSTICAS DEL DASHBOARD
    // =====================================================

    /**
     * Obtiene estadísticas generales para el dashboard
     * 
     * @param int|null $asesorId - Si es null, obtiene todas
     * @return array
     */
    public static function getEstadisticasDashboard(?int $asesorId = null)
    {
        $cacheKey = $asesorId
            ? self::CACHE_PREFIX . "stats_asesor_{$asesorId}"
            : self::CACHE_PREFIX . 'stats_global';

        return Cache::remember(
            $cacheKey,
            self::SHORT_TTL,
            function () use ($asesorId) {
                $prestamosQuery = \App\Models\Prestamo::query();
                $gruposQuery = \App\Models\Grupo::query();
                $clientesQuery = \App\Models\Cliente::query();

                if ($asesorId) {
                    $prestamosQuery->whereHas('grupo', fn($q) => $q->where('asesor_id', $asesorId));
                    $gruposQuery->where('asesor_id', $asesorId);
                    $clientesQuery->whereHas('grupos', fn($q) => $q->where('asesor_id', $asesorId));
                }

                return [
                    'prestamos_activos' => (clone $prestamosQuery)->where('estado', 'Activo')->count(),
                    'prestamos_pendientes' => (clone $prestamosQuery)->where('estado', 'Pendiente')->count(),
                    'grupos_activos' => $gruposQuery->where('estado_grupo', 'Activo')->count(),
                    'total_clientes' => $clientesQuery->count(),
                    'monto_colocado' => (clone $prestamosQuery)
                        ->whereIn('estado', ['Activo', 'Desembolsado', 'Ejecutado'])
                        ->sum('monto_prestado_total'),
                ];
            }
        );
    }

    // =====================================================
    // INVALIDACIÓN DE CACHÉ
    // =====================================================

    /**
     * Invalida el caché relacionado a un asesor específico
     * Llamar cuando se modifica un asesor, sus grupos o préstamos
     * 
     * @param int $asesorId
     */
    public static function invalidateAsesorCache(int $asesorId): void
    {
        Cache::forget(self::CACHE_PREFIX . "grupos_asesor_{$asesorId}");
        Cache::forget(self::CACHE_PREFIX . "stats_asesor_{$asesorId}");
        Cache::forget(self::CACHE_PREFIX . 'asesores_activos');

        Log::debug("CacheService: Invalidated cache for asesor {$asesorId}");
    }

    /**
     * Invalida el caché de un usuario (asesor por user_id)
     * 
     * @param int $userId
     */
    public static function invalidateUserCache(int $userId): void
    {
        Cache::forget(self::CACHE_PREFIX . "asesor_user_{$userId}");

        Log::debug("CacheService: Invalidated cache for user {$userId}");
    }

    /**
     * Invalida el caché global de grupos
     * Llamar cuando se crea, modifica o elimina un grupo
     */
    public static function invalidateGruposCache(): void
    {
        Cache::forget(self::CACHE_PREFIX . 'grupos_activos');

        Log::debug("CacheService: Invalidated global grupos cache");
    }

    /**
     * Invalida el caché de productos financieros
     */
    public static function invalidateProductosCache(): void
    {
        Cache::forget(self::CACHE_PREFIX . 'productos_financieros');

        Log::debug("CacheService: Invalidated productos financieros cache");
    }

    /**
     * Invalida todas las estadísticas del dashboard
     */
    public static function invalidateStatsCache(): void
    {
        Cache::forget(self::CACHE_PREFIX . 'stats_global');
        // También podríamos invalidar stats de asesores individuales si es necesario

        Log::debug("CacheService: Invalidated stats cache");
    }

    /**
     * Limpia TODO el caché de la aplicación
     * ⚠️ Usar con cuidado en producción
     */
    public static function clearAllCache(): void
    {
        Cache::flush();

        Log::warning("CacheService: ALL cache cleared");
    }

    // =====================================================
    // UTILIDADES
    // =====================================================

    /**
     * Obtiene información sobre el estado del caché
     * Útil para debugging
     * 
     * @return array
     */
    public static function getCacheInfo(): array
    {
        return [
            'driver' => config('cache.default'),
            'prefix' => self::CACHE_PREFIX,
            'default_ttl' => self::DEFAULT_TTL,
            'is_redis' => config('cache.default') === 'redis',
            'ready_for_redis' => true,
        ];
    }
}
