<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro de separación de un cliente moroso de un grupo.
 *
 * Se crea cuando JC o JO separa a un integrante en mora
 * de un préstamo grupal. Registra la deuda, penalización
 * y trazabilidad completa de la operación.
 */
class SeparacionCliente extends Model
{
    protected $table = 'separaciones_clientes';

    protected $fillable = [
        'prestamo_origen_id',
        'prestamo_nuevo_id',
        'grupo_origen_id',
        'cliente_id',
        'ejecutado_por',
        'deuda_capital',
        'deuda_interes',
        'deuda_mora',
        'penalizacion_grupo',
        'motivo',
        'estado',
    ];

    protected $casts = [
        'deuda_capital' => 'decimal:2',
        'deuda_interes' => 'decimal:2',
        'deuda_mora' => 'decimal:2',
        'penalizacion_grupo' => 'decimal:2',
    ];

    // ─── Relaciones ─────────────────────────────────────────────────

    public function prestamoOrigen(): BelongsTo
    {
        return $this->belongsTo(Prestamo::class, 'prestamo_origen_id');
    }

    public function prestamoNuevo(): BelongsTo
    {
        return $this->belongsTo(Prestamo::class, 'prestamo_nuevo_id');
    }

    public function grupoOrigen(): BelongsTo
    {
        return $this->belongsTo(Grupo::class, 'grupo_origen_id');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function ejecutor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ejecutado_por');
    }

    // ─── Scopes ─────────────────────────────────────────────────────

    public function scopeEjecutadas($query)
    {
        return $query->where('estado', 'ejecutada');
    }

    public function scopeRevertidas($query)
    {
        return $query->where('estado', 'revertida');
    }
}
