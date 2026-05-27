<?php

namespace App\Http\Requests\Api\Pago;

use Illuminate\Foundation\Http\FormRequest;

class StorePagoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole(['Asesor', 'Jefe de creditos', 'super_admin']) ?? false;
    }

    public function rules(): array
    {
        return [
            'cuota_grupal_id'  => ['required', 'integer', 'exists:cuotas_grupales,id'],
            'monto_pagado'     => ['required', 'numeric', 'min:0.01'],
            'tipo_pago'        => ['required', 'string', 'max:100'],
            'codigo_operacion' => ['nullable', 'string', 'max:100'],
            'observaciones'    => ['nullable', 'string', 'max:500'],
        ];
    }
}
