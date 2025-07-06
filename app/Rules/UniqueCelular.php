<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use App\Models\Persona;

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
        $query = Persona::where('celular', $value);
        
        if ($this->ignoreId) {
            $query->where('id', '!=', $this->ignoreId);
        }
        
        if ($query->exists()) {
            $fail('El número de celular ya existe en el sistema.');
        }
    }
}
