<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Retanqueo extends Model
{
    use HasFactory;

    protected $table = 'retanqueos';

    protected $fillable = [
        'prestamos_id',
        'grupo_id',
        'asesores_id',
        'monto_retanqueado',
        'monto_devolver',
        'monto_desembolsar',
        'cantidad_cuotas_retanqueo',
        'aceptado',
        'fecha_aceptacion',
        'estado_retanqueo',
    ];

    protected $casts = [
        'fecha_aceptacion' => 'datetime',
    ];

    public function prestamo()
    {
        return $this->belongsTo(Prestamo::class);
    }

    public function grupo()
    {
        return $this->belongsTo(Grupo::class);
    }

    public function asesor()
    {
        return $this->belongsTo(Asesor::class, 'asesores_id');
    }
}