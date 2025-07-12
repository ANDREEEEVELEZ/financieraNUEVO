<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use App\Models\Persona;
use Illuminate\Support\Facades\Log;

class UniqueCelular implements ValidationRule
{
    protected $ignoreId;

    public function __construct($ignoreId = null)
    {
        $this->ignoreId = $ignoreId;
    }

    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            // Normalizar el valor (quitar espacios y convertir a string)
            $normalizedValue = trim((string)$value);
            
            // Si está vacío, no validar (se maneja con required)
            if (empty($normalizedValue)) {
                return;
            }
            
            // Solo validar si tiene exactamente 9 dígitos
            if (strlen($normalizedValue) !== 9 || !ctype_digit($normalizedValue)) {
                return; // No validar si no son exactamente 9 dígitos
            }
            
            $query = Persona::where('celular', $normalizedValue);
            
            // Si estamos editando, ignorar el registro actual
            if ($this->ignoreId) {
                $query->where('id', '!=', $this->ignoreId);
            }
            
            if ($query->exists()) {
                $fail('Este número de celular ya está registrado en el sistema.');
            }
            
        } catch (\Exception $e) {
            // Log del error para debugging
            Log::error('Error en validación Celular: ' . $e->getMessage());
            
            // Fallar con mensaje genérico
            $fail('El número de celular ya existe en el sistema.');
        }
    }
}
