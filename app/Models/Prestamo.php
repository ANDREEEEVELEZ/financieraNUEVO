<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Prestamo extends Model
{
    use HasFactory;

    protected $table = 'prestamos';

    protected $fillable = [
        'grupo_id',
        'tasa_interes',
        'monto_prestado_total',
        'monto_devolver',
        'cantidad_cuotas',
        'fecha_prestamo',
        'frecuencia',
        'estado',
        'calificacion',
    ];

    protected $casts = [
        'tasa_interes' => 'float',
        'monto_prestado_total' => 'float',
        'monto_devolver' => 'float',
        'cantidad_cuotas' => 'integer',
        'fecha_prestamo' => 'date',
    ];

    public function grupo()
    {
        return $this->belongsTo(Grupo::class, 'grupo_id');
    }
}