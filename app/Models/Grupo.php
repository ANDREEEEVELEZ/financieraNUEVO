<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Grupo extends Model
{
    use HasFactory;

    protected $table = 'grupos'; // Especificando el nombre de la tabla si no sigue las convenciones de Laravel
    
    protected $fillable = [
        'nombre_grupo',
        'numero_integrantes',
        'fecha_registro',
        'calificacion_grupo',
        'estado_grupo',
        'asesor_id', // Relación con Asesor
    ];

    protected $casts = [
        'fecha_registro' => 'date',
    ];

    public function integrantes()
    {
       //  return $this->hasMany(Integrante::class); // Suposición de una relación con una tabla de integrantes
    }

    public function clientes()
    {
        return $this->belongsToMany(Cliente::class, 'grupo_cliente', 'grupo_id', 'cliente_id')
            ->withTimestamps()
             ->join('personas', 'clientes.persona_id', '=', 'personas.id')
                ->orderBy('personas.apellidos')
                ->orderBy('personas.nombre');
    }

    public function prestamos()
    {
        return $this->hasMany(\App\Models\Prestamo::class, 'grupo_id');
    }

    public function getIntegrantesNombresAttribute()
    {
        return $this->clientes->map(function($cliente) {
            return $cliente->persona->nombre . ' ' . $cliente->persona->apellidos;
        })->implode(', ');
    }
    
    public function getNumeroIntegrantesRealAttribute()
    {
        return $this->clientes()->count();
    }
    /**
     * Sincroniza el estado del grupo según el estado del préstamo principal.
     */
    public function sincronizarEstadoPorPrestamoPrincipal()
    {
        // Considera solo el préstamo principal (más reciente o con estado relevante)
        $prestamo = $this->prestamos()->orderByDesc('id')->first();
        if ($prestamo) {
            $this->estado_grupo = $prestamo->estado;
            $this->save();
        }
    }

    public function asesor()
    {
        return $this->belongsTo(Asesor::class);
    }

    public function scopeVisiblePorUsuario($query, $user)
    {
        if ($user->hasRole('Asesor')) {
            $asesor = \App\Models\Asesor::where('user_id', $user->id)->first();
            if ($asesor) {
                return $query->where('asesor_id', $asesor->id);
            }
        } elseif ($user->hasAnyRole(['super_admin', 'Jefe de operaciones', 'Jefe de credito'])) {
            return $query; // Mostrar todos los grupos
        }

        return $query->whereRaw('1 = 0'); // No mostrar nada si no aplica
    }
}