<?php

namespace App\Observers;

use App\Models\Cliente;
use App\Services\CacheService;
use Illuminate\Support\Facades\Log;

class ClienteObserver
{
    public bool $afterCommit = true;

    public function updated(Cliente $cliente): void
    {
        if ($cliente->wasChanged('asesor_id')) {
            $oldAsesorId = $cliente->getOriginal('asesor_id');
            $newAsesorId = $cliente->asesor_id;

            Log::info('Cliente asesor reassigned', [
                'cliente_id'     => $cliente->id,
                'old_asesor_id'  => $oldAsesorId,
                'new_asesor_id'  => $newAsesorId,
            ]);

            if ($oldAsesorId) {
                CacheService::invalidateDashboardCache($oldAsesorId);
                CacheService::invalidateAsesorCache($oldAsesorId);
            }
            if ($newAsesorId && $newAsesorId !== $oldAsesorId) {
                CacheService::invalidateDashboardCache($newAsesorId);
                CacheService::invalidateAsesorCache($newAsesorId);
            }
        }

        if ($cliente->wasChanged('estado_cliente')) {
            Log::info('Cliente estado changed', [
                'cliente_id'    => $cliente->id,
                'old_estado'    => $cliente->getOriginal('estado_cliente'),
                'new_estado'    => $cliente->estado_cliente,
            ]);

            CacheService::invalidateDashboardCache();
        }
    }
}
