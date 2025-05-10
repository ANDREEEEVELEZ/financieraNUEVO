<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Pago extends Model
{
    use HasFactory;

    // Opcional: Si el nombre de la tabla no sigue la convención (plural del nombre del modelo), se especifica:
    // protected $table = 'pagos';

    /**
     * Los atributos que se pueden asignar de forma masiva.
     */
    protected $fillable = [
        'tipo_pago',
        'monto_pagado',
        'fecha_pago',
        'estado_pago',
    ];

    /**
     * Los atributos que deben ser convertidos a tipos nativos.
     */
    protected $casts = [
        'fecha_pago' => 'datetime',
        'monto_pagado' => 'decimal:2', // si deseas tratarlo como monto decimal
    ];
}
