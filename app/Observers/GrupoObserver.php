<?php

namespace App\Observers;

use App\Contracts\CacheServiceInterface;
use App\Models\Grupo;
use Illuminate\Support\Facades\Log;

class GrupoObserver
{
    public bool $afterCommit = true;

    public function __construct(private CacheServiceInterface $cache) {}

    public function updated(Grupo $grupo): void
    {
        if ($grupo->wasChanged('asesor_id')) {
            $oldAsesorId = $grupo->getOriginal('asesor_id');
            $newAsesorId = $grupo->asesor_id;

            Log::info('Grupo asesor reassigned', [
                'grupo_id'       => $grupo->id,
                'old_asesor_id'  => $oldAsesorId,
                'new_asesor_id'  => $newAsesorId,
            ]);

            if ($oldAsesorId) {
                $this->cache->invalidateDashboardCache($oldAsesorId);
                $this->cache->invalidateAsesorCache($oldAsesorId);
            }
            if ($newAsesorId) {
                $this->cache->invalidateDashboardCache($newAsesorId);
                $this->cache->invalidateAsesorCache($newAsesorId);
            }
        }

        if ($grupo->wasChanged('estado_grupo')) {
            $this->cache->invalidateDashboardCache();
        }
    }
}
