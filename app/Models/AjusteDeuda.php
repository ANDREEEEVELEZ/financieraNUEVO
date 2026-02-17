<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AjusteDeuda extends Model
{
    use HasFactory;

    protected $table = 'ajuste_deuda';

    protected $fillable = [
        'cuota_id',
        'usuario_id',
        'tipo',
        'monto_ajuste',
        'justificacion',
        'metadata',
    ];

    protected $casts = [
        'monto_ajuste' => 'decimal:2',
        'metadata' => 'array',
    ];

    // Relaciones
    public function cuota()
    {
        return $this->belongsTo(CuotaIndividual::class, 'cuota_id');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    // Scopes
    public function scopeCondonaciones($query)
    {
        return $query->where('tipo', 'condonacion_mora');
    }

    public function scopeDescuentos($query)
    {
        return $query->whereIn('tipo', ['descuento_capital', 'descuento_interes']);
    }

    // Helpers
    public function esCondonacion(): bool
    {
        return $this->tipo === 'condonacion_mora';
    }

    public function getNombreTipoAttribute(): string
    {
        return match ($this->tipo) {
            'condonacion_mora' => 'Condonación de Mora',
            'descuento_capital' => 'Descuento de Capital',
            'descuento_interes' => 'Descuento de Interés',
            'refinanciamiento' => 'Refinanciamiento',
            'castigo' => 'Castigo de Deuda',
            default => $this->tipo,
        };
    }
}
