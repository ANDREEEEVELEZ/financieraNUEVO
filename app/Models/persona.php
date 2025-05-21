<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Persona extends Model
{
    use HasFactory;

    protected $table = 'personas';

    protected $fillable = [
        'DNI',
        'nombre',
        'apellidos',
        'sexo',
        'fecha_nacimiento',
        'celular',
        'correo',
        'direccion',
        'distrito',
        'estado_civil',
    ];

    protected $casts = [
        'fecha_nacimiento' => 'date',
    ];
    public function user()
    {
        return $this->hasOne(User::class, 'persona_id');
    }
}
