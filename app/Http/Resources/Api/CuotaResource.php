<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class CuotaResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        // dias_mora: positive integer for overdue pending cuotas, 0 otherwise
        $diasMora = 0;
        if ($this->fecha_vencimiento && $this->estado !== 'pagada') {
            $vencimiento = \Carbon\Carbon::parse($this->fecha_vencimiento)->startOfDay();
            if ($vencimiento->lt(now()->startOfDay())) {
                $diasMora = (int) abs(now()->startOfDay()->diffInDays($vencimiento));
            }
        }

        return [
            'id'                     => $this->id,
            'prestamo_id'            => $this->prestamo_id,
            'cliente_id'             => $this->cliente_id,
            'numero_cuota'           => $this->numero_cuota,
            'saldo_capital'          => $this->saldo_capital,
            'saldo_interes'          => $this->saldo_interes,
            'monto_capital_original' => $this->monto_capital_original,
            'monto_interes_original' => $this->monto_interes_original,
            'monto_seguro'           => $this->monto_seguro,
            'saldo_total'            => bcadd((string) $this->saldo_capital, (string) $this->saldo_interes, 2),
            'fecha_vencimiento'      => $this->fecha_vencimiento?->toDateString(),
            'estado'                 => $this->estado,
            'dias_mora'              => $diasMora,
        ];
    }
}
