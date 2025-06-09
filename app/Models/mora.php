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
        return self::calcularMontoMora($this->cuotaGrupal, $this->fecha_atraso ?? now(), $this->estado_mora);
    }

    // Método central de cálculo de mora - CORREGIDO CON VALIDACIÓN DE ESTADO PAGADA
    public static function calcularMontoMora($cuota, $fechaAtraso = null, $estadoMora = null)
    {
        if (!$cuota || !$cuota->prestamo || !$cuota->prestamo->grupo) {
            return 0;
        }

        // Si la mora está pagada, no calcular días adicionales
        if ($estadoMora === 'pagada') {
            // Obtener la mora existente para usar sus días de atraso congelados
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

        // Calcular días de atraso correctamente solo si no está pagada
        $diasAtraso = 0;
        if ($fechaAtraso->greaterThan($fechaVencimiento)) {
            // Usar diffInDays en el orden correcto: fecha_posterior - fecha_anterior
            $diasAtraso = $fechaVencimiento->diffInDays($fechaAtraso);
        }

        $integrantes = $cuota->prestamo->grupo->clientes()->count();

        // El cálculo debería dar un resultado positivo
        $montoMora = $integrantes * $diasAtraso * 1;
                
        return $montoMora;
    }

    // Actualiza la fecha de atraso si aplica - MODIFICADO
    public function actualizarDiasAtraso()
    {
        // Solo actualizar si NO está pagada
        if ($this->estado_mora === 'pagada') {
            return; // No hacer nada si ya está pagada
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
        } elseif ($user->hasAnyRole(['super_admin', 'Jefe de operaciones', 'Jefe de creditos'])) {
            return $query; // Mostrar todas las moras
        }

        return $query->whereRaw('1 = 0'); // No mostrar nada si no aplica
    }
}