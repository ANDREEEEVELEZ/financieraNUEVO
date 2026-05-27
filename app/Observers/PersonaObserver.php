<?php

namespace App\Observers;

use App\Contracts\CacheServiceInterface;
use App\Models\Persona;

class PersonaObserver
{
    public function __construct(private CacheServiceInterface $cache) {}

    public function updating(Persona $persona): void
    {
        if (!$persona->isDirty('correo')) {
            return;
        }

        // If this persona is linked to an asesor user, bust the cached asesor record
        // so CheckUserActive re-evaluates on their next request.
        $asesor = $persona->asesor ?? null;

        if ($asesor?->user_id) {
            $this->cache->invalidateUserCache($asesor->user_id);
        }
    }
}
