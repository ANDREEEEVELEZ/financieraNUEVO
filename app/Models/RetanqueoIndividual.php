<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class RetanqueoIndividual extends Model
{
    use HasFactory;

    protected $table = 'retanqueos_individual';

    protected $guarded = ['id'];

    protected $casts = [
        'aporte_cobertura'    => 'decimal:2',
        'monto_solicitado'    => 'decimal:2',
        'monto_desembolsar'   => 'decimal:2',
        'monto_cuota'         => 'decimal:2',
        'aceptacion_cliente'  => 'boolean',
    ];

    public function retanqueo()
    {
        return $this->belongsTo(Retanqueo::class, 'retanqueo_id');
    }

    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }
}
