<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pago extends Model
{
    use HasFactory;

    protected $fillable = [
        'cuota_grupal_id',
        'tipo_pago',
        'monto_pagado',
        'fecha_pago',
        'estado_pago',
        'observaciones',
    ];

    // ✅ Conversión automática a objetos Carbon
    protected $casts = [
        'fecha_pago' => 'datetime',
    ];

    public function cuotaGrupal()
    {
        // Nota: Asegúrate que el modelo CuotaGrupal exista y esté nombrado correctamente
        return $this->belongsTo(Cuotas_Grupales::class);
    }

    public function setMontoPagadoAttribute($value)
    {
        $this->attributes['monto_pagado'] = preg_replace('/[^\d.]/', '', $value);
    }

    public function getFechaPagoFormattedAttribute()
    {
        return $this->fecha_pago ? $this->fecha_pago->format('d/m/Y H:i') : null;
    }
}
