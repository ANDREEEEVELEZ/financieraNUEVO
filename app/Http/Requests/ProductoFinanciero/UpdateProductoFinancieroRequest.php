<?php

namespace App\Http\Requests\ProductoFinanciero;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates financial product update payloads.
 * Only admin-level users should reach this endpoint.
 */
final class UpdateProductoFinancieroRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['super_admin']) ?? false;
    }

    public function rules(): array
    {
        return [
            'tasa_interes'          => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'monto_minimo'          => ['sometimes', 'numeric', 'min:0'],
            'monto_maximo'          => ['sometimes', 'numeric', 'min:0'],
            'numero_cuotas_max'     => ['sometimes', 'integer', 'min:1'],
            'activo'                => ['sometimes', 'boolean'],
        ];
    }
}
