<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Api\GrupoResource;
use App\Http\Resources\Api\PrestamoResource;

final class ClienteResource extends JsonResource
{
    /**
     * PII-safe cliente representation.
     *
     * Persona fields are filtered through PersonaResource (which gates DNI/celular/correo/direccion).
     * Scoring is only included when the relationship has been eager-loaded.
     */
    public function toArray(Request $request): array
    {
        $data = [
            'id'                  => $this->id,
            'persona'             => new PersonaResource($this->persona),
            'ciclo'               => $this->ciclo,
            'estado_cliente'      => $this->estado_cliente,
            'condicion_vivienda'  => $this->condicion_vivienda,
            'actividad'           => $this->actividad,
            'asesor_id'           => $this->asesor_id,
            'condicion_personal'  => $this->condicion_personal,
        ];

        // Include scoring only when eager-loaded to avoid N+1 queries.
        if ($this->relationLoaded('scoring')) {
            $scoring = $this->scoring;
            $data['scoring'] = $scoring ? ['grado' => $scoring->grado, 'score' => $scoring->score] : null;
        }

        if ($this->relationLoaded('grupos')) {
            $data['grupos'] = GrupoResource::collection($this->grupos);
        }

        if ($this->relationLoaded('prestamos')) {
            $data['prestamos'] = PrestamoResource::collection($this->prestamos);
        }

        return $data;
    }
}
