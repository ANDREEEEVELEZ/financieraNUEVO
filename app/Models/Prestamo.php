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
    ];

    protected $casts = [
        'tasa_interes' => 'integer',
        'monto_prestado_total' => 'decimal:2',
        'monto_devolver' => 'decimal:2',
        'cantidad_cuotas' => 'integer',
        'fecha_prestamo' => 'date',
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

    public function retanqueos()
    {
        return $this->hasMany(Retanqueo::class);
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
            $pagadas = $this->cuotasGrupales()->where('estado_pago', 'Pagado')->count();
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
            $pagadas = $this->cuotasGrupales()->where('estado_pago', 'Pagado')->count();

            if ($total > 0 && $total === $pagadas) {
                $this->estado = 'Finalizado';
                $this->save();
            }
        }
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
