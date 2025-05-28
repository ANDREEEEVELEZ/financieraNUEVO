<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RetanqueoIndividual extends Model
{
    use HasFactory;

    // Tabla asociada
    protected $table = 'retanqueo_individual';

    // Atributos 
    protected $fillable = [
        'retanqueo_id',
        'cliente_id',
        'monto_solicitado',
        'monto_desembolsar',
        'monto_cuota_retanqueo',
        'estado_retanqueo_individual',
    ];

    /**
     * Relación: un retanqueo individual pertenece a un retanqueo grupal.
     */
    public function retanqueo()
    {
        return $this->belongsTo(Retanqueo::class);
    }

    /**
     * Relación: un retanqueo individual pertenece a un cliente.
     */
    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }
}
