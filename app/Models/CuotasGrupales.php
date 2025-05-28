<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Mora;

class CuotasGrupales extends Model
{
    use HasFactory;

    protected $table = 'cuotas_grupales';

    protected $fillable = [
        'prestamo_id',
        'numero_cuota',
        'monto_cuota_grupal',
        'fecha_vencimiento',
        'saldo_pendiente',
        'estado_cuota_grupal',
        'estado_pago',
    ];

    protected $casts = [
        'fecha_vencimiento' => 'date',
        'monto_cuota_grupal' => 'decimal:2',
        'saldo_pendiente' => 'decimal:2',
    ];

    public function prestamo()
    {
        return $this->belongsTo(Prestamo::class, 'prestamo_id');
    }

    // Tu método estadoLegible
        public function mora()
        {
            return $this->hasOne(Mora::class, 'cuota_grupal_id');
        }
        public function pagos()
        {
            return $this->hasMany(Pago::class, 'cuota_grupal_id');
        }

        public function getMontoTotalAPagarAttribute()
        {
            $saldo = $this->saldo_pendiente ?? 0;
            $montoMora = 0;
            if ($this->mora && in_array($this->mora->estado_mora, ['pendiente', 'parcial'])) {
                $montoMora = abs($this->mora->monto_mora_calculado);
            }
            return $saldo + $montoMora;
        }
}
