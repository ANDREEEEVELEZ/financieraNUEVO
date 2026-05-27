<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class PersonaResource extends JsonResource
{
    /**
     * PII-safe persona representation.
     *
     * DNI, celular, correo, and direccion are ONLY included for
     * super_admin and Jefe de creditos roles.
     */
    public function toArray(Request $request): array
    {
        $canSeePii = $request->user()?->hasRole(['super_admin', 'Jefe de creditos'])
            ?? auth()->user()?->hasRole(['super_admin', 'Jefe de creditos'])
            ?? false;

        $data = [
            'id'               => $this->id,
            'nombre'           => $this->nombre,
            'apellidos'        => $this->apellidos,
            'sexo'             => $this->sexo,
            'fecha_nacimiento' => $this->fecha_nacimiento?->toDateString(),
            'estado_civil'     => $this->estado_civil,
            'distrito'         => $this->distrito,
        ];

        if ($canSeePii) {
            $data['DNI']      = $this->DNI;
            $data['celular']  = $this->celular;
            $data['correo']   = $this->correo;
            $data['direccion'] = $this->direccion;
        }

        return $data;
    }
}
