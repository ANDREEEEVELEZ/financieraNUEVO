<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class MoraResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'cuota_grupal_id' => $this->cuota_grupal_id,
            'fecha_atraso'    => $this->fecha_atraso?->toDateString(),
            'estado_mora'     => $this->estado_mora,
            // Inline computation — do NOT delegate to getDiasAtrasoAttribute()
            'dias_atraso'     => $this->fecha_atraso !== null
                ? (int) abs(now()->startOfDay()->diffInDays($this->fecha_atraso->startOfDay()))
                : null,
            // monto_mora_calculado relies on cuotaGrupal.prestamo.grupo eager-load;
            // controller MUST eager-load the full chain or this returns 0.
            'monto_mora_calculado' => $this->monto_mora_calculado,
        ];
    }
}
