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
        'estado_cliente',
        'asesor_id', // Relación con Asesor
    ];

    /**
     * Relación uno a uno (o uno a muchos) con Persona.
     */
    public function persona()
    {
        return $this->belongsTo(Persona::class);
    }

    /**
     * Relación muchos a muchos con Grupo a través de la tabla pivote grupo_cliente.
     */
    public function grupos()
    {
        return $this->belongsToMany(Grupo::class, 'grupo_cliente', 'cliente_id', 'grupo_id')
            ->withTimestamps();
    }
    public function asesor()
    {
        return $this->belongsTo(Asesor::class);
    }
}
