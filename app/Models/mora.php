<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\CuotasGrupales;
use Carbon\Carbon;

class Mora extends Model
{
    use HasFactory;

    protected $fillable = [
        'cuota_grupal_id',
        'fecha_atraso',
        'estado_mora',
    ];

    protected $casts = [
        'fecha_atraso' => 'date',
    ];

    // Relaciones
    public function cuotaGrupal()
    {
        return $this->belongsTo(CuotasGrupales::class, 'cuota_grupal_id');
    }

    // Estado de mora en formato legible
    public function getEstadoMoraFormattedAttribute()
    {
        return ucfirst(str_replace('_', ' ', $this->estado_mora));
    }

    // Calcula el monto de mora dinámicamente
    public function getMontoMoraCalculadoAttribute()
    {
        return self::calcularMontoMora($this->cuotaGrupal, $this->fecha_atraso ?? now());
    }

    // Método central de cálculo de mora
    public static function calcularMontoMora($cuota, $fechaAtraso = null)
    {
        if (!$cuota || !$cuota->prestamo || !$cuota->prestamo->grupo) {
            return 0;
        }

        $fechaAtraso = Carbon::parse($fechaAtraso)->startOfDay();
        $fechaVencimiento = Carbon::parse($cuota->fecha_vencimiento)->addDay()->startOfDay();

        $diasAtraso = 0;
        if ($fechaAtraso->greaterThan($fechaVencimiento)) {
            $diasAtraso = $fechaAtraso->diffInDays($fechaVencimiento);
        }

        $integrantes = $cuota->prestamo->grupo->clientes()->count();

        return $integrantes * $diasAtraso * 1;
    }

    // Actualiza la fecha de atraso si aplica
    public function actualizarDiasAtraso()
    {
        $fechaVencimiento = Carbon::parse($this->cuotaGrupal->fecha_vencimiento)->addDay()->startOfDay();

        if (in_array($this->estado_mora, ['pendiente', 'parcialmente_pagada'])) {
            $fechaAtraso = $this->fecha_atraso ? Carbon::parse($this->fecha_atraso)->startOfDay() : now()->startOfDay();

            if ($fechaAtraso->greaterThan($fechaVencimiento)) {
                $this->fecha_atraso = $fechaAtraso;
                $this->save();
            }
        }
    }

    // Filtro de visibilidad por usuario
    public function scopeVisiblePorUsuario($query, $user)
    {
        if ($user->hasRole('Asesor')) {
            $asesor = \App\Models\Asesor::where('user_id', $user->id)->first();
            if ($asesor) {
                return $query->whereHas('cuotaGrupal.prestamo.grupo', function ($subQuery) use ($asesor) {
                    $subQuery->where('asesor_id', $asesor->id);
                });
            }
        } elseif ($user->hasAnyRole(['super_admin', 'Jefe de operaciones', 'Jefe de credito'])) {
            return $query; // Mostrar todas las moras
        }

        return $query->whereRaw('1 = 0'); // No mostrar nada si no aplica
    }
}
