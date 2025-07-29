<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Retanqueo extends Model
{
    use HasFactory;

    // Protege el id; el resto asignable en masa
    protected $guarded = ['id'];

    // Casts acordes a la migración
    protected $casts = [
        'monto_retanqueo'                 => 'decimal:2',
        'monto_usado_para_cubrir_antiguo' => 'decimal:2',
        'monto_desembolsar'               => 'decimal:2',
        'monto_cuota'                     => 'decimal:2',
        'saldo_restante_prestamo_antiguo' => 'decimal:2',
        'prestamo_antiguo_estado'         => 'boolean',
        'fecha_aceptacion'                => 'date',
    ];

    // Relaciones
    public function prestamoAntiguo()
    {
        return $this->belongsTo(Prestamo::class, 'prestamo_id');
    }

    public function prestamoNuevo()
    {
        return $this->belongsTo(Prestamo::class, 'prestamo_nuevo_id');
    }
}
