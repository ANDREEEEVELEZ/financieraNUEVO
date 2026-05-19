<?php

namespace App\Http\Requests\Cliente;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates client update payloads.
 * Prevents mass-assignment of sensitive financial fields.
 */
final class UpdateClienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'activo'            => ['sometimes', 'boolean'],
            'asesor_id'         => ['sometimes', 'integer', 'exists:asesores,id'],
            // Persona-level fields go through a separate PersonaRequest if needed
        ];
    }
}
