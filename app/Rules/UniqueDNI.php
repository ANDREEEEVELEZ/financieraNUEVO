<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use App\Models\Persona;
use Illuminate\Support\Facades\Log;

class UniqueDNI implements ValidationRule
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
            
            // Solo validar si tiene exactamente 8 dígitos
            if (strlen($normalizedValue) !== 8 || !ctype_digit($normalizedValue)) {
                return; // No validar si no son exactamente 8 dígitos
            }
            
            $query = Persona::where('DNI', $normalizedValue);
            
            // Si estamos editando, ignorar el registro actual
            if ($this->ignoreId) {
                $query->where('id', '!=', $this->ignoreId);
            }
            
            if ($query->exists()) {
                $fail('Este DNI ya está registrado en el sistema.');
            }
            
        } catch (\Exception $e) {
            // Log del error para debugging
            Log::error('Error en validación DNI: ' . $e->getMessage());
            
            // Fallar con mensaje genérico
            $fail('El DNI ya existe en el sistema.');
        }
    }
}
