<?php


namespace App\Models;


use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


class Pago extends Model
{
    use HasFactory;


    protected $fillable = [
        'cuota_grupal_id',
        'tipo_pago',
        'monto_pagado',
        'monto_mora_pagada',
        'fecha_pago',
        'estado_pago',
        'observaciones',
    ];


    protected $casts = [
        'fecha_pago' => 'datetime',
    ];
    protected $attributes = [
    'estado_pago' => 'pendiente',
    ];


    public function cuotaGrupal()
    {
     
        return $this->belongsTo(CuotasGrupales::class, 'cuota_grupal_id');
    }


    public function setMontoPagadoAttribute($value)
    {
        $this->attributes['monto_pagado'] = preg_replace('/[^\d.]/', '', $value);
    }


    public function getFechaPagoFormattedAttribute()
    {
        return $this->fecha_pago ? $this->fecha_pago->format('d/m/Y H:i') : null;
    }
    // En App\Models\Pago.php
    public function aprobar()
    {
        if (strtolower($this->estado_pago) !== 'pendiente') {
            return;
        }


        $this->estado_pago = 'Aprobado';
        $this->save();


        $cuota = $this->cuotaGrupal;
        $montoCuota = $cuota->monto_cuota_grupal;
        $montoPagado = floatval($this->monto_pagado);
        $montoMora = $cuota->mora ? abs($cuota->mora->monto_mora_calculado) : 0;


        // Si la cuota tiene mora pendiente, solo se puede cancelar todo si se paga cuota+mora
        if ($this->tipo_pago === 'cuota_mora' && $cuota->mora) {
            $totalAPagar = $cuota->saldo_pendiente + $montoMora;
            if ($montoPagado >= $totalAPagar) {
                $cuota->saldo_pendiente = 0;
                $cuota->mora->estado_mora = 'pagada';
                $cuota->mora->actualizarDiasAtraso();
                $cuota->mora->save();
                $cuota->estado_cuota_grupal = 'cancelada';
                $cuota->estado_pago = 'pagado';
                $cuota->save();
                return;
            }
            // Si no cubre todo, solo descuenta lo que corresponda
            $restanteParaCuota = min($montoPagado, $cuota->saldo_pendiente);
            $cuota->saldo_pendiente = max($cuota->saldo_pendiente - $restanteParaCuota, 0);
            $cuota->mora->estado_mora = 'parcial';
            $cuota->mora->save();
            $cuota->estado_pago = $cuota->saldo_pendiente > 0 ? 'parcial' : 'pagado';
            $cuota->estado_cuota_grupal = $cuota->saldo_pendiente > 0 ? 'mora' : 'cancelada';
            $cuota->save();
            return;
        }


        // Si paga solo cuota y hay mora pendiente, la mora sigue pendiente y la cuota no se cancela
        if ($this->tipo_pago === 'cuota') {
            if ($cuota->mora && in_array($cuota->mora->estado_mora, ['pendiente', 'parcial'])) {
                $cuota->saldo_pendiente = 0;
                $cuota->estado_pago = 'pagado';
                $cuota->estado_cuota_grupal = 'mora';
                $cuota->save();
                return;
            }
            if ($montoPagado >= $montoCuota) {
                $cuota->saldo_pendiente = 0;
                $cuota->estado_pago = 'pagado';
                if ($cuota->mora && $cuota->mora->estado_mora !== 'pagada') {
                    $cuota->estado_cuota_grupal = 'mora';
                } else {
                    $cuota->estado_cuota_grupal = 'cancelada';
                }
            } else {
                $nuevoSaldo = $cuota->saldo_pendiente - min($montoPagado, $montoCuota);
                $cuota->saldo_pendiente = $nuevoSaldo > 0 ? $nuevoSaldo : 0;
                $cuota->estado_pago = 'parcial';
            }
            $cuota->save();
        } elseif ($this->tipo_pago === 'pago_parcial') {
            $nuevoSaldo = $cuota->saldo_pendiente - $montoPagado;
            $cuota->saldo_pendiente = $nuevoSaldo > 0 ? $nuevoSaldo : 0;
            if ($nuevoSaldo <= 0) {
                $cuota->estado_pago = 'pagado';
                if ($cuota->mora && $cuota->mora->estado_mora !== 'pagada') {
                    $cuota->estado_cuota_grupal = 'mora';
                } else {
                    $cuota->estado_cuota_grupal = 'cancelada';
                }
            } else {
                $cuota->estado_pago = 'parcial';
            }
            $cuota->save();
        }
    }
        public function rechazar()
        {
            if (strtolower($this->estado_pago) !== 'pendiente') {
                return;
            }


            $this->estado_pago = 'Rechazado';
            $this->save();


            $cuota = $this->cuotaGrupal;
            if ($cuota) {
                $pagosValidos = $cuota->pagos()->where('estado_pago', '!=', 'Rechazado')->get();
                if ($pagosValidos->isEmpty()) {
                    $cuota->estado_cuota_grupal = 'vigente';
                    $cuota->estado_pago = 'pendiente';
                    $cuota->save();
                }
            }
        }
        public function grupo()
        {
            return $this->hasOneThrough(Grupo::class, Prestamo::class, 'id', 'id', 'cuota_grupal_id', 'grupo_id');
        }
}
