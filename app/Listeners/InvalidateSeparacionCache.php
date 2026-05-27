<?php

namespace App\Listeners;

use App\Contracts\CacheServiceInterface;
use App\Events\SeparacionRealizada;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Cache;

class InvalidateSeparacionCache implements ShouldQueue
{
    public function __construct(private CacheServiceInterface $cache) {}

    public function handle(SeparacionRealizada $event): void
    {
        $s = $event->separacion->loadMissing(['prestamoOrigen.grupo']);

        // Fine-grained resource keys
        Cache::forget("prestamo:{$s->prestamo_origen_id}");
        Cache::forget("cliente:{$s->cliente_id}");

        if ($s->prestamo_nuevo_id) {
            Cache::forget("prestamo:{$s->prestamo_nuevo_id}");
        }
        if ($s->grupo_origen_id) {
            Cache::forget("grupo:{$s->grupo_origen_id}");
        }

        // Dashboard KPIs — scope to the asesor if resolvable
        $asesorId = $s->prestamoOrigen?->grupo?->asesor_id;

        if ($asesorId) {
            $this->cache->invalidateDashboardCache($asesorId);
            $this->cache->invalidateAsesorCache($asesorId);
        }

        // Always invalidate shared role dashboards (admin, JO, JC)
        $this->cache->invalidateDashboardCache();
    }
}
