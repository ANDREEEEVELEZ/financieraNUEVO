<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class PagoResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'cuota_grupal_id'  => $this->cuota_grupal_id,
            'tipo_pago'        => $this->tipo_pago,
            'codigo_operacion' => $this->codigo_operacion,
            'monto_pagado'     => $this->monto_pagado,
            'monto_mora_pagada'=> $this->monto_mora_pagada,
            'fecha_pago'       => $this->fecha_pago?->toDateTimeString(),
            'estado_pago'      => $this->estado_pago,
            'observaciones'    => $this->observaciones,
            'grupo_nombre'     => $this->whenLoaded(
                'cuotaGrupal',
                fn () => optional(optional(optional($this->cuotaGrupal)->prestamo)->grupo)->nombre_grupo
            ),
            'cuota_context'    => $this->whenLoaded('cuotaGrupal', fn () => [
                'numero_cuota'      => $this->cuotaGrupal->numero_cuota,
                'fecha_vencimiento' => $this->cuotaGrupal->fecha_vencimiento?->toDateString(),
                'prestamo_id'       => $this->cuotaGrupal->prestamo_id,
                'grupo_nombre'      => $this->cuotaGrupal->prestamo?->grupo?->nombre_grupo,
            ]),
        ];
    }
}
