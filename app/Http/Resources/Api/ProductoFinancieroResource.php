<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ProductoFinancieroResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * config_json is intentionally excluded — it is an internal configuration
     * blob and MUST NOT appear in any API response.
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                      => $this->id,
            'codigo'                  => $this->codigo,
            'nombre'                  => $this->nombre,
            'tipo'                    => $this->tipo,
            'tasa_interes'            => $this->tasa_interes,
            'tasa_mora'               => $this->tasa_mora,
            'permite_condonacion_mora'=> $this->permite_condonacion_mora,
            'permite_retanqueo'       => $this->permite_retanqueo,
            'penalizacion_separacion' => $this->penalizacion_separacion,
            'monto_minimo'            => $this->monto_minimo,
            'monto_maximo'            => $this->monto_maximo,
            'plazo_minimo_meses'      => $this->plazo_minimo_meses,
            'plazo_maximo_meses'      => $this->plazo_maximo_meses,
            'activo'                  => $this->activo,
        ];
    }
}
