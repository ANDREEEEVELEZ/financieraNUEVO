<?php

namespace App\Observers;

use App\Models\Mora;
use Illuminate\Support\Facades\Log;

class MoraObserver
{
    public bool $afterCommit = true;

    public function updated(Mora $mora): void
    {
        if ($mora->wasChanged('estado_mora')) {
            Log::info('Mora estado changed', [
                'mora_id'           => $mora->id,
                'cuota_grupal_id'   => $mora->cuota_grupal_id,
                'old_estado'        => $mora->getOriginal('estado_mora'),
                'new_estado'        => $mora->estado_mora,
                'monto_calculado'   => $mora->monto_mora_calculado,
            ]);
        }
    }

    public function created(Mora $mora): void
    {
        Log::info('Mora created', [
            'mora_id'         => $mora->id,
            'cuota_grupal_id' => $mora->cuota_grupal_id,
            'estado'          => $mora->estado_mora,
        ]);
    }
}
