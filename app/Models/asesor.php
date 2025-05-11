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

    // Relaciones con otras entidades
    public function persona()
    {
        return $this->belongsTo(Persona::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}