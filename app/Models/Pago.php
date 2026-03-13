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
        'codigo_operacion',
        'monto_pagado',
        'monto_mora_pagada',
        'fecha_pago',
        'estado_pago',
        'observaciones',
    ];


    protected $casts = [
        'fecha_pago' => 'datetime',
        'saldo_pendiente' => 'decimal:2',
    ];

    // Mutator solo para código de operación (mantener mayúsculas para códigos)
    public function setCodigoOperacionAttribute($value)
    {
        $this->attributes['codigo_operacion'] = strtoupper($value);
    }

    public function setObservacionesAttribute($value)
    {
        $this->attributes['observaciones'] = strtoupper($value);
    }
    protected $attributes = [
    'estado_pago' => 'pendiente',
    ];


    public function cuotaGrupal()
    {

        return $this->belongsTo(CuotasGrupales::class, 'cuota_grupal_id');
    }

    /**
     * Relación: Un pago tiene muchos detalles de pago
     */
    public function detallesPago()
    {
        return $this->hasMany(DetallePago::class, 'pago_id');
    }

    public function ingreso(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Ingreso::class);
    }


    public function tieneIngreso(): bool
    {
        return $this->ingreso()->exists();
    }


    public function scopeSinIngreso($query)
    {
        return $query->whereDoesntHave('ingreso');
    }


    public function setMontoPagadoAttribute($value)
    {
        $this->attributes['monto_pagado'] = preg_replace('/[^\d.]/', '', $value);
    }


    public function getFechaPagoFormattedAttribute()
    {
        return $this->fecha_pago ? $this->fecha_pago->format('d/m/Y H:i') : null;
    }

    public function aprobar()
    {
        $prestamo = $this->cuotaGrupal?->prestamo;
        if (!$prestamo || !in_array($prestamo->estado, Prestamo::ESTADOS_ACTIVOS)) {
            throw new \Exception('Solo se pueden aprobar pagos de préstamos en estado Activo, Al Día o En Mora.');
        }
        if ($this->estado_pago !== 'pendiente') {
        return;
    }

    $this->estado_pago = 'aprobado';
    $this->save();

    $cuota = $this->cuotaGrupal;
    if ($cuota) {
        $montoCuota = floatval($cuota->monto_cuota_grupal);
        $montoPagado = floatval($this->monto_pagado);

        // Calcular mora generada hasta la fecha de este pago
        $montoMoraTotal = $cuota->mora ? abs($cuota->mora->monto_mora_calculado) : 0;

        // Sumar pagos de mora previos (aprobados y distintos a este)
        $pagosMoraPrevios = $cuota->pagos()
            ->where('estado_pago', 'aprobado')
            ->where('id', '!=', $this->id)
            ->sum('monto_mora_pagada');

        // Lo primero que cubre este pago es la mora generada hasta este momento
        $saldoMoraPorPagar = max(0, $montoMoraTotal - $pagosMoraPrevios);
        $moraPagadaEnEstePago = min($montoPagado, $saldoMoraPorPagar);
        $this->monto_mora_pagada = $moraPagadaEnEstePago;
        $this->save();

        // El resto del pago va a cuota
        $montoRestanteParaCuota = max(0, $montoPagado - $moraPagadaEnEstePago);

        // Suma pagos válidos de cuota (solo lo que fue a cuota, incluyendo este pago)
        $pagosAprobados = $cuota->pagos()->where('estado_pago', 'aprobado')->get();
        $totalPagadoCuota = 0;
        foreach ($pagosAprobados as $pago) {
            $totalPagadoCuota += max(0, $pago->monto_pagado - $pago->monto_mora_pagada);
        }

        // Calcula saldos
        $saldoCuotaPendiente = max(0, $montoCuota - $totalPagadoCuota);
        $saldoMoraPendiente = max(0, $montoMoraTotal - ($pagosMoraPrevios + $moraPagadaEnEstePago));
        $saldoTotalPendiente = $saldoCuotaPendiente + $saldoMoraPendiente;

        // Actualiza mora
        if ($cuota->mora) {
            if ($saldoMoraPendiente == 0 && $saldoCuotaPendiente == 0) {
                $cuota->mora->estado_mora = 'pagada';
            } elseif ($saldoMoraPendiente > 0 && $saldoCuotaPendiente == 0) {
                $cuota->mora->estado_mora = 'pendiente';
            } else {
                $cuota->mora->estado_mora = 'parcialmente_pagada';
            }
            $cuota->mora->save();
        }

        // Actualiza saldo y estado cuota
        $cuota->saldo_pendiente = $saldoTotalPendiente;
        if ($saldoTotalPendiente == 0) {
            $cuota->estado_pago = 'pagado';
            $cuota->estado_cuota_grupal = 'cancelada';
        } else {
            $cuota->estado_pago = 'parcial';
            $cuota->estado_cuota_grupal = $saldoMoraPendiente > 0 ? 'mora' : 'vigente';
        }
        $cuota->save();

        // Actualiza estado préstamo si aplica
        $prestamo = $cuota->prestamo;
        if ($prestamo) {
            $prestamo->verificarYActualizarEstado();
        }
    }
}    public function rechazar()
    {
        $prestamo = $this->cuotaGrupal?->prestamo;
        if (!$prestamo || !in_array($prestamo->estado, Prestamo::ESTADOS_ACTIVOS)) {
            throw new \Exception('Solo se pueden rechazar pagos de préstamos en estado Activo, Al Día o En Mora.');
        }

            if ($this->estado_pago !== 'pendiente') {
                return;
            }

            $this->estado_pago = 'rechazado';
            $this->save();

            $cuota = $this->cuotaGrupal;
            if ($cuota) {

                $pagosValidos = $cuota->pagos()
                    ->where('estado_pago', 'aprobado')
                    ->where('id', '!=', $this->id)
                    ->get();


                $totalPagado = $pagosValidos->sum('monto_pagado');
                $totalAPagar = $cuota->monto_cuota_grupal;
                $montoMora = $cuota->mora ? abs($cuota->mora->monto_mora_calculado) : 0;

                if ($pagosValidos->isEmpty()) {

                    $cuota->estado_pago = 'pendiente';
                    $cuota->saldo_pendiente = $totalAPagar;
                    $cuota->estado_cuota_grupal = $cuota->mora ? 'mora' : 'vigente';
                    if ($cuota->mora) {
                        $cuota->mora->estado_mora = 'pendiente';
                        $cuota->mora->save();
                    }
                } else {

                    if ($totalPagado >= ($totalAPagar + $montoMora)) {
                    $cuota->update(['saldo_pendiente' => 0]);
                    $cuota->estado_pago = 'pagado';
                    $cuota->estado_cuota_grupal = 'cancelada';
                    if ($cuota->mora) {
                        $cuota->mora->estado_mora = 'pagada';
                        $cuota->mora->save();
                    }
                } else {
                    $cuota->update(['saldo_pendiente' => round($totalAPagar - $totalPagado, 2)]);
                    $cuota->estado_pago = $totalPagado > 0 ? 'parcial' : 'pendiente';
                    $cuota->estado_cuota_grupal = $cuota->mora ? 'mora' : 'vigente';
                    if ($cuota->mora) {
                        $cuota->mora->estado_mora = $totalPagado > 0 ? 'parcial' : 'pendiente';
                        $cuota->mora->save();
                    }
                }

                }
                $cuota->save();

                // Verificar si el préstamo debe cambiar su estado
                $prestamo = $cuota->prestamo;
                if ($prestamo) {
                    $prestamo->verificarYActualizarEstado();
                }
            }
        }
        public function grupo()
        {
            return $this->hasOneThrough(Grupo::class, Prestamo::class, 'id', 'id', 'cuota_grupal_id', 'grupo_id');
        }

    /**
     * Revierte un pago aprobado a pendiente (JO/super_admin).
     * Recalcula saldos de la cuota y mora.
     */
    public function revertir(): bool
    {
        if ($this->estado_pago !== 'aprobado') {
            return false;
        }

        $this->estado_pago = 'pendiente';
        $this->monto_mora_pagada = 0;
        $this->save();

        $cuota = $this->cuotaGrupal;
        if ($cuota) {
            // Recalcular saldos sin este pago
            $pagosAprobados = $cuota->pagos()
                ->where('estado_pago', 'aprobado')
                ->where('id', '!=', $this->id)
                ->get();

            $totalPagado = $pagosAprobados->sum('monto_pagado');
            $totalAPagar = floatval($cuota->monto_cuota_grupal);
            $montoMora = $cuota->mora ? abs($cuota->mora->monto_mora_calculado) : 0;

            $saldoPendiente = max(0, ($totalAPagar + $montoMora) - $totalPagado);
            $cuota->saldo_pendiente = round($saldoPendiente, 2);

            if ($totalPagado <= 0) {
                $cuota->estado_pago = 'pendiente';
                $cuota->estado_cuota_grupal = $cuota->mora ? 'mora' : 'vigente';
            } else {
                $cuota->estado_pago = 'parcial';
                $cuota->estado_cuota_grupal = $cuota->mora ? 'mora' : 'vigente';
            }
            $cuota->save();

            // Recalcular mora
            if ($cuota->mora) {
                $cuota->mora->estado_mora = 'pendiente';
                $cuota->mora->save();
            }

            // Verificar estado del préstamo
            $prestamo = $cuota->prestamo;
            if ($prestamo) {
                $prestamo->verificarYActualizarEstado();
            }
        }

        return true;
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($pago) {
            $prestamo = $pago->cuotaGrupal?->prestamo;
            if (!$prestamo || !in_array($prestamo->estado, Prestamo::ESTADOS_ACTIVOS)) {
                throw new \Exception('No se pueden registrar pagos para préstamos que no estén en estado Activo, Al Día o En Mora.');
            }
        });
    }

}
