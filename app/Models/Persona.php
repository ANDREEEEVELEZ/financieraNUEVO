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

    // Mutators para convertir automáticamente a mayúsculas
    public function setNombreAttribute($value)
    {
        $this->attributes['nombre'] = strtoupper($value);
    }

    public function setApellidosAttribute($value)
    {
        $this->attributes['apellidos'] = strtoupper($value);
    }

    public function setDireccionAttribute($value)
    {
        $this->attributes['direccion'] = strtoupper($value);
    }

    public function setCorreoAttribute($value)
    {
        $this->attributes['correo'] = strtoupper($value);
    }

    public function setDistritoAttribute($value)
    {
        $this->attributes['distrito'] = strtoupper($value);
    }

    public function setEstadoCivilAttribute($value)
    {
        $this->attributes['estado_civil'] = strtoupper($value);
    }

    public function setSexoAttribute($value)
    {
        $this->attributes['sexo'] = strtoupper($value);
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
