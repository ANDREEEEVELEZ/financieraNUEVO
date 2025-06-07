<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ingreso extends Model
{
    use HasFactory;

    protected $fillable = [
        'tipo_ingreso',
        'pago_id',
        'grupo_id',
        'fecha_hora',
        'descripcion',
        'monto',
    ];

    protected $casts = [
        'fecha_hora' => 'datetime',
        'monto' => 'decimal:2',
    ];

    // Relación con el modelo Pago
    public function pago(): BelongsTo
    {
        return $this->belongsTo(Pago::class);
    }

    // Relación con el modelo Grupo
    public function grupo(): BelongsTo
    {
        return $this->belongsTo(Grupo::class);
    }

    // Accessor para obtener el código de operación de la relación con pago
    public function getCodigoOperacionAttribute(): ?string
    {
        // Solo para pagos de cuota de grupo, obtener el código de la relación
        if ($this->tipo_ingreso === 'pago de cuota de grupo' && $this->pago) {
            return $this->pago->codigo_operacion;
        }
        
        return null;
    }

    // Accessor para mostrar el tipo de ingreso formateado
    public function getTipoIngresoFormateadoAttribute(): string
    {
        return match ($this->tipo_ingreso) {
            'transferencia' => 'Transferencia',
            'pago de cuota de grupo' => 'Pago de Cuota de Grupo',
            default => $this->tipo_ingreso,
        };
    }

    // Scope para filtrar por tipo de ingreso
    public function scopeTransferencias($query)
    {
        return $query->where('tipo_ingreso', 'transferencia');
    }

    public function scopePagosCuotas($query)
    {
        return $query->where('tipo_ingreso', 'pago de cuota de grupo');
    }
}