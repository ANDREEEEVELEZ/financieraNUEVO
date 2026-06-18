<?php


namespace App\Models;


use App\Models\AplicacionPago;
use Illuminate\Database\Eloquent\Builder;
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
     * Relación: Un pago tiene muchas aplicaciones de pago (V2 — trazabilidad por CuotaIndividual).
     */
    public function aplicacionesPago()
    {
        return $this->hasMany(AplicacionPago::class, 'pago_id');
    }

    /**
     * Alias de compatibilidad para el recurso Filament.
     */
    public function detallesPago()
    {
        return $this->aplicacionesPago();
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

    // ── Scopes para el panel asesor ─────────────────────────────────────

    /**
     * Pagos pendientes de aprobación.
     */
    public function scopePendientes(Builder $query): Builder
    {
        return $query->where('estado_pago', 'pendiente');
    }

    /**
     * Pagos aprobados.
     */
    public function scopeAprobados(Builder $query): Builder
    {
        return $query->where('estado_pago', 'aprobado');
    }


    public function setMontoPagadoAttribute($value)
    {
        $this->attributes['monto_pagado'] = preg_replace('/[^\d.]/', '', $value);
    }


    public function getFechaPagoFormattedAttribute()
    {
        return $this->fecha_pago ? $this->fecha_pago->format('d/m/Y H:i') : null;
    }

    public function aprobar(): static
    {
        return app(\App\Domain\Pagos\PagoService::class)->aprobarPago($this);
    }

    public function rechazar(): static
    {
        return app(\App\Domain\Pagos\PagoService::class)->rechazarPago($this);
    }

    public function grupo()
        {
            return $this->hasOneThrough(Grupo::class, Prestamo::class, 'id', 'id', 'cuota_grupal_id', 'grupo_id');
        }



    protected static function boot()
    {
        parent::boot();

        static::creating(function ($pago) {
            if ($pago->cuota_grupal_id) {
                $prestamo = $pago->cuotaGrupal?->prestamo;
                if (!$prestamo || !in_array($prestamo->estado, Prestamo::ESTADOS_ACTIVOS)) {
                    throw new \Exception('No se pueden registrar pagos para préstamos que no estén en estado Activo, Al Día o En Mora.');
                }
            }
        });
    }

}
