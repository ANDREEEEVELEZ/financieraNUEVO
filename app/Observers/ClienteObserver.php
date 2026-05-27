<?php

namespace App\Observers;

use App\Contracts\CacheServiceInterface;
use App\Models\Cliente;
use Illuminate\Support\Facades\Log;

class ClienteObserver
{
    public bool $afterCommit = true;

    public function __construct(private CacheServiceInterface $cache) {}

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
                $this->cache->invalidateDashboardCache($oldAsesorId);
                $this->cache->invalidateAsesorCache($oldAsesorId);
            }
            if ($newAsesorId && $newAsesorId !== $oldAsesorId) {
                $this->cache->invalidateDashboardCache($newAsesorId);
                $this->cache->invalidateAsesorCache($newAsesorId);
            }
        }

        if ($cliente->wasChanged('estado_cliente')) {
            Log::info('Cliente estado changed', [
                'cliente_id'    => $cliente->id,
                'old_estado'    => $cliente->getOriginal('estado_cliente'),
                'new_estado'    => $cliente->estado_cliente,
            ]);

            $this->cache->invalidateDashboardCache();
        }
    }
}
