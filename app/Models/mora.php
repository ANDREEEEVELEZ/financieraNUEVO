<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Mora extends Model
{
    use HasFactory;

    /**
     * Los atributos que pueden ser asignados de forma masiva.
     */
    protected $fillable = [
        'dias_atraso',
        'monto_mora',
        'estado_mora',
    ];

    /**
     * Los atributos que deben ser convertidos a tipos nativos.
     */
    protected $casts = [
        'dias_atraso' => 'integer',
        'monto_mora' => 'decimal:2', // si manejas montos como texto puedes omitir esto
    ];
}
