<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class RetanqueoResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                              => $this->id,
            'prestamo_id'                     => $this->prestamo_id,
            'prestamo_nuevo_id'               => $this->prestamo_nuevo_id,
            'monto_retanqueo'                 => $this->monto_retanqueo,
            'monto_usado_para_cubrir_antiguo' => $this->monto_usado_para_cubrir_antiguo,
            'monto_desembolsar'               => $this->monto_desembolsar,
            'monto_cuota'                     => $this->monto_cuota,
            'cantidad_cuotas_nuevo'           => $this->cantidad_cuotas_nuevo,
            'saldo_restante_prestamo_antiguo' => $this->saldo_restante_prestamo_antiguo,
            'estado_retanqueo'                => $this->estado_retanqueo,
            'fecha_aceptacion'                => $this->fecha_aceptacion?->toDateString(),
            'grupo_nombre'                    => $this->whenLoaded(
                'prestamoAntiguo',
                fn () => $this->prestamoAntiguo->grupo?->nombre_grupo
            ),
        ];
    }
}
