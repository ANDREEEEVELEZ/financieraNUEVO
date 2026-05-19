<?php

namespace App\Observers;

use App\Models\Persona;
use App\Services\CacheService;

class PersonaObserver
{
    public function updating(Persona $persona): void
    {
        if (!$persona->isDirty('correo')) {
            return;
        }

        // If this persona is linked to an asesor user, bust the cached asesor record
        // so CheckUserActive re-evaluates on their next request.
        $asesor = $persona->asesor ?? null;

        if ($asesor?->user_id) {
            CacheService::invalidateUserCache($asesor->user_id);
        }
    }
}
