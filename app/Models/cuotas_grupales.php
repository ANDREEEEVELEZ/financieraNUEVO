<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cuotas_Grupales extends Model
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

    /**
     * Relación: una cuota grupal pertenece a un préstamo grupal
     */
    public function prestamo()
    {
        return $this->belongsTo(Prestamo::class, 'prestamo_id');
    }

    /**
     * Relación: una cuota grupal puede tener múltiples pagos
     */
    public function pagos()
    {
        return $this->hasMany(Pago::class, 'cuota_grupal_id');
    }

    /**
     * Scope: obtener cuotas vencidas y no pagadas completamente
     */
    public function scopeVencidas($query)
    {
        return $query->whereDate('fecha_vencimiento', '<', now())
                     ->where('estado_pago', '!=', 'pagado');
    }
}
