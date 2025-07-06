<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use App\Models\Persona;

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
        $query = Persona::where('correo', $value);
        
        if ($this->ignoreId) {
            $query->where('id', '!=', $this->ignoreId);
        }
        
        if ($query->exists()) {
            $fail('El correo electrónico ya existe en el sistema.');
        }
    }
}
