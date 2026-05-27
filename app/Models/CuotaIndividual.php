<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class CuotaIndividual extends Model
{
    use HasFactory;

    protected $table = 'cuota_individual';

    protected $fillable = [
        'prestamo_id',
        'cliente_id',
        'numero_cuota',
        'monto_capital_original',
        'monto_interes_original',
        'monto_seguro',
        'saldo_capital',
        'saldo_interes',
        'fecha_vencimiento',
        'estado',
    ];

    protected $casts = [
        'monto_capital_original' => 'decimal:2',
        'monto_interes_original' => 'decimal:2',
        'monto_seguro' => 'decimal:2',
        'saldo_capital' => 'decimal:2',
        'saldo_interes' => 'decimal:2',
        'fecha_vencimiento' => 'date',
    ];

    // Relaciones
    public function prestamo()
    {
        return $this->belongsTo(Prestamo::class);
    }

    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }

    public function aplicacionesPago()
    {
        return $this->hasMany(AplicacionPago::class, 'cuota_id');
    }

    public function ajustes()
    {
        return $this->hasMany(AjusteDeuda::class, 'cuota_id');
    }

    // Métodos de Negocio

    /**
     * Calcula la mora dinámicamente basada en días de atraso.
     * La mora NO se guarda en BD, se calcula siempre.
     */
    public function moraCalculada(): float
    {
        if ($this->estado === 'pagada' || $this->fecha_vencimiento >= now()) {
            return 0;
        }

        $diasAtraso = now()->diffInDays($this->fecha_vencimiento);
        $tasaMoraDiaria = ($this->prestamo->producto->tasa_mora ?? 0.05) / 30;
        $moraBase = (float) $this->saldo_capital * $tasaMoraDiaria * $diasAtraso;

        // Restar condonaciones de mora
        $condonaciones = $this->ajustes()
            ->where('tipo', 'condonacion_mora')
            ->sum('monto_ajuste');

        return max(0, $moraBase - $condonaciones);
    }

    /**
     * Calcula el saldo total pendiente (capital + interés + mora).
     */
    public function saldoTotal(): float
    {
        return (float) $this->saldo_capital
            + (float) $this->saldo_interes
            + $this->moraCalculada();
    }

    /**
     * Registra una condonación de mora.
     */
    public function condonarMora(float $monto, string $justificacion): AjusteDeuda
    {
        return AjusteDeuda::create([
            'cuota_id' => $this->id,
            'tipo' => 'condonacion_mora',
            'monto_ajuste' => $monto,
            'justificacion' => $justificacion,
            'usuario_id' => auth()->id(),
        ]);
    }

    /**
     * Verifica si la cuota está vencida.
     */
    public function estaVencida(): bool
    {
        return $this->fecha_vencimiento < now() && $this->estado !== 'pagada';
    }

    /**
     * Días de atraso.
     */
    public function diasAtraso(): int
    {
        if (!$this->estaVencida()) {
            return 0;
        }
        return now()->diffInDays($this->fecha_vencimiento);
    }

    // Scopes
    public function scopePendientes($query)
    {
        return $query->where('estado', 'pendiente');
    }

    public function scopeVencidas($query)
    {
        return $query->where('fecha_vencimiento', '<', now())
            ->where('estado', '!=', 'pagada');
    }

    public function scopeDelCliente($query, $clienteId)
    {
        return $query->where('cliente_id', $clienteId);
    }

    // ── Scopes para el panel asesor ─────────────────────────────────────

    /**
     * Cuotas con fecha de vencimiento hoy y estado pendiente.
     */
    public function scopeDueToday(Builder $query): Builder
    {
        return $query->whereDate('fecha_vencimiento', today())
                     ->where('estado', 'pendiente');
    }

    /**
     * Cuotas en mora (estado vencida).
     */
    public function scopeEnMora(Builder $query): Builder
    {
        return $query->where('estado', 'vencida');
    }

    /**
     * Cuotas belonging to clients of the given asesor.
     *
     * Corrected: prestamos.cliente_id does not exist in the schema.
     * The correct path is cuota_individual → prestamos → grupos (via grupo_id) → asesor_id.
     *
     * @param  Builder  $query
     * @param  \App\Models\Asesor  $asesor
     */
    public function scopeOfAsesor(Builder $query, \App\Models\Asesor $asesor): Builder
    {
        return $query->whereHas('prestamo.grupo', fn (Builder $q) =>
            $q->where('asesor_id', $asesor->id)
        );
    }
}
