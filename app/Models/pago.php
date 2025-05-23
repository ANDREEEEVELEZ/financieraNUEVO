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
        'estado_pago', // Asegura que este campo sea fillable
        'observaciones',
    ];

    protected $casts = [
        'fecha_pago' => 'datetime',
    ];
    protected $attributes = [
    'estado_pago' => 'pendiente',
    ];

    public function cuotaGrupal()
    {
      
        return $this->belongsTo(Cuotas_Grupales::class, 'cuota_grupal_id');
    }

    public function setMontoPagadoAttribute($value)
    {
        $this->attributes['monto_pagado'] = preg_replace('/[^\d.]/', '', $value);
    }

    public function getFechaPagoFormattedAttribute()
    {
        return $this->fecha_pago ? $this->fecha_pago->format('d/m/Y H:i') : null;
    }
    // En App\Models\Pago.php
    public function aprobar()
    {
        // Solo se aprueba si estaba pendiente
        if ($this->estado_pago !== 'pendiente') {
            return;
        }

        $this->estado_pago = 'aprobado';
        $this->save();

        $cuota = $this->cuotaGrupal;

        if ($this->tipo_pago === 'cuota') {
            // Si el pago es completo (tipo cuota)
            $cuota->estado_pago = 'pagado';
            $cuota->estado_cuota_grupal = 'cancelada';
            $cuota->saldo_pendiente = 0;
        } elseif ($this->tipo_pago === 'amortizacion') {
            // Si es amortización adicional, puede quedar en parcial
            $nuevoSaldo = $cuota->saldo_pendiente - $this->monto_pagado;

            $cuota->saldo_pendiente = $nuevoSaldo > 0 ? $nuevoSaldo : 0;

            if ($nuevoSaldo <= 0) {
                $cuota->estado_pago = 'pagado';
                $cuota->estado_cuota_grupal = 'cancelada';
            } else {
                $cuota->estado_pago = 'parcial';
                // El estado de la cuota grupal se mantiene en mora o vigente
            }
        }

        $cuota->save();
    }

}
