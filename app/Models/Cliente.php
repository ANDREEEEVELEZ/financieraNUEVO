<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;


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
        'asesor_id',
    ];

    protected $casts = [
        'ciclo' => 'integer',
    ];


    // Mutators para convertir automáticamente a mayúsculas
    public function setInfocorpAttribute($value)
    {
        $this->attributes['infocorp'] = strtoupper($value);
    }

    public function setActividadAttribute($value)
    {
        $this->attributes['actividad'] = strtoupper($value);
    }

    public function setCondicionViviendaAttribute($value)
    {
        $this->attributes['condicion_vivienda'] = strtoupper($value);
    }

    public function setCondicionPersonalAttribute($value)
    {
        $this->attributes['condicion_personal'] = strtoupper($value);
    }

    public function setEstadoClienteAttribute($value)
    {
        $this->attributes['estado_cliente'] = strtoupper($value);
    }


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
     * Relación con préstamos individuales
     */
    public function prestamosIndividuales()
    {
        return $this->hasMany(PrestamoIndividual::class);
    }

    /**
     * Relación con préstamos individuales completados
     */
    public function prestamosCompletados()
    {
        return $this->hasMany(PrestamoIndividual::class)->where('estado', 'Completado');
    }


    /**
     * Verifica si el cliente ya pertenece a un grupo activo
     */
    public function tieneGrupoActivo(): bool
    {
        return $this->grupos()
            ->where('estado_grupo', 'ACTIVO')
            ->exists();
    }


    /**
     * Obtiene el grupo activo del cliente
     */
    public function getGrupoActivoAttribute()
    {
        return $this->grupos()
            ->where('estado_grupo', 'ACTIVO')
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
        } elseif ($user->hasAnyRole(['super_admin', 'Jefe de operaciones', 'Jefe de creditos'])) {
            return $query; // Mostrar todos los clientes
        }

        return $query->whereRaw('1 = 0'); // No mostrar nada si no aplica
    }


    /**
     * Accessor para obtener el ciclo en formato romano
     */
    public function getCicloRomanoAttribute()
    {
        return \App\Helpers\CicloHelper::normalize($this->ciclo);
    }

    /**
     * Accessor para obtener el monto máximo según el ciclo
     */
    public function getMontoMaximoAttribute()
    {
        return \App\Helpers\CicloHelper::getMontoMaximo($this->ciclo_romano);
    }

    /**
     * Obtiene el número de préstamos completados
     */
    public function getPrestamosCompletadosCountAttribute()
    {
        return $this->prestamosCompletados()->count();
    }

    /**
     * Verifica si el cliente puede subir de ciclo
     */
    public function puedeSubirCiclo()
    {
        return \App\Helpers\CicloHelper::puedeSubirCiclo($this->ciclo, $this->prestamos_completados_count);
    }

    /**
     * Préstamos directamente asociados a este cliente (préstamos individuales en el modelo Prestamo).
     */
    public function prestamos(): HasMany
    {
        return $this->hasMany(Prestamo::class, 'cliente_id');
    }

    /**
     * Scoring vigente del cliente.
     */
    public function scoringVigente(): HasOne
    {
        return $this->hasOne(ClienteScoring::class)->where('vigente', true)->latestOfMany();
    }

    // ── Scopes para el panel asesor ─────────────────────────────────────

    /**
     * Filtra clientes del asesor por su asesor_id (apunta a asesores.id).
     *
     * @param  Builder  $query
     * @param  Asesor   $asesor
     */
    public function scopeOfAsesor(Builder $query, Asesor $asesor): Builder
    {
        return $query->where('asesor_id', $asesor->id);
    }

    /**
     * Clientes que tienen al menos un préstamo en estado activo.
     */
    public function scopeActivo(Builder $query): Builder
    {
        return $query->whereHas('prestamos', function (Builder $q) {
            $q->whereIn('estado', Prestamo::ESTADOS_ACTIVOS);
        });
    }

    /**
     * Clientes que tienen al menos una cuota_individual en estado vencida.
     */
    public function scopeConMoraActiva(Builder $query): Builder
    {
        return $query->whereHas('prestamos.cuotasIndividuales', function (Builder $q) {
            $q->where('estado', 'vencida');
        });
    }
}
