<?php

namespace App\Http\Requests\Prestamo;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates loan approval action.
 * The approve action transitions a Prestamo from Pendiente → Aprobado.
 */
final class ApprovePrestamoRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Only Jefe de creditos or super_admin may approve
        return $this->user()?->hasAnyRole(['super_admin', 'Jefe de creditos']) ?? false;
    }

    public function rules(): array
    {
        return [
            'observaciones' => ['sometimes', 'string', 'max:1000'],
        ];
    }
}
