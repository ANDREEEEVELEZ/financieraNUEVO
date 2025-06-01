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


    /**
     * Verifica si el cliente ya pertenece a un grupo activo
     */
    public function tieneGrupoActivo(): bool
    {
        return $this->grupos()
            ->where('estado_grupo', 'Activo')
            ->exists();
    }


    /**
     * Obtiene el grupo activo del cliente
     */
    public function getGrupoActivoAttribute()
    {
        return $this->grupos()
            ->where('estado_grupo', 'Activo')
            ->first();
    }


    /**
     * Scope para filtrar clientes visibles según el rol del usuario.
     */
    public function scopeVisiblePorUsuario($query, $user)
    {
        if ($user->hasRole('Asesor')) {
            $asesor = \App\Models\Asesor::where('user_id', $user->id)->first();
            if ($asesor) {
                return $query->where('asesor_id', $asesor->id);
            }
        } elseif ($user->hasAnyRole(['super_admin', 'Jefe de operaciones', 'Jefe de credito'])) {
            return $query; // Mostrar todos los clientes
        }

        return $query->whereRaw('1 = 0'); // No mostrar nada si no aplica
    }
}
