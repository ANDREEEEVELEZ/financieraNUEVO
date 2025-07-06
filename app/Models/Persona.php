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

    /**
     * Register the model observers.
     */
    protected static function boot()
    {
        parent::boot();
        
        // Registrar el Observer para el modelo Persona
        static::observe(\App\Observers\PersonaObserver::class);
    }

    public function user()
    {
        return $this->hasOne(User::class, 'persona_id');
    }
    
    public function asesor()
    {
        return $this->hasOne(Asesor::class, 'persona_id');
    }
    
    public function clientes()
    {
        return $this->hasMany(Cliente::class, 'persona_id');
    }
}
