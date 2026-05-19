<?php

namespace App\Http\Requests\Pago;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates payment creation payloads.
 * Financial mutations must validate all fields to prevent amount manipulation.
 */
final class StorePagoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'cuota_grupal_id'   => ['required', 'integer', 'exists:cuotas_grupales,id'],
            'monto_pagado'      => ['required', 'numeric', 'min:0.01'],
            'fecha_pago'        => ['required', 'date', 'before_or_equal:today'],
            'estado_pago'       => ['sometimes', 'string', 'in:pendiente,aprobado,rechazado'],
        ];
    }
}
