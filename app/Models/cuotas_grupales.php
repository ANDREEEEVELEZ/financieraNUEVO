<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cuotas_Grupales extends Model
{
    use HasFactory;

    // Nombre de la tabla (opcional, pero útil si no sigue la convención plural estándar)
    protected $table = 'cuotas_grupales';

    // Atributos asignables masivamente
    protected $fillable = [
        'prestamo_id',
        'numero_cuota',
        'monto_cuota_grupal',
        'fecha_vencimiento',
        'estado_cuota_grupal',
    ];

    /**
     * Relación: una cuota grupal pertenece a un préstamo grupal.
     */
    public function prestamo()
    {
        return $this->belongsTo(Prestamo::class);
    }
}
