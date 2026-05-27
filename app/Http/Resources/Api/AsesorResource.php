<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class AsesorResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * user_id is intentionally excluded (AS-8).
     * PII gating is inherited via the nested PersonaResource (AS-9).
     */
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'codigo_asesor' => $this->codigo_asesor,
            'fecha_ingreso' => $this->fecha_ingreso,
            'estado_asesor' => $this->estado_asesor,
            'persona'       => $this->whenLoaded('persona', fn () => new PersonaResource($this->persona)),
        ];
    }
}
