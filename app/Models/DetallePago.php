<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Pago;
use App\Models\PrestamoIndividual;

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
     * Relación con el modelo Pago
     */
    public function pago()
    {
        return $this->belongsTo(Pago::class);
    }

    /**
     * Relación con el modelo PrestamoIndividual
     */
    public function prestamoIndividual()
    {
        return $this->belongsTo(PrestamoIndividual::class);
    }
}
