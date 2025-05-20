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
        'monto_mora',
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

        $fechaAtraso = $this->fecha_atraso ?? now();
        $fechaVencimiento = $cuota->fecha_vencimiento;

        $diasAtraso = Carbon::parse($fechaAtraso)->isAfter($fechaVencimiento)
            ? Carbon::parse($fechaAtraso)->diffInDays($fechaVencimiento)
            : 0;

        $integrantes = $cuota->prestamo->grupo->numero_integrantes ?? 0;

        return $integrantes * $diasAtraso * 1;
    }

    public static function calcularMontoMora($cuota, $fechaAtraso = null)
    {
        if (!$cuota || !$cuota->prestamo || !$cuota->prestamo->grupo) {
            return 0;
        }

        $fechaAtraso = $fechaAtraso ?? now();
        $fechaVencimiento = $cuota->fecha_vencimiento;

        $diasAtraso = Carbon::parse($fechaAtraso)->isAfter($fechaVencimiento)
            ? Carbon::parse($fechaAtraso)->diffInDays($fechaVencimiento)
            : 0;

        $integrantes = $cuota->prestamo->grupo->numero_integrantes ?? 0;

        return $integrantes * $diasAtraso * 1;
    }

}
