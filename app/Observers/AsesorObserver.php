<?php

namespace App\Observers;

use App\Contracts\CacheServiceInterface;
use App\Models\Asesor;

class AsesorObserver
{
    public function __construct(private CacheServiceInterface $cache) {}

    public function updated(Asesor $asesor): void
    {
        if ($asesor->wasChanged('estado_asesor') || $asesor->wasChanged('persona_id')) {
            // Bust the per-user asesor cache so CheckUserActive picks up the new state
            // on the very next request from the affected user.
            if ($asesor->user_id) {
                $this->cache->invalidateUserCache($asesor->user_id);
            }
        }
    }
}
