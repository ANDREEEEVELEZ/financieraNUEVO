<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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
        return $this->belongsTo(Cuotas_Grupales::class, 'cuota_grupal_id');
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



}
