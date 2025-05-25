<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Asesor extends Model
{
    use HasFactory;

    protected $table = 'asesores';

    protected $fillable = [
        'persona_id',
        'user_id',
        'codigo_asesor',
        'fecha_ingreso',
        'estado_asesor',
    ];

    // Relaciones con tablas
    public function persona()
    {
        return $this->belongsTo(Persona::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function grupos()
    {
        return $this->hasMany(Grupo::class);
    }
    public function clientes()
    {
        return $this->hasMany(Cliente::class);
    }
}