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

    public function CuotaGrupal()
    {
        return $this->belongsTo(CuotasGrupales::class, 'cuota_grupal_id');
    }

    public function getEstadoMoraFormattedAttribute()
    {
        return ucfirst(str_replace('_', ' ', $this->estado_mora));
    }

    public function getMontoMoraCalculadoAttribute()
    {
        $cuota = $this->cuotaGrupal;
        if (!$cuota || !$cuota->prestamo || !$cuota->prestamo->grupo) {
            return 0;
        }

        $fechaAtraso = $this->fecha_atraso ? \Carbon\Carbon::parse($this->fecha_atraso)->startOfDay() : now()->startOfDay();
        $fechaVencimiento = \Carbon\Carbon::parse($cuota->fecha_vencimiento)->addDay()->startOfDay();

        $diasAtraso = 0;
        if ($fechaAtraso->greaterThan($fechaVencimiento)) {
            $diasAtraso = $fechaAtraso->diffInDays($fechaVencimiento);
        }

        $integrantes = $cuota->prestamo->grupo->clientes()->count();

        return $integrantes * $diasAtraso * 1;
    }


        public static function calcularMontoMora($cuota, $fechaAtraso = null)
    {
        if (!$cuota || !$cuota->prestamo || !$cuota->prestamo->grupo) {
            return 0;
        }

        $fechaAtraso = $fechaAtraso ? \Carbon\Carbon::parse($fechaAtraso)->startOfDay() : now()->startOfDay();
        $fechaVencimiento = \Carbon\Carbon::parse($cuota->fecha_vencimiento)->addDay()->startOfDay();

        $diasAtraso = 0;
        if ($fechaAtraso->greaterThan($fechaVencimiento)) {
            $diasAtraso = $fechaAtraso->diffInDays($fechaVencimiento);
        }

        $integrantes = $cuota->prestamo->grupo->clientes()->count();

        return $integrantes * $diasAtraso * 1;
    }

    public function actualizarDiasAtraso() {
        $fechaVencimiento = \Carbon\Carbon::parse($this->cuotaGrupal->fecha_vencimiento)->addDay()->startOfDay();

        if ($this->estado_mora === 'pendiente' || $this->estado_mora === 'parcialmente_pagada') {
            $fechaAtraso = $this->fecha_atraso ? \Carbon\Carbon::parse($this->fecha_atraso)->startOfDay() : now()->startOfDay();

            if ($fechaAtraso->greaterThan($fechaVencimiento)) {
                $this->fecha_atraso = $fechaAtraso;
                $this->save();
            }
        }
        // No recalcular días de atraso si la mora está pagada
    }

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
