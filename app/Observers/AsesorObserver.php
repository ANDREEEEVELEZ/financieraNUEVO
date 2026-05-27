<?php

namespace App\Observers;

use App\Models\Asesor;
use App\Infrastructure\Cache\CacheService;

class AsesorObserver
{
    public function updated(Asesor $asesor): void
    {
        if ($asesor->wasChanged('estado_asesor') || $asesor->wasChanged('persona_id')) {
            // Bust the per-user asesor cache so CheckUserActive picks up the new state
            // on the very next request from the affected user.
            if ($asesor->user_id) {
                CacheService::invalidateUserCache($asesor->user_id);
            }
        }
    }
}
