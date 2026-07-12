<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AplicacionPago extends Model
{
    use HasFactory;

    protected $table = 'aplicacion_pago';

    protected $fillable = [
        'pago_id',
        'cuota_id',
        'tipo_aplicacion',
        'monto_aplicado_capital',
        'monto_aplicado_interes',
        'monto_aplicado_mora',
        'fecha_aplicacion',
    ];

    protected $attributes = [
        'tipo_aplicacion' => 'cobranza',
    ];

    protected $casts = [
        'monto_aplicado_capital' => 'decimal:2',
        'monto_aplicado_interes' => 'decimal:2',
        'monto_aplicado_mora' => 'decimal:2',
        'fecha_aplicacion' => 'datetime',
    ];

    // Relaciones
    public function pago()
    {
        return $this->belongsTo(Pago::class);
    }

    public function cuota()
    {
        return $this->belongsTo(CuotaIndividual::class, 'cuota_id');
    }

    // Helpers
    public function montoTotal(): float
    {
        return (float) $this->monto_aplicado_capital
            + (float) $this->monto_aplicado_interes
            + (float) $this->monto_aplicado_mora;
    }
}
