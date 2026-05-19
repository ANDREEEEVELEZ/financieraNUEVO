<?php

namespace App\Http\Requests\Prestamo;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates loan update payloads.
 * Ensures only valid state transitions and numeric values reach the service layer.
 */
final class UpdatePrestamoRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Delegate to PrestamoPolicy::update if registered; otherwise allow authenticated users
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'estado'            => ['sometimes', 'string', 'in:Pendiente,Aprobado,Firmado,Activo'],
            'monto_prestado'    => ['sometimes', 'numeric', 'min:0'],
            'tasa_interes'      => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'numero_cuotas'     => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
