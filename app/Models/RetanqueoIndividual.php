<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class RetanqueoIndividual extends Model
{
    use HasFactory;

    protected $table = 'retanqueos_individual';

    protected $guarded = ['id'];

    protected $casts = [
        'aporte_cobertura'    => 'decimal:2',
        'monto_solicitado'    => 'decimal:2',
        'monto_desembolsar'   => 'decimal:2',
        'monto_cuota'         => 'decimal:2',
        'aceptacion_cliente'  => 'boolean',
    ];

    public function retanqueo()
    {
        return $this->belongsTo(Retanqueo::class, 'retanqueo_id');
    }

    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    // Método para calcular el aporte de cobertura basado en deuda pendiente
    public function calcularAporteCobertura()
    {
        if ($this->participacion_tipo !== 'retanquea') {
            return 0;
        }

        $retanqueo = $this->retanqueo;
        $prestamoAntiguo = $retanqueo->prestamoAntiguo;
        
        // Obtener todas las cuotas grupales del préstamo antiguo
        $cuotasGrupales = $prestamoAntiguo->cuotasGrupales()
            ->where('estado_pago', '!=', 'pagado')
            ->get();

        $totalDeudaGrupal = $cuotasGrupales->sum('saldo_pendiente');
        
        if ($totalDeudaGrupal <= 0) {
            return 0;
        }

        // Obtener cantidad de integrantes que retanquean
        $integrantesQueRetanquean = $retanqueo->retanqueosIndividuales()
            ->where('participacion_tipo', 'retanquea')
            ->count();

        if ($integrantesQueRetanquean <= 0) {
            return 0;
        }

        // Dividir la deuda proporcionalmente
        return round($totalDeudaGrupal / $integrantesQueRetanquean, 2);
    }

    // Método para calcular monto a desembolsar
    public function calcularMontoDesembolsar()
    {
        return max(0, $this->monto_solicitado - $this->aporte_cobertura);
    }

    // Método para verificar si el cliente puede retanquear
    public function puedeRetanquear()
    {
        $cliente = $this->cliente;
        if (!$cliente) {
            return false;
        }

        // Verificar que el cliente esté en el grupo del préstamo
        $grupo = $this->retanqueo->prestamoAntiguo->grupo;
        $perteneceAlGrupo = $grupo->clientes()->where('clientes.id', $cliente->id)->exists();

        return $perteneceAlGrupo;
    }
}
