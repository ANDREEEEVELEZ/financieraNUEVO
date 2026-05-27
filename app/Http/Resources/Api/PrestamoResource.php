<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class PrestamoResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            // ── Index fields (always present) ──────────────────────────
            'id'                   => $this->id,
            'grupo_id'             => $this->grupo_id,
            'grupo_nombre'         => $this->whenLoaded(
                'grupo',
                fn () => $this->grupo->nombre_grupo
            ),
            'producto_id'          => $this->producto_id,
            'monto_prestado_total' => $this->monto_prestado_total,
            'estado'               => $this->estado,
            'fecha_prestamo'       => $this->fecha_prestamo?->toDateString(),
            'fecha_desembolso'     => $this->fecha_desembolso?->toDateString(),
            'cantidad_cuotas'      => $this->cantidad_cuotas,

            // ── NEW scalar fields (additive) ────────────────────────────
            'monto_devolver'             => $this->monto_devolver,
            'tasa_interes'               => $this->tasa_interes,
            'frecuencia'                 => $this->frecuencia,
            'es_retanqueo'               => (bool) $this->es_retanqueo,
            'titular_cuenta_desembolso'  => $this->titular_cuenta_desembolso,
            'numero_cuenta_desembolso'   => $this->numero_cuenta_desembolso,

            // ── FSM helper booleans ─────────────────────────────────────
            ...$this->fsmHelpers(),

            // ── Detail fields (only when relations loaded) ──────────────
            'cuotas_grupales'         => $this->whenLoaded(
                'cuotasGrupales',
                fn () => $this->cuotasGrupales->map(fn ($c) => [
                    'id'                => $c->id,
                    'numero_cuota'      => $c->numero_cuota,
                    'fecha_vencimiento' => optional($c->fecha_vencimiento)->toDateString(),
                    'monto_cuota'       => $c->monto_cuota,
                    'estado_pago'       => $c->estado_pago,
                ])
            ),
            'prestamos_individuales'  => $this->whenLoaded(
                'prestamoIndividual',
                fn () => $this->prestamoIndividual->map(fn ($pi) => [
                    'id'                         => $pi->id,
                    'cliente_id'                 => $pi->cliente_id,
                    'monto_prestado_individual'  => $pi->monto_prestado_individual,
                    'monto_devolver_individual'  => $pi->monto_devolver_individual,
                    'estado'                     => $pi->estado,
                ])
            ),
        ];
    }

    /**
     * FSM button-state projection. Pure read of model state-check methods; no relations loaded.
     */
    private function fsmHelpers(): array
    {
        return [
            'puede_aprobar'     => $this->resource->puedeSerAprobado(),
            'puede_rechazar'    => $this->resource->puedeSerRechazado(),
            'puede_firmar'      => $this->resource->puedeFirmar(),
            'puede_desembolsar' => $this->resource->puedeDesembolsar(),
            'puede_cancelar'    => $this->resource->puedeCancelar(),
        ];
    }
}
