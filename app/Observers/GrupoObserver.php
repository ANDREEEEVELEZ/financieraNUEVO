<?php

namespace App\Observers;

use App\Models\Grupo;
use App\Services\CacheService;
use Illuminate\Support\Facades\Log;

class GrupoObserver
{
    public bool $afterCommit = true;

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
                CacheService::invalidateDashboardCache($oldAsesorId);
                CacheService::invalidateAsesorCache($oldAsesorId);
            }
            if ($newAsesorId) {
                CacheService::invalidateDashboardCache($newAsesorId);
                CacheService::invalidateAsesorCache($newAsesorId);
            }
        }

        if ($grupo->wasChanged('estado_grupo')) {
            CacheService::invalidateDashboardCache();
        }
    }
}
