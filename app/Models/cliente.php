<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cliente extends Model
{
    use HasFactory;

    protected $table = 'clientes';

    protected $fillable = [
        'persona_id',
        'infocorp',
        'ciclo',
        'condicion_vivienda',
        'actividad',
        'condicion_personal',
        'estadoCliente',
    ];

    /**
     * Relación uno a uno (o uno a muchos) con Persona.
     */
    public function persona()
    {
        return $this->belongsTo(Persona::class);
    }
}
