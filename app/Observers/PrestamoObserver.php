<?php

namespace App\Observers;

use App\Models\Prestamo;
use App\Models\PrestamoIndividual;
use App\Infrastructure\Cache\CacheService;
use App\Infrastructure\Notifications\NotificationService;
use Illuminate\Support\Facades\Log;

class PrestamoObserver
{
    public function created(Prestamo $prestamo): void
    {
        Log::info('PrestamoObserver: Préstamo creado', ['prestamo_id' => $prestamo->id]);

        CacheService::invalidateStatsCache();

        $supervisores = \App\Models\User::role(['super_admin', 'Jefe de operaciones', 'Jefe de creditos'])->get();
        foreach ($supervisores as $supervisor) {
            NotificationService::invalidateNotificationsCache($supervisor->id);
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

            CacheService::invalidateStatsCache();

            $supervisores = \App\Models\User::role(['super_admin', 'Jefe de operaciones', 'Jefe de creditos'])->get();
            foreach ($supervisores as $supervisor) {
                NotificationService::invalidateNotificationsCache($supervisor->id);
            }

            if ($prestamo->grupo && $prestamo->grupo->asesor && $prestamo->grupo->asesor->user_id) {
                NotificationService::invalidateNotificationsCache($prestamo->grupo->asesor->user_id);
            }
        }
    }
}
