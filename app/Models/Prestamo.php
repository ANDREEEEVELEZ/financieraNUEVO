<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Prestamo extends Model
{
    use HasFactory;

    protected $table = 'prestamos';

    protected $fillable = [
        'grupo_id',
        'tasa_interes',
        'monto_prestado_total',
        'monto_devolver',
        'cantidad_cuotas',
        'fecha_prestamo',
        'frecuencia',
        'estado',
        'calificacion',
        'descripcion',
        'es_retanqueo',
        'prestamo_origen_id',
    ];

    protected $casts = [
        'tasa_interes' => 'integer',
        'monto_prestado_total' => 'decimal:2',
        'monto_devolver' => 'decimal:2',
        'cantidad_cuotas' => 'integer',
        'fecha_prestamo' => 'date',
        'es_retanqueo' => 'boolean',
    ];

    // Relaciones
    public function grupo()
    {
        return $this->belongsTo(Grupo::class, 'grupo_id');
    }

    public function cuotasGrupales()
    {
        return $this->hasMany(CuotasGrupales::class, 'prestamo_id');
    }

    public function prestamoIndividual()
    {
        return $this->hasMany(PrestamoIndividual::class);
    }

    public function egresos()
    {
        return $this->hasMany(Egreso::class);
    }

    // Relaciones para retanqueos
    public function prestamoOrigen()
    {
        return $this->belongsTo(Prestamo::class, 'prestamo_origen_id');
    }

    public function prestamosRetanqueados()
    {
        return $this->hasMany(Prestamo::class, 'prestamo_origen_id');
    }

    public function retanqueoComoAntiguo()
    {
        return $this->hasOne(Retanqueo::class, 'prestamo_id');
    }

    public function retanqueoComoNuevo()
    {
        return $this->hasOne(Retanqueo::class, 'prestamo_nuevo_id');
    }


    public function scopeVisiblePorUsuario($query, $user)
    {
        if ($user->hasRole('Asesor')) {
            $asesor = \App\Models\Asesor::where('user_id', $user->id)->first();
            if ($asesor) {
                return $query->whereHas('grupo', function ($subQuery) use ($asesor) {
                    $subQuery->where('asesor_id', $asesor->id);
                });
            }
        } elseif ($user->hasAnyRole(['super_admin', 'Jefe de operaciones', 'Jefe de creditos'])) {
            return $query;
        }

        return $query->whereRaw('1 = 0');
    }

    public function estaFinalizado()
    {
        return $this->estado === 'Finalizado';
    }

    // Accessor para estado visible en tabla
    public function getEstadoVisibleAttribute()
    {
        if ($this->estado === 'Aprobado') {
            $total = $this->cuotasGrupales()->count();
            $pagadas = $this->cuotasGrupales()->where('estado_pago', 'pagado')->count();
            if ($pagadas > 0 && $pagadas < $total) {
                return 'Activo';
            }
        }

        return $this->estado;
    }

    // Accessor para asegurar que el monto total se calcule correctamente
    public function getMontoTotalCalculadoAttribute()
    {
        return $this->prestamoIndividual()->sum('monto_prestado_individual');
    }

    public function getMontoTotalDevolverCalculadoAttribute()
    {
        return $this->prestamoIndividual()->sum('monto_devolver_individual');
    }

    // Método para actualizar el estado automáticamente
    public function actualizarEstadoAutomaticamente()
    {
        if ($this->estado === 'Aprobado') {
            $total = $this->cuotasGrupales()->count();
            $pagadas = $this->cuotasGrupales()->where('estado_pago', 'pagado')->count();

            if ($total > 0 && $total === $pagadas) {
                $this->estado = 'Finalizado';
                $this->save();
            }
        }
    }

    /**
     * Método para verificar y actualizar el estado del préstamo basándose en el estado de las cuotas
     */
    public function verificarYActualizarEstado()
    {
        if ($this->estado === 'Aprobado') {
            $totalCuotas = $this->cuotasGrupales()->count();
            $cuotasPagadas = $this->cuotasGrupales()->where('estado_pago', 'pagado')->count();
            
            \Illuminate\Support\Facades\Log::info('Verificando estado del préstamo', [
                'prestamo_id' => $this->id,
                'estado_actual' => $this->estado,
                'total_cuotas' => $totalCuotas,
                'cuotas_pagadas' => $cuotasPagadas,
            ]);
            
            if ($totalCuotas > 0 && $cuotasPagadas === $totalCuotas) {
                $this->estado = 'Finalizado';
                $this->save();
                
                // Actualizar también los préstamos individuales
                $this->prestamoIndividual()->update(['estado' => 'Finalizado']);
                
                \Illuminate\Support\Facades\Log::info('Estado del préstamo actualizado a Finalizado', [
                    'prestamo_id' => $this->id,
                ]);
                
                return true;
            }
        }
        
        return false;
    }

    /**
     * Método para aprobar un préstamo
     */
    public function aprobar()
    {
        if (strtolower($this->estado) !== 'pendiente') {
            return;
        }

        $this->estado = 'Aprobado';
        $this->save();

        // Actualizar el estado del grupo asociado
        if ($this->grupo) {
            $this->grupo->estado_grupo = 'Activo';
            $this->grupo->save();
        }

        // Actualizar el estado de los préstamos individuales
        $this->prestamoIndividual()->update(['estado' => 'Aprobado']);
    }

    /**
     * Método para rechazar un préstamo
     */
    public function rechazar()
    {
        if (strtolower($this->estado) !== 'pendiente') {
            return;
        }

        $this->estado = 'Rechazado';
        $this->save();

        // Actualizar el estado del grupo asociado
        if ($this->grupo) {
            $this->grupo->estado_grupo = 'Activo';
            $this->grupo->save();
        }

        // Actualizar el estado de los préstamos individuales
        $this->prestamoIndividual()->update(['estado' => 'Rechazado']);
    }

    // Método para sincronizar los montos totales basándose en los préstamos individuales
    public function sincronizarMontosTotal()
    {
        if ($this->prestamoIndividual()->count() > 0) {
            $montoTotal = $this->prestamoIndividual()->sum('monto_prestado_individual');
            $montoDevolver = $this->prestamoIndividual()->sum('monto_devolver_individual');
            
            $this->updateQuietly([
                'monto_prestado_total' => round($montoTotal, 2),
                'monto_devolver' => round($montoDevolver, 2),
            ]);
            
            return $this->fresh();
        }
        
        return $this;
    }
}
