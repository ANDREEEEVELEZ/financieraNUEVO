<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo para la tabla de configuración de reglas de negocio.
 * Solo el Súper Admin puede modificar estas reglas desde el panel.
 */
class BusinessRuleConfig extends Model
{
    protected $fillable = ['clave', 'valor', 'tipo', 'descripcion', 'grupo'];

    /**
     * Obtiene el valor casteado según el tipo definido en la regla.
     */
    public function getValorCasteadoAttribute(): mixed
    {
        return match ($this->tipo) {
            'integer' => (int) $this->valor,
            'boolean' => filter_var($this->valor, FILTER_VALIDATE_BOOLEAN),
            'decimal' => (float) $this->valor,
            default   => $this->valor,
        };
    }
}
