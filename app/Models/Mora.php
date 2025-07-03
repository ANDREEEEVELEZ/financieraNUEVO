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


    public function cuotaGrupal()
    {
        return $this->belongsTo(CuotasGrupales::class, 'cuota_grupal_id');
    }


    public function getEstadoMoraFormattedAttribute()
    {
        return ucfirst(str_replace('_', ' ', $this->estado_mora));
    }


    public function getDiasAtrasoAttribute()
    {
        if (!$this->cuotaGrupal) {
            return 0;
        }

        $fechaVencimiento = Carbon::parse($this->cuotaGrupal->fecha_vencimiento)->addDay()->startOfDay();


        if ($this->estado_mora === 'pagada' && $this->fecha_atraso) {
            $fechaAtrasoCongelada = Carbon::parse($this->fecha_atraso)->startOfDay();
            return max(0, $fechaVencimiento->diffInDays($fechaAtrasoCongelada));
        }


        $fechaActual = now()->startOfDay();
        return max(0, $fechaVencimiento->diffInDays($fechaActual));
    }


    public function getMontoMoraCalculadoAttribute()
    {
        return self::calcularMontoMora($this->cuotaGrupal, $this->fecha_atraso ?? now(), $this->estado_mora);
    }


    public static function calcularMontoMora($cuota, $fechaAtraso = null, $estadoMora = null)
    {
        if (!$cuota || !$cuota->prestamo || !$cuota->prestamo->grupo) {
            return 0;
        }


        if ($estadoMora === 'pagada') {

            $moraExistente = self::where('cuota_grupal_id', $cuota->id)->first();
            if ($moraExistente && $moraExistente->fecha_atraso) {
                $fechaVencimiento = Carbon::parse($cuota->fecha_vencimiento)->addDay()->startOfDay();
                $fechaAtrasoCongelada = Carbon::parse($moraExistente->fecha_atraso)->startOfDay();

                $diasAtraso = 0;
                if ($fechaAtrasoCongelada->greaterThan($fechaVencimiento)) {

                    $diasAtraso = $fechaVencimiento->diffInDays($fechaAtrasoCongelada);
                }

                $integrantes = $cuota->prestamo->grupo->clientes()->count();
                return $integrantes * $diasAtraso * 1;
            }
            return 0;
        }

        $fechaAtraso = Carbon::parse($fechaAtraso)->startOfDay();
        $fechaVencimiento = Carbon::parse($cuota->fecha_vencimiento)->addDay()->startOfDay();


        $diasAtraso = 0;
        if ($fechaAtraso->greaterThan($fechaVencimiento)) {

            $diasAtraso = $fechaVencimiento->diffInDays($fechaAtraso);
        }

        $integrantes = $cuota->prestamo->grupo->clientes()->count();


        $montoMora = $integrantes * $diasAtraso * 1;

        return $montoMora;
    }

    public function actualizarDiasAtraso()
    {

        if ($this->estado_mora === 'pagada') {
            return;
        }

        $fechaVencimiento = Carbon::parse($this->cuotaGrupal->fecha_vencimiento)->addDay()->startOfDay();

        if (in_array($this->estado_mora, ['pendiente', 'parcialmente_pagada'])) {
            $fechaAtraso = $this->fecha_atraso ? Carbon::parse($this->fecha_atraso)->startOfDay() : now()->startOfDay();

            if ($fechaAtraso->greaterThan($fechaVencimiento)) {
                $this->fecha_atraso = $fechaAtraso;
                $this->save();
            }
        }
    }

    public function congelarMora()
    {
        if ($this->estado_mora === 'pagada' && !$this->fecha_atraso) {
            $this->fecha_atraso = now();
            $this->save();
        }
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
        } elseif ($user->hasAnyRole(['super_admin', 'Jefe de operaciones', 'Jefe de creditos'])) {
            return $query;
        }

        return $query->whereRaw('1 = 0');
    }
}
