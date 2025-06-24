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
        'interes' => 'integer',  // Porcentaje como entero (17)
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
}
