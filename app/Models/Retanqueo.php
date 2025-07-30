<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Retanqueo extends Model
{
    use HasFactory;

    // Protege el id; el resto asignable en masa
    protected $guarded = ['id'];

    // Casts acordes a la migración
    protected $casts = [
        'monto_retanqueo'                 => 'decimal:2',
        'monto_usado_para_cubrir_antiguo' => 'decimal:2',
        'monto_desembolsar'               => 'decimal:2',
        'monto_cuota'                     => 'decimal:2',
        'saldo_restante_prestamo_antiguo' => 'decimal:2',
        'prestamo_antiguo_estado'         => 'boolean',
        'fecha_aceptacion'                => 'date',
    ];

    // Relaciones
    public function prestamoAntiguo()
    {
        return $this->belongsTo(Prestamo::class, 'prestamo_id');
    }

    public function prestamoNuevo()
    {
        return $this->belongsTo(Prestamo::class, 'prestamo_nuevo_id');
    }

    public function retanqueosIndividuales()
    {
        return $this->hasMany(RetanqueoIndividual::class, 'retanqueo_id');
    }

    public function grupo()
    {
        return $this->hasOneThrough(
            Grupo::class,
            Prestamo::class,
            'id', // Foreign key en prestamos
            'id', // Foreign key en grupos
            'prestamo_id', // Local key en retanqueos
            'grupo_id' // Local key en prestamos
        );
    }

    // Scopes para filtrar por usuario
    public function scopeVisiblePorUsuario($query, $user)
    {
        if ($user->hasRole('Asesor')) {
            $asesor = \App\Models\Asesor::where('user_id', $user->id)->first();
            if ($asesor) {
                return $query->whereHas('prestamoAntiguo.grupo', function ($subQuery) use ($asesor) {
                    $subQuery->where('asesor_id', $asesor->id);
                });
            }
        } elseif ($user->hasAnyRole(['super_admin', 'Jefe de operaciones', 'Jefe de creditos'])) {
            return $query;
        }

        return $query->whereRaw('1 = 0');
    }

    // Métodos de estado
    public function esSolicitudPendiente()
    {
        return $this->estado_retanqueo === 'solicitud_pendiente';
    }

    public function estaAprobado()
    {
        return $this->estado_retanqueo === 'aprobado';
    }

    public function estaEjecutado()
    {
        return $this->estado_retanqueo === 'ejecutado';
    }

    public function estaRechazado()
    {
        return $this->estado_retanqueo === 'rechazado';
    }

    // Método para calcular totales automáticamente
    public function calcularTotales()
    {
        $totalRetanqueo = $this->retanqueosIndividuales()
            ->whereIn('participacion_tipo', ['retanquea', 'nueva'])
            ->sum('monto_solicitado');

        $totalCobertura = $this->retanqueosIndividuales()
            ->where('participacion_tipo', 'retanquea')
            ->sum('aporte_cobertura');

        $this->update([
            'monto_retanqueo' => $totalRetanqueo,
            'monto_usado_para_cubrir_antiguo' => $totalCobertura,
            'monto_desembolsar' => $totalRetanqueo - $totalCobertura,
        ]);

        return $this->fresh();
    }
}
