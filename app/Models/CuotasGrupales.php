<?php


namespace App\Models;


use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Mora;


class CuotasGrupales extends Model
{
    use HasFactory;


    protected $table = 'cuotas_grupales';


    protected $fillable = [
        'prestamo_id',
        'numero_cuota',
        'monto_cuota_grupal',
        'fecha_vencimiento',
        'saldo_pendiente',
        'estado_cuota_grupal',
        'estado_pago',
    ];


    protected $casts = [
        'fecha_vencimiento' => 'date',
        'monto_cuota_grupal' => 'decimal:2',
        'saldo_pendiente' => 'decimal:2',
    ];


    public function prestamo()
    {
        return $this->belongsTo(Prestamo::class, 'prestamo_id');
    }


    // Tu método estadoLegible
        public function mora()
        {
            return $this->hasOne(Mora::class, 'cuota_grupal_id');
        }
        public function pagos()
        {
            return $this->hasMany(Pago::class, 'cuota_grupal_id');
        }


        public function getMontoTotalAPagarAttribute()
        {
            $saldo = $this->saldo_pendiente ?? 0;
            $montoMora = 0;
            if ($this->mora && in_array($this->mora->estado_mora, ['pendiente', 'parcialmente_pagada'])) {
                $montoMora = Mora::calcularMontoMora($this, $this->mora->fecha_atraso); // ✅ Usa el método correcto
            }
            return $saldo + $montoMora;
        }
            public function getSaldoTotalPendienteAttribute()
            {
                $pagos = $this->pagos()->where('estado_pago', 'Aprobado')->sum('monto_pagado');
                $mora = $this->mora ? abs($this->mora->monto_mora_calculado) : 0;
                return round(max(($this->monto_cuota_grupal + $mora) - $pagos, 0), 2);
            }
      public function saldoPendiente()
        {
            $montoCuota = floatval($this->monto_cuota_grupal);
            $montoMoraTotal = $this->mora ? abs($this->mora->monto_mora_calculado) : 0;
            $pagosAprobados = $this->pagos()->where('estado_pago', 'Aprobado')->sum('monto_pagado');

            // Aplicar pagos: PRIMERO A LA MORA, DESPUÉS A LA CUOTA
            $saldoMoraPendiente = max(0, $montoMoraTotal - $pagosAprobados);
            $pagoAplicadoACuota = max(0, $pagosAprobados - $montoMoraTotal);
            $saldoCuotaPendiente = max(0, $montoCuota - $pagoAplicadoACuota);

            return round($saldoMoraPendiente + $saldoCuotaPendiente, 2);
        }

        /**
         * Obtiene el saldo pendiente de mora
         */
        public function getSaldoMoraPendiente()
        {
            if (!$this->mora) {
                return 0;
            }
            $moraGenerada = abs($this->mora->monto_mora_calculado);
            $pagadoMora = $this->pagos()->where('estado_pago', 'Aprobado')->sum('monto_mora_pagada');
            return max($moraGenerada - $pagadoMora, 0);
        }

        /**
         * Obtiene el saldo pendiente de la cuota (sin mora)
         */
        public function getSaldoCuotaPendiente()
        {
            $montoCuota = floatval($this->monto_cuota_grupal);
            $pagosAprobados = $this->pagos()->where('estado_pago', 'Aprobado')->sum('monto_pagado');
            $pagadoMora = $this->pagos()->where('estado_pago', 'Aprobado')->sum('monto_mora_pagada');
            $pagadoCuota = max(0, $pagosAprobados - $pagadoMora);
            return max(0, $montoCuota - $pagadoCuota);
        }

        /**
         * Obtiene el monto de mora ya pagada
         */
        public function getMoraPagada()
        {
            if (!$this->mora) {
                return 0;
            }
            // Suma solo lo que se pagó de mora, no lo que se generó después
            return $this->pagos()->where('estado_pago', 'Aprobado')->sum('monto_mora_pagada');
        }


}
