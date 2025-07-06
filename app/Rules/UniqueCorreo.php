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
            
            // Buscar en la base de datos comparando en minúsculas
            $query = Persona::whereRaw('LOWER(correo) = ?', [$normalizedValue]);
            
            // Si estamos editando, ignorar el registro actual
            if ($this->ignoreId) {
                $query->where('id', '!=', $this->ignoreId);
            }
            
            if ($query->exists()) {
                $fail('El correo electrónico ya existe en el sistema.');
            }
            
        } catch (\Exception $e) {
            // Log del error para debugging
            Log::error('Error en validación Correo: ' . $e->getMessage());
            
            // Fallar con mensaje genérico
            $fail('El correo electrónico ya existe en el sistema.');
        }
    }
}
