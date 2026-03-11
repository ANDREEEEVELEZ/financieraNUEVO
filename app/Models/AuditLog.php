<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Modelo para registros de auditoría.
 *
 * Registra acciones críticas del sistema con relación polimórfica
 * hacia cualquier modelo (Pago, Mora, Prestamo, etc.).
 *
 * @property int $id
 * @property int $user_id
 * @property string $accion
 * @property string $auditable_type
 * @property int $auditable_id
 * @property array|null $datos_anteriores
 * @property array|null $datos_nuevos
 * @property string|null $motivo
 * @property string|null $ip
 */
class AuditLog extends Model
{
    protected $fillable = [
        'user_id',
        'accion',
        'auditable_type',
        'auditable_id',
        'datos_anteriores',
        'datos_nuevos',
        'motivo',
        'ip',
    ];

    protected $casts = [
        'datos_anteriores' => 'array',
        'datos_nuevos' => 'array',
    ];

    // ─── Relaciones ─────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    // ─── Scopes ─────────────────────────────────────────────────────

    public function scopePorAccion($query, string $accion)
    {
        return $query->where('accion', $accion);
    }

    public function scopeDelModelo($query, string $modelClass, int $modelId)
    {
        return $query->where('auditable_type', $modelClass)
            ->where('auditable_id', $modelId);
    }

    public function scopeRecientes($query, int $dias = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($dias));
    }
}
