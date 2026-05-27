<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class GrupoResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * Index fields: id, nombre_grupo, estado_grupo, numero_integrantes,
     *               calificacion_grupo, fecha_registro, created_at
     * Conditional (asesor loaded): asesor {id, nombre_completo}; asesor_id omitted.
     * Conditional (asesor NOT loaded): asesor_id raw FK.
     * Detail (when eager-loaded):
     *   - integrantes: explicit map with pivot rol + fecha_ingreso
     *   - prestamo_activo: first active prestamo with extra fields
     */
    public function toArray(Request $request): array
    {
        $data = [
            'id'                 => $this->id,
            'nombre_grupo'       => $this->nombre_grupo,
            'estado_grupo'       => $this->estado_grupo,
            'numero_integrantes' => $this->numero_integrantes,
            'calificacion_grupo' => $this->calificacion_grupo,
            'fecha_registro'     => $this->fecha_registro?->toDateString(),
            'created_at'         => $this->created_at,
        ];

        // Asesor: expose nested object when loaded, raw FK when not loaded.
        if ($this->relationLoaded('asesor')) {
            $data['asesor'] = $this->asesor ? [
                'id'              => $this->asesor->id,
                'nombre_completo' => trim(
                    ($this->asesor->persona?->nombre ?? '') . ' ' .
                    ($this->asesor->persona?->apellidos ?? '')
                ),
            ] : null;
        } else {
            $data['asesor_id'] = $this->asesor_id;
        }

        // Integrantes with pivot fields (rol, fecha_ingreso).
        if ($this->relationLoaded('clientes')) {
            $data['integrantes'] = $this->clientes->map(fn ($c) => [
                'id'              => $c->id,
                'nombre_completo' => trim(
                    ($c->persona?->nombre ?? '') . ' ' .
                    ($c->persona?->apellidos ?? '')
                ),
                'rol'             => $c->pivot?->rol,
                'fecha_ingreso'   => $c->pivot?->fecha_ingreso instanceof \Carbon\Carbon
                    ? $c->pivot->fecha_ingreso->toDateString()
                    : $c->pivot?->fecha_ingreso,
            ])->values()->all();
        }

        // Prestamo activo with enriched fields.
        if ($this->relationLoaded('prestamos')) {
            $activo = $this->prestamos->where('estado', 'Activo')->first();
            $data['prestamo_activo'] = $activo ? $activo->only([
                'id',
                'estado',
                'monto_prestado_total',
                'monto_devolver',
                'cantidad_cuotas',
                'frecuencia',
                'tasa_interes',
                'fecha_desembolso',
            ]) : null;
        }

        return $data;
    }
}
