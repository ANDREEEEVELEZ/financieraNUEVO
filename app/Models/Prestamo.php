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
        'tasa_interes' => 'float',
        'monto_prestado_total' => 'float',
        'monto_devolver' => 'float',
        'cantidad_cuotas' => 'integer',
        'fecha_prestamo' => 'date',
    ];

    public function grupo()
    {
        return $this->belongsTo(Grupo::class, 'grupo_id');
    }
        public function cuotasGrupales()
    {
        return $this->hasMany(CuotasGrupales::class);
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
            return $query; // Mostrar todos los préstamos
        }

        return $query->whereRaw('1 = 0'); // No mostrar nada si no aplica
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
}