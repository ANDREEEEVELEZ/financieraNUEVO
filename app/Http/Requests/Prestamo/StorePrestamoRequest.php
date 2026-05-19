<?php

namespace App\Http\Requests\Prestamo;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates loan creation payloads.
 */
final class StorePrestamoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'grupo_id'          => ['required', 'integer', 'exists:grupos,id'],
            'monto_prestado'    => ['required', 'numeric', 'min:1'],
            'tasa_interes'      => ['required', 'numeric', 'min:0', 'max:100'],
            'numero_cuotas'     => ['required', 'integer', 'min:1'],
            'fecha_inicio'      => ['required', 'date'],
        ];
    }
}
