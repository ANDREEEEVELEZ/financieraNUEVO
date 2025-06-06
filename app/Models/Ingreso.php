<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Ingreso extends Model
{
    use HasFactory;

    // Constantes para los tipos de ingreso (evita errores de string)
    public const TIPO_TRANSFERENCIA = 'transferencia';
    public const TIPO_PAGO_CUOTA = 'pago de cuota de grupo';

    protected $table = 'ingresos';

    protected $fillable = [
        'tipo_ingreso',
        'pago_id',
        'grupo_id',
        'fecha_hora',
        'descripcion',
        'monto',
    ];

    protected $casts = [
        'fecha_hora' => 'datetime',
        'monto' => 'decimal:2',
    ];

    /** Relaciones */

    public function pago()
    {
        return $this->belongsTo(Pago::class);
    }

    public function grupo()
    {
        return $this->belongsTo(Grupo::class);
    }

    /** Scopes */

    public function scopeTransferencias($query)
    {
        return $query->where('tipo_ingreso', self::TIPO_TRANSFERENCIA);
    }

    public function scopePagosDeCuota($query)
    {
        return $query->where('tipo_ingreso', self::TIPO_PAGO_CUOTA);
    }
}
