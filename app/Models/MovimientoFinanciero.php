<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MovimientoFinanciero extends Model
{
    use HasFactory;

    protected $table = 'movimiento_financiero';

    protected $fillable = [
        'tipo',
        'concepto',
        'monto',
        'fecha',
        'referencia_tipo',
        'referencia_id',
        'descripcion',
        'usuario_id',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
        'fecha' => 'date',
    ];

    // Relaciones
    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    /**
     * Relación polimórfica con la fuente del movimiento.
     */
    public function referencia()
    {
        if (!$this->referencia_tipo || !$this->referencia_id) {
            return null;
        }
        return $this->referencia_tipo::find($this->referencia_id);
    }

    // Scopes
    public function scopeIngresos($query)
    {
        return $query->where('tipo', 'ingreso');
    }

    public function scopeEgresos($query)
    {
        return $query->where('tipo', 'egreso');
    }

    public function scopeDesembolsos($query)
    {
        return $query->where('concepto', 'desembolso');
    }

    public function scopePagosCuotas($query)
    {
        return $query->where('concepto', 'pago_cuota');
    }

    public function scopeEnRango($query, $desde, $hasta)
    {
        return $query->whereBetween('fecha', [$desde, $hasta]);
    }

    // Helpers
    public function esIngreso(): bool
    {
        return $this->tipo === 'ingreso';
    }

    public function esEgreso(): bool
    {
        return $this->tipo === 'egreso';
    }

    /**
     * Crea un movimiento de desembolso para un préstamo.
     */
    public static function registrarDesembolso(Prestamo $prestamo, ?int $usuarioId = null): self
    {
        return self::create([
            'tipo' => 'egreso',
            'concepto' => 'desembolso',
            'monto' => $prestamo->monto_prestado_total,
            'fecha' => $prestamo->fecha_desembolso ?? now(),
            'referencia_tipo' => Prestamo::class,
            'referencia_id' => $prestamo->id,
            'descripcion' => 'Desembolso préstamo #' . $prestamo->id,
            'usuario_id' => $usuarioId ?? auth()->id(),
        ]);
    }

    /**
     * Crea un movimiento de ingreso por pago de cuota.
     */
    public static function registrarPagoCuota(Pago $pago, ?int $usuarioId = null): self
    {
        return self::create([
            'tipo' => 'ingreso',
            'concepto' => 'pago_cuota',
            'monto' => $pago->monto_pagado,
            'fecha' => $pago->fecha_pago,
            'referencia_tipo' => Pago::class,
            'referencia_id' => $pago->id,
            'descripcion' => 'Pago de cuota #' . $pago->id,
            'usuario_id' => $usuarioId ?? auth()->id(),
        ]);
    }
}
