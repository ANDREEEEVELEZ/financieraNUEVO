<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DetallePago extends Model
{
    use HasFactory;

    protected $table = 'detalles_pago';

    protected $fillable = [
        'pago_id',
        'prestamo_individual_id',
        'monto_pagado',
        'estado_pago_individual',
    ];

    /**
     * Relación: Un detalle de pago pertenece a un pago
     */
    public function pago()
    {
        return $this->belongsTo(Pago::class);
    }

    /**
     * Relación: Un detalle de pago pertenece a un préstamo individual
     */
    public function prestamoIndividual()
    {
        return $this->belongsTo(PrestamoIndividual::class);
    }

    /**
     * Accesor opcional: Formato del monto pagado
     */
    public function getMontoPagadoFormattedAttribute()
    {
        return number_format($this->monto_pagado, 2);
    }

    /**
     * Scope para filtrar por estado de pago
     */
    public function scopeConEstado($query, $estado)
    {
        return $query->where('estado_pago_individual', $estado);
    }
}
