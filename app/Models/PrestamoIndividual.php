<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PrestamoIndividual extends Model
{
    use HasFactory;

    // Tabla asociada
    protected $table = 'prestamo_individual';

    // Atributos
    protected $fillable = [
        'prestamo_id',
        'cliente_id',
        'monto_prestado_individual',
        'monto_cuota_prestamo_individual',
        'monto_devolver_individual',
        'seguro',
        'interes',
        'estado',
    ];

    protected $casts = [
        'monto_prestado_individual' => 'decimal:2',
        'monto_cuota_prestamo_individual' => 'decimal:2',
        'monto_devolver_individual' => 'decimal:2',
        'seguro' => 'decimal:2',
        'interes' => 'decimal:2',  // Cambiado a decimal porque ahora guardamos el monto, no el porcentaje
    ];

    /**
     * Relación: un préstamo individual pertenece a un préstamo grupal
     */
    public function prestamo()
    {
        return $this->belongsTo(Prestamo::class);
    }

    /**
     * Relación: un préstamo individual pertenece a un cliente
     */
    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }

    // Opcional: método para saber si está finalizado
    public function estaFinalizado()
    {
        return $this->estado === 'Finalizado';
    }

    /**
     * Boot method para eventos del modelo
     */
    protected static function booted()
    {
        // Evento que se ejecuta después de guardar un préstamo individual
        static::saved(function ($prestamoIndividual) {
            $prestamoIndividual->sincronizarTotalesPrestamo();
        });

        // Evento que se ejecuta después de eliminar un préstamo individual
        static::deleted(function ($prestamoIndividual) {
            $prestamoIndividual->sincronizarTotalesPrestamo();
        });
    }

    /**
     * Sincroniza los totales del préstamo principal cuando se modifica un préstamo individual
     */
    public function sincronizarTotalesPrestamo()
    {
        if ($this->prestamo) {
            // Solo sincronizar si el préstamo está en estado Pendiente y NO es un retanqueo
            if ($this->prestamo->estado === 'Pendiente' && !$this->prestamo->es_retanqueo) {
                $this->prestamo->sincronizarMontosTotal();
                
                \Illuminate\Support\Facades\Log::info('Sincronización automática de totales ejecutada', [
                    'prestamo_id' => $this->prestamo->id,
                    'prestamo_individual_id' => $this->id,
                    'nuevo_monto_total' => $this->prestamo->fresh()->monto_prestado_total,
                    'nuevo_monto_devolver' => $this->prestamo->fresh()->monto_devolver,
                ]);
            }
        }
    }
}
