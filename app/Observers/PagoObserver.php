<?php
namespace App\Observers;

use App\Models\Pago;
use App\Models\Ingreso;

class PagoObserver
{
    public function updated(Pago $pago)
    {
        // Verifica si el estado ha cambiado a "aprobado"
        if ($pago->isDirty('estado_pago') && $pago->estado_pago === 'aprobado') {
            $cuotaGrupal = $pago->cuotaGrupal;
            $grupo = $cuotaGrupal->prestamo->grupo ?? null;

            if (!$grupo) return;

            // Verifica si ya existe un ingreso para este pago (para evitar duplicados)
            if (Ingreso::where('pago_id', $pago->id)->exists()) return;

            Ingreso::create([
                'tipo_ingreso' => 'pago de cuota de grupo',
                'pago_id' => $pago->id,
                  'monto' => $pago->monto_pagado,
                'fecha_hora' => $pago->fecha_pago,
                'grupo_id' => $grupo->id,
                'descripcion' => 'PAGO A CTA DE CUOTA GRUPO ' . $grupo->nombre_grupo . ($pago->observaciones ? ' (' . $pago->observaciones . ')' : ''),


            ]);
        }
    }
}
