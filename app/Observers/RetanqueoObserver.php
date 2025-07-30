<?php

namespace App\Observers;

use App\Models\Retanqueo;
use Illuminate\Support\Facades\Log;

class RetanqueoObserver
{
    /**
     * Handle the Retanqueo "created" event.
     */
    public function created(Retanqueo $retanqueo): void
    {
        Log::info('Nuevo retanqueo creado', [
            'retanqueo_id' => $retanqueo->id,
            'prestamo_id' => $retanqueo->prestamo_id,
            'estado' => $retanqueo->estado_retanqueo
        ]);

        // Aquí se pueden agregar notificaciones automáticas
        // Por ejemplo, notificar a los jefes que hay una nueva solicitud pendiente
    }

    /**
     * Handle the Retanqueo "updated" event.
     */
    public function updated(Retanqueo $retanqueo): void
    {
        $cambios = $retanqueo->getChanges();
        
        // Log si cambió el estado
        if (array_key_exists('estado_retanqueo', $cambios)) {
            Log::info('Estado de retanqueo actualizado', [
                'retanqueo_id' => $retanqueo->id,
                'estado_anterior' => $retanqueo->getOriginal('estado_retanqueo'),
                'estado_nuevo' => $retanqueo->estado_retanqueo
            ]);
        }

        // Si fue aprobado, se pueden enviar notificaciones
        if ($retanqueo->estaAprobado() && array_key_exists('estado_retanqueo', $cambios)) {
            Log::info('Retanqueo aprobado - listo para ejecutar', [
                'retanqueo_id' => $retanqueo->id
            ]);
        }

        // Si fue ejecutado, se pueden enviar notificaciones
        if ($retanqueo->estaEjecutado() && array_key_exists('estado_retanqueo', $cambios)) {
            Log::info('Retanqueo ejecutado exitosamente', [
                'retanqueo_id' => $retanqueo->id,
                'prestamo_nuevo_id' => $retanqueo->prestamo_nuevo_id
            ]);
        }
    }

    /**
     * Handle the Retanqueo "deleted" event.
     */
    public function deleted(Retanqueo $retanqueo): void
    {
        Log::info('Retanqueo eliminado', [
            'retanqueo_id' => $retanqueo->id,
            'prestamo_id' => $retanqueo->prestamo_id
        ]);
    }

    /**
     * Handle the Retanqueo "restored" event.
     */
    public function restored(Retanqueo $retanqueo): void
    {
        Log::info('Retanqueo restaurado', [
            'retanqueo_id' => $retanqueo->id
        ]);
    }

    /**
     * Handle the Retanqueo "force deleted" event.
     */
    public function forceDeleted(Retanqueo $retanqueo): void
    {
        Log::info('Retanqueo eliminado permanentemente', [
            'retanqueo_id' => $retanqueo->id
        ]);
    }
}
