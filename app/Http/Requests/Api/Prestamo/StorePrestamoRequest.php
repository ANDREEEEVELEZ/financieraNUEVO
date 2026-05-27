<?php

namespace App\Http\Requests\Api\Prestamo;

use App\Models\Asesor;
use App\Models\Grupo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePrestamoRequest extends FormRequest
{
    /**
     * Only Asesor and super_admin can create prestamos.
     */
    public function authorize(): bool
    {
        return $this->user()->hasAnyRole(['Asesor', 'super_admin']);
    }

    public function rules(): array
    {
        return [
            'grupo_id'                        => ['required', 'integer', 'exists:grupos,id', $this->grupoOwnershipRule()],
            'producto_id'                     => ['required', 'integer', 'exists:producto_financiero,id'],
            'monto_prestado_total'            => ['required', 'numeric', 'min:0'],
            'cantidad_cuotas'                 => ['required', 'integer', 'min:1'],
            'frecuencia'                      => ['required', 'string', Rule::in(['Semanal', 'Quincenal', 'Mensual'])],
            'fecha_prestamo'                  => ['required', 'date'],
            'montos_individuales'             => ['required', 'array', 'min:1'],
            'montos_individuales.*.cliente_id'=> ['required', 'integer', 'exists:clientes,id'],
            'montos_individuales.*.monto'     => ['required', 'numeric', 'min:0'],
        ];
    }

    /**
     * Custom rule: grupo must belong to the requesting Asesor.
     * SA bypasses this check.
     */
    private function grupoOwnershipRule(): \Closure|\Illuminate\Contracts\Validation\ValidationRule
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            $user = $this->user();

            if ($user->hasRole('super_admin')) {
                return;
            }

            $asesor = Asesor::where('user_id', $user->id)->first();

            if (! $asesor) {
                $fail('No se encontró un asesor asociado al usuario.');
                return;
            }

            $belongs = Grupo::where('id', $value)
                ->where('asesor_id', $asesor->id)
                ->exists();

            if (! $belongs) {
                $fail('El grupo no pertenece al asesor autenticado.');
            }
        };
    }

    public function messages(): array
    {
        return [
            'grupo_id.exists'                         => 'El grupo no existe.',
            'producto_id.exists'                      => 'El producto financiero no existe.',
            'frecuencia.in'                           => 'La frecuencia debe ser Semanal, Quincenal o Mensual.',
            'montos_individuales.*.cliente_id.exists' => 'Uno o más clientes no existen.',
        ];
    }
}
