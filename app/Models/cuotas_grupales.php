<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cuotas_Grupales extends Model
{
    use HasFactory;

    protected $table = 'cuotas_grupales';

    // Atributos 
    protected $fillable = [
        'prestamo_id',
        'numero_cuota',
        'monto_cuota_grupal',
        'fecha_vencimiento',
        'estado_cuota_grupal',
    ];

    /**
     * Relación: una cuota grupal pertenece a un préstamo grupal
     */
    public function prestamo()
    {
        return $this->belongsTo(Prestamo::class);
    }
}
