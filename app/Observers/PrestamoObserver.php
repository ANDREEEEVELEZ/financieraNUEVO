<?php

namespace App\Observers;

use App\Contracts\CacheServiceInterface;
use App\Contracts\NotificationServiceInterface;
use App\Models\Prestamo;
use App\Models\PrestamoIndividual;
use Illuminate\Support\Facades\Log;

class PrestamoObserver
{
    public function __construct(private CacheServiceInterface $cache, private NotificationServiceInterface $notifications) {}

    public function created(Prestamo $prestamo): void
    {
        Log::info('PrestamoObserver: Préstamo creado', ['prestamo_id' => $prestamo->id]);

        $this->cache->invalidateStatsCache();

        $supervisores = \App\Models\User::role(['super_admin', 'Jefe de operaciones', 'Jefe de creditos'])->get();
        foreach ($supervisores as $supervisor) {
            $this->notifications->invalidateNotificationsCache($supervisor->id);
        }
    }

    public function updated(Prestamo $prestamo): void
    {
        Log::info('PrestamoObserver: updated() ejecutado', [
            'prestamo_id'  => $prestamo->id,
            'estado_actual' => $prestamo->estado,
            'was_changed'  => $prestamo->wasChanged('estado'),
        ]);

        if ($prestamo->wasChanged('estado')) {
            if (in_array($prestamo->estado, ['Pendiente', 'Aprobado', 'Rechazado'])) {
                PrestamoIndividual::where('prestamo_id', $prestamo->id)
                    ->update(['estado' => $prestamo->estado]);
            }

            $this->cache->invalidateStatsCache();

            $supervisores = \App\Models\User::role(['super_admin', 'Jefe de operaciones', 'Jefe de creditos'])->get();
            foreach ($supervisores as $supervisor) {
                $this->notifications->invalidateNotificationsCache($supervisor->id);
            }

            if ($prestamo->grupo && $prestamo->grupo->asesor && $prestamo->grupo->asesor->user_id) {
                $this->notifications->invalidateNotificationsCache($prestamo->grupo->asesor->user_id);
            }
        }
    }
}
