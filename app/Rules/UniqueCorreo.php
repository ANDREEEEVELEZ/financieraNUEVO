<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use App\Models\Persona;
use Illuminate\Support\Facades\Log;

class UniqueCorreo implements ValidationRule
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
            // Normalizar el valor (quitar espacios, convertir a minúsculas para correos)
            $normalizedValue = trim(strtolower((string)$value));
            
            // Si está vacío, no validar (se maneja con required)
            if (empty($normalizedValue)) {
                return;
            }
            
            // Solo validar si tiene formato de email válido (no validar @gmail.com incompleto)
            if (!filter_var($normalizedValue, FILTER_VALIDATE_EMAIL)) {
                return; // No validar si no es un email válido
            }
            
            // Buscar en la base de datos comparando en minúsculas
            $query = Persona::whereRaw('LOWER(correo) = ?', [$normalizedValue]);
            
            // Si estamos editando, ignorar el registro actual
            if ($this->ignoreId) {
                $query->where('id', '!=', $this->ignoreId);
            }
            
            if ($query->exists()) {
                $fail('Este correo electrónico ya está registrado en el sistema.');
            }
            
        } catch (\Exception $e) {
            // Log del error para debugging
            Log::error('Error en validación Correo: ' . $e->getMessage());
            
            // Fallar con mensaje genérico
            $fail('El correo electrónico ya existe en el sistema.');
        }
    }
}
