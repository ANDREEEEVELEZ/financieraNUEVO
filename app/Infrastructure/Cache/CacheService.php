<?php

namespace App\Infrastructure\Cache;

use App\Contracts\CacheServiceInterface;
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
class CacheService implements CacheServiceInterface
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

    /**
     * Tiempo máximo de espera para adquirir el lock (segundos)
     */
    private const LOCK_WAIT = 5;

    /**
     * Cache::remember con double-check locking para prevenir cache stampede.
     * Usar solo para claves compartidas entre múltiples usuarios del mismo rol.
     *
     * @template T
     * @param string   $key
     * @param int      $ttl
     * @param \Closure $callback
     * @return T
     */
    private function rememberWithLock(string $key, int $ttl, \Closure $callback): mixed
    {
        $cached = Cache::get($key);
        if ($cached !== null) {
            return $cached;
        }

        return Cache::lock($key . ':lock', 10)->block(self::LOCK_WAIT, function () use ($key, $ttl, $callback) {
            $cached = Cache::get($key);
            if ($cached !== null) {
                return $cached;
            }
            $result = $callback();
            Cache::put($key, $result, $ttl);
            return $result;
        });
    }

    // =====================================================
    // ASESORES
    // =====================================================

    /**
     * Obtiene la lista de asesores activos
     *
     * @return \Illuminate\Support\Collection
     */
    public function getAsesoresActivos()
    {
        return Cache::remember(
            self::CACHE_PREFIX . 'asesores_activos',
            self::DEFAULT_TTL,
            function () {
                return \App\Models\Asesor::with('persona:id,nombre,apellidos')
                    ->where('estado_asesor', 'Activo')
                    ->get(['id', 'persona_id', 'user_id', 'estado_asesor']);
            }
        );
    }

    /**
     * Obtiene un asesor por su user_id (para el usuario logueado)
     *
     * @param int $userId
     * @return \App\Models\Asesor|null
     */
    public function getAsesorByUserId(int $userId)
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
    // SUPERVISORES
    // =====================================================

    /**
     * Returns the cached list of supervisor users.
     * Roles included: super_admin, Jefe de operaciones, Jefe de creditos.
     * Key: ec_supervisores | TTL: DEFAULT_TTL (300 s).
     * Invalidation is manual — supervisor role changes are rare and admin-driven.
     *
     * @return \Illuminate\Support\Collection<int, \App\Models\User>
     */
    public function getSupervisores(): \Illuminate\Support\Collection
    {
        return $this->rememberWithLock(
            self::CACHE_PREFIX . 'supervisores',
            self::DEFAULT_TTL,
            function () {
                return \App\Models\User::role(['super_admin', 'Jefe de operaciones', 'Jefe de creditos'])->get();
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
    public function getGruposActivosPorAsesor(int $asesorId)
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
    public function getGruposActivos()
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
    public function getProductosFinancieros()
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
    public function getMontosPorCiclo()
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
    public function getSegurosPorMonto()
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
    public function getEstadisticasDashboard(?int $asesorId = null)
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
                    'prestamos_activos' => (clone $prestamosQuery)->whereIn('estado', \App\Models\Prestamo::ESTADOS_ACTIVOS)->count(),
                    'prestamos_pendientes' => (clone $prestamosQuery)->where('estado', 'Pendiente')->count(),
                    'grupos_activos' => $gruposQuery->where('estado_grupo', 'Activo')->count(),
                    'total_clientes' => $clientesQuery->count(),
                    'monto_colocado' => (clone $prestamosQuery)
                        ->whereIn('estado', \App\Models\Prestamo::ESTADOS_ACTIVOS)
                        ->sum('monto_prestado_total'),
                ];
            }
        );
    }

    // =====================================================
    // KPIs DEL DASHBOARD POR ROL
    // =====================================================

    /**
     * Obtiene KPIs para el dashboard del Asesor
     *
     * @param int $asesorId
     * @return array
     */
    public function getDashboardStatsAsesor(int $asesorId): array
    {
        return Cache::remember(
            self::CACHE_PREFIX . "dashboard_asesor_{$asesorId}",
            self::DEFAULT_TTL,
            function () use ($asesorId) {
                $gruposActivos = \App\Models\Grupo::where('asesor_id', $asesorId)
                    ->where('estado_grupo', 'Activo')
                    ->count();

                // JOIN directo: elimina 4 EXISTS anidados por query
                $cuotasSemana = \App\Models\CuotasGrupales::join('prestamos', 'cuotas_grupales.prestamo_id', '=', 'prestamos.id')
                    ->join('grupos', 'prestamos.grupo_id', '=', 'grupos.id')
                    ->where('grupos.asesor_id', $asesorId)
                    ->whereIn('prestamos.estado', \App\Models\Prestamo::ESTADOS_ACTIVOS)
                    ->whereBetween('cuotas_grupales.fecha_vencimiento', [\Illuminate\Support\Carbon::now()->startOfDay(), \Illuminate\Support\Carbon::now()->addDays(7)->endOfDay()])
                    ->where('cuotas_grupales.estado_pago', '!=', 'pagado')
                    ->count();

                $montoCobradoMes = \App\Models\Pago::join('cuotas_grupales', 'pagos.cuota_grupal_id', '=', 'cuotas_grupales.id')
                    ->join('prestamos', 'cuotas_grupales.prestamo_id', '=', 'prestamos.id')
                    ->join('grupos', 'prestamos.grupo_id', '=', 'grupos.id')
                    ->where('grupos.asesor_id', $asesorId)
                    ->whereBetween('pagos.fecha_pago', [\Illuminate\Support\Carbon::now()->startOfMonth(), \Illuminate\Support\Carbon::now()->endOfMonth()])
                    ->where('pagos.estado_pago', 'aprobado')
                    ->sum('pagos.monto_pagado');

                // Contamos grupos directamente: más semántico y evita DISTINCT sobre prestamos
                $gruposEnMora = \App\Models\Grupo::where('asesor_id', $asesorId)
                    ->whereHas('prestamos', fn($q) => $q->where('estado', 'En_Mora'))
                    ->count();

                return [
                    'grupos_activos' => $gruposActivos,
                    'cuotas_semana' => $cuotasSemana,
                    'monto_cobrado_mes' => $montoCobradoMes,
                    'grupos_en_mora' => $gruposEnMora,
                ];
            }
        );
    }

    /**
     * Obtiene KPIs para el dashboard del Jefe de Operaciones
     *
     * @return array
     */
    public function getDashboardStatsJO(): array
    {
        return $this->rememberWithLock(
            self::CACHE_PREFIX . 'dashboard_jo',
            self::SHORT_TTL,
            function () {
                $pagosPendientes = \App\Models\Pago::where('estado_pago', 'pendiente')->count();

                $montoCobradoMes = \App\Models\Pago::whereBetween('fecha_pago', [\Illuminate\Support\Carbon::now()->startOfMonth(), \Illuminate\Support\Carbon::now()->endOfMonth()])
                    ->where('estado_pago', 'aprobado')
                    ->sum('monto_pagado');

                $montoDesembolsadoMes = \App\Models\Egreso::where('tipo_egreso', 'desembolso')
                    ->whereBetween('fecha', [\Illuminate\Support\Carbon::now()->startOfMonth(), \Illuminate\Support\Carbon::now()->endOfMonth()])
                    ->sum('monto');

                $gruposEnMora = \App\Models\Prestamo::where('estado', 'En_Mora')
                    ->distinct('grupo_id')
                    ->count();

                return [
                    'pagos_pendientes' => $pagosPendientes,
                    'monto_cobrado_mes' => $montoCobradoMes,
                    'monto_desembolsado_mes' => $montoDesembolsadoMes,
                    'grupos_en_mora' => $gruposEnMora,
                ];
            }
        );
    }

    /**
     * Obtiene KPIs para el dashboard del Jefe de Créditos
     *
     * @return array
     */
    public function getDashboardStatsJC(): array
    {
        return $this->rememberWithLock(
            self::CACHE_PREFIX . 'dashboard_jc',
            self::SHORT_TTL,
            function () {
                $solicitudesPendientes = \App\Models\Prestamo::where('estado', 'Pendiente')->count();

                $aprobadosSinFirmar = \App\Models\Prestamo::where('estado', 'Aprobado')->count();

                $carteraActiva = \App\Models\Prestamo::whereIn('estado', \App\Models\Prestamo::ESTADOS_ACTIVOS)
                    ->sum('monto_prestado_total');

                // Un solo query con agregación condicional en lugar de dos queries separados
                $moraStats = \App\Models\Prestamo::whereIn('estado', \App\Models\Prestamo::ESTADOS_ACTIVOS)
                    ->selectRaw("count(*) as total, sum(case when estado = 'En_Mora' then 1 else 0 end) as en_mora")
                    ->first();
                $tasaMora = $moraStats->total > 0 ? ($moraStats->en_mora / $moraStats->total) * 100 : 0;

                return [
                    'solicitudes_pendientes' => $solicitudesPendientes,
                    'aprobados_sin_firmar' => $aprobadosSinFirmar,
                    'cartera_activa' => $carteraActiva,
                    'tasa_mora' => round($tasaMora, 2),
                ];
            }
        );
    }

    /**
     * Obtiene KPIs ejecutivos para el dashboard del Admin/Super Admin
     *
     * @return array
     */
    public function getDashboardStatsAdmin(): array
    {
        return $this->rememberWithLock(
            self::CACHE_PREFIX . 'dashboard_admin',
            self::SHORT_TTL,
            function () {
                // Un solo query con agregación condicional para cartera + tasa de mora
                $prestamoStats = \App\Models\Prestamo::whereIn('estado', \App\Models\Prestamo::ESTADOS_ACTIVOS)
                    ->selectRaw("
                        count(*) as total,
                        sum(monto_prestado_total) as cartera,
                        sum(case when estado = 'En_Mora' then 1 else 0 end) as en_mora
                    ")
                    ->first();

                $carteraTotal = $prestamoStats->cartera ?? 0;
                $tasaMora = $prestamoStats->total > 0
                    ? ($prestamoStats->en_mora / $prestamoStats->total) * 100
                    : 0;

                $pagosPendientes = \App\Models\Pago::where('estado_pago', 'pendiente')->count();

                $ingresosDelMes = \App\Models\Ingreso::whereBetween('fecha_hora', [\Illuminate\Support\Carbon::now()->startOfMonth(), \Illuminate\Support\Carbon::now()->endOfMonth()])
                    ->sum('monto');

                $egresosDelMes = \App\Models\Egreso::whereBetween('fecha', [\Illuminate\Support\Carbon::now()->startOfMonth(), \Illuminate\Support\Carbon::now()->endOfMonth()])
                    ->sum('monto');

                return [
                    'cartera_total' => $carteraTotal,
                    'pagos_pendientes' => $pagosPendientes,
                    'tasa_mora' => round($tasaMora, 2),
                    'ingresos_mes' => $ingresosDelMes,
                    'egresos_mes' => $egresosDelMes,
                    'flujo_neto_mes' => $ingresosDelMes - $egresosDelMes,
                ];
            }
        );
    }

    /**
     * Invalida todas las estadísticas del dashboard (todos los roles)
     */
    public function invalidateDashboardCache(?int $asesorId = null): void
    {
        if ($asesorId) {
            Cache::forget(self::CACHE_PREFIX . "dashboard_asesor_{$asesorId}");
        } else {
            Cache::forget(self::CACHE_PREFIX . 'dashboard_jo');
            Cache::forget(self::CACHE_PREFIX . 'dashboard_jc');
            Cache::forget(self::CACHE_PREFIX . 'dashboard_admin');

            // Limpiar todas las claves por asesor (file cache no soporta wildcard delete)
            // Cache the asesor ID list to avoid a per-event SELECT on the asesores table.
            $asesorIds = Cache::remember(
                'ec_asesor_ids_all',
                self::SHORT_TTL,
                fn () => \App\Models\Asesor::pluck('id')
            ) ?? collect();

            $asesorIds->each(function (int $id) {
                Cache::forget(self::CACHE_PREFIX . "dashboard_asesor_{$id}");
            });
        }

        Log::debug("CacheService: Invalidated dashboard cache");
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
    public function invalidateAsesorCache(int $asesorId): void
    {
        Cache::forget(self::CACHE_PREFIX . "grupos_asesor_{$asesorId}");
        Cache::forget(self::CACHE_PREFIX . "stats_asesor_{$asesorId}");
        Cache::forget(self::CACHE_PREFIX . 'asesores_activos');
        // Invalidate the asesor ID list so invalidateDashboardCache(null) re-fetches on the next call.
        Cache::forget('ec_asesor_ids_all');

        Log::debug("CacheService: Invalidated cache for asesor {$asesorId}");
    }

    /**
     * Invalida el caché de un usuario (asesor por user_id)
     *
     * @param int $userId
     */
    public function invalidateUserCache(int $userId): void
    {
        Cache::forget(self::CACHE_PREFIX . "asesor_user_{$userId}");

        Log::debug("CacheService: Invalidated cache for user {$userId}");
    }

    /**
     * Invalida el caché global de grupos
     * Llamar cuando se crea, modifica o elimina un grupo
     */
    public function invalidateGruposCache(): void
    {
        Cache::forget(self::CACHE_PREFIX . 'grupos_activos');

        Log::debug("CacheService: Invalidated global grupos cache");
    }

    /**
     * Invalida el caché de productos financieros
     */
    public function invalidateProductosCache(): void
    {
        Cache::forget(self::CACHE_PREFIX . 'productos_financieros');

        Log::debug("CacheService: Invalidated productos financieros cache");
    }

    /**
     * Invalida todas las estadísticas del dashboard
     */
    public function invalidateStatsCache(): void
    {
        Cache::forget(self::CACHE_PREFIX . 'stats_global');
        // También podríamos invalidar stats de asesores individuales si es necesario

        Log::debug("CacheService: Invalidated stats cache");
    }

    /**
     * Limpia TODO el caché de la aplicación
     * Use with caution in production
     */
    public function clearAllCache(): void
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
    public function getCacheInfo(): array
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
