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
     * Direct prestamo_individual records for this client.
     *
     * Note: group loans (Prestamo) belong to Grupo, not directly to Cliente.
     * The original hasMany(Prestamo, 'cliente_id') was wrong — prestamos.cliente_id
     * does not exist in the schema. Group loans are accessible via grupos → prestamos.
     */
    public function prestamos(): HasMany
    {
        return $this->hasMany(PrestamoIndividual::class);
    }

    /**
     * Scoring vigente del cliente — latest by id among vigente=true records.
     *
     * Uses ofMany() with an inline filter so the subquery respects vigente=true,
     * instead of latestOfMany() which picks MAX(id) across ALL records first.
     */
    public function scoringVigente(): HasOne
    {
        return $this->hasOne(ClienteScoring::class)
            ->ofMany(['id' => 'max'], fn (Builder $q) => $q->where('vigente', true));
    }

    /**
     * Alias for scoring vigente — matches what ClienteResource expects via relationLoaded('scoring').
     * Eager-load as: ->with(['scoring' => fn($q) => $q->where('vigente', true)])
     */
    public function scoring(): HasOne
    {
        return $this->hasOne(ClienteScoring::class)->latestOfMany();
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
     * Clientes that belong to a group with at least one active loan.
     *
     * Corrected: prestamos are linked to grupos, not directly to clientes.
     * The original whereHas('prestamos') used a non-existent prestamos.cliente_id column.
     */
    public function scopeActivo(Builder $query): Builder
    {
        return $query->whereHas('grupos.prestamos', function (Builder $q) {
            $q->whereIn('estado', Prestamo::ESTADOS_ACTIVOS);
        });
    }

    /**
     * Clientes with at least one overdue cuota_individual.
     *
     * Corrected: cuotas are linked via prestamos → cuota_individual, and prestamos
     * belong to grupos, not directly to clientes.
     */
    public function scopeConMoraActiva(Builder $query): Builder
    {
        return $query->whereHas('grupos.prestamos.cuotasIndividuales', function (Builder $q) {
            $q->where('estado', 'vencida');
        });
    }
}
