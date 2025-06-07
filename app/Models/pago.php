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
    ];
    protected $attributes = [
    'estado_pago' => 'pendiente',
    ];


    public function cuotaGrupal()
    {
     
        return $this->belongsTo(CuotasGrupales::class, 'cuota_grupal_id');
    }
    /**
     * Relación con el modelo Ingreso
     */
    public function ingreso(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Ingreso::class);
    }

    /**
     * Verificar si el pago ya tiene un ingreso asociado
     */
    public function tieneIngreso(): bool
    {
        return $this->ingreso()->exists();
    }

    /**
     * Scope para pagos sin ingreso asociado
     */
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
        // Validar que el préstamo esté aprobado
        $prestamo = $this->cuotaGrupal?->prestamo;
        if (!$prestamo || strtolower($prestamo->estado) !== 'aprobado') {
            throw new \Exception('Solo se pueden aprobar pagos de préstamos aprobados.');
        }

        if (strtolower($this->estado_pago) !== 'pendiente') {
            return;
        }

        $this->estado_pago = 'aprobado';
        $this->save();

        $cuota = $this->cuotaGrupal;
        if ($cuota) {
            $montoCuota = $cuota->monto_cuota_grupal;
            $montoPagado = floatval($this->monto_pagado);
            $montoMora = $cuota->mora ? abs($cuota->mora->monto_mora_calculado) : 0;
            $totalAPagar = $montoCuota + $montoMora;

            // Si el pago es de cuota + mora
            if ($this->tipo_pago === 'pago_completo') {
                if ($montoPagado >= $totalAPagar) {
                    $cuota->saldo_pendiente = 0;
                    $cuota->estado_pago = 'pagado';
                    if ($cuota->mora) {
                        $cuota->mora->estado_mora = 'pagada';
                        $cuota->mora->save();
                    }
                    $cuota->estado_cuota_grupal = 'cancelada';
                } else {
                    $cuota->saldo_pendiente = $totalAPagar - $montoPagado;
                    $cuota->estado_pago = 'parcial';
                    $cuota->estado_cuota_grupal = 'mora';
                    if ($cuota->mora) {
                        $cuota->mora->estado_mora = 'parcial';
                        $cuota->mora->save();
                    }
                }
            }
            // Si el pago es solo de la cuota
            else if ($this->tipo_pago === 'pago_parcial') {
                if ($montoPagado >= $cuota->saldo_pendiente) {
                    $cuota->saldo_pendiente = 0;
                    $cuota->estado_pago = 'pagado';
                    // Si tiene mora, el estado sigue siendo mora
                    $cuota->estado_cuota_grupal = $cuota->mora ? 'mora' : 'cancelada';
                } else {
                    $cuota->saldo_pendiente -= $montoPagado;
                    $cuota->estado_pago = 'parcial';
                    $cuota->estado_cuota_grupal = $cuota->mora ? 'mora' : 'vigente';
                }
            }

            $cuota->save();
        }
    }
        public function rechazar()
        {
            // Validar que el préstamo esté aprobado
            $prestamo = $this->cuotaGrupal?->prestamo;
            if (!$prestamo || strtolower($prestamo->estado) !== 'aprobado') {
                throw new \Exception('Solo se pueden rechazar pagos de préstamos aprobados.');
            }

            if (strtolower($this->estado_pago) !== 'pendiente') {
                return;
            }

            $this->estado_pago = 'Rechazado';
            $this->save();

            $cuota = $this->cuotaGrupal;
            if ($cuota) {
                // Obtener todos los pagos válidos (no rechazados) de esta cuota
                $pagosValidos = $cuota->pagos()
                    ->where('estado_pago', 'Aprobado')
                    ->where('id', '!=', $this->id)
                    ->get();

                // Recalcular el saldo y estado basado en los pagos válidos
                $totalPagado = $pagosValidos->sum('monto_pagado');
                $totalAPagar = $cuota->monto_cuota_grupal;
                $montoMora = $cuota->mora ? abs($cuota->mora->monto_mora_calculado) : 0;
                
                if ($pagosValidos->isEmpty()) {
                    // No hay otros pagos válidos, restaurar al estado original
                    $cuota->estado_pago = 'Pendiente';
                    $cuota->saldo_pendiente = $totalAPagar;
                    $cuota->estado_cuota_grupal = $cuota->mora ? 'mora' : 'vigente';
                    if ($cuota->mora) {
                        $cuota->mora->estado_mora = 'pendiente';
                        $cuota->mora->save();
                    }
                } else {
                    // Hay pagos válidos, actualizar según el total pagado
                    if ($totalPagado >= ($totalAPagar + $montoMora)) {
                        $cuota->saldo_pendiente = 0;
                        $cuota->estado_pago = 'Aprobado';
                        $cuota->estado_cuota_grupal = 'cancelada';
                        if ($cuota->mora) {
                            $cuota->mora->estado_mora = 'pagada';
                            $cuota->mora->save();
                        }
                    } else {
                        $cuota->saldo_pendiente = $totalAPagar - $totalPagado;
                        $cuota->estado_pago = 'Aprobado';
                        $cuota->estado_cuota_grupal = $cuota->mora ? 'mora' : 'vigente';
                        if ($cuota->mora) {
                            $cuota->mora->estado_mora = $totalPagado > 0 ? 'parcial' : 'pendiente';
                            $cuota->mora->save();
                        }
                    }
                }
                $cuota->save();
            }
        }
        public function grupo()
        {
            return $this->hasOneThrough(Grupo::class, Prestamo::class, 'id', 'id', 'cuota_grupal_id', 'grupo_id');
        }

        protected static function boot()
        {
            parent::boot();

            static::creating(function ($pago) {
                $prestamo = $pago->cuotaGrupal?->prestamo;
                if (!$prestamo || strtolower($prestamo->estado) !== 'aprobado') {
                    throw new \Exception('No se pueden registrar pagos para préstamos que no estén aprobados.');
                }
            });
        }

}
