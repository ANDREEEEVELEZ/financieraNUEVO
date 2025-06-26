<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Egreso extends Model
{
    use HasFactory;

    protected $fillable = [
        'tipo_egreso',
        'prestamo_id',
        'categoria_id',
        'subcategoria_id',
        'fecha',
        'monto',
        'descripcion',
    ];

    protected $casts = [
        'fecha' => 'date',
        'monto' => 'decimal:2',
    ];

    // Relaciones
    public function prestamo()
    {
        return $this->belongsTo(Prestamo::class);
    }

    public function categoria()
    {
        return $this->belongsTo(Categoria::class);
    }

    public function subcategoria()
    {
        return $this->belongsTo(Subcategoria::class);
    }
    public function grupo()
    {
        return $this->belongsTo(Grupo::class);
    }
    public function getMontoAttribute($value)
{
    // Asegurar que siempre retorne un decimal correcto
    return is_null($value) ? null : (float)$value;
}

public function setMontoAttribute($value)
{
    // Asegurar que siempre se guarde como decimal
    $this->attributes['monto'] = is_null($value) ? null : (float)$value;
}
}
