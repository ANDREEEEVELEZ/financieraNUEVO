<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro de reagrupación parcial.
 *
 * Se crea cuando JC o JO divide un grupo porque algunos integrantes
 * quieren renovar/retanquear y otros están en mora.
 * Los integrantes al día forman un nuevo grupo.
 */
class Reagrupacion extends Model
{
    protected $table = 'reagrupaciones';

    protected $fillable = [
        'grupo_origen_id',
        'grupo_nuevo_id',
        'prestamo_origen_id',
        'ejecutado_por',
        'tipo',
        'clientes_trasladados',
        'clientes_retenidos',
        'monto_descuento',
        'observaciones',
    ];

    protected $casts = [
        'clientes_trasladados' => 'array',
        'clientes_retenidos' => 'array',
        'monto_descuento' => 'decimal:2',
    ];

    // ─── Relaciones ─────────────────────────────────────────────────

    public function grupoOrigen(): BelongsTo
    {
        return $this->belongsTo(Grupo::class, 'grupo_origen_id');
    }

    public function grupoNuevo(): BelongsTo
    {
        return $this->belongsTo(Grupo::class, 'grupo_nuevo_id');
    }

    public function prestamoOrigen(): BelongsTo
    {
        return $this->belongsTo(Prestamo::class, 'prestamo_origen_id');
    }

    public function ejecutor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ejecutado_por');
    }

    // ─── Helpers ────────────────────────────────────────────────────

    public function esRetanqueo(): bool
    {
        return $this->tipo === 'retanqueo';
    }

    public function esRenovacion(): bool
    {
        return $this->tipo === 'renovacion';
    }
}
