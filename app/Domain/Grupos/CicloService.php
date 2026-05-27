<?php

namespace App\Domain\Grupos;

use App\Helpers\CicloHelper;
use App\Models\Cliente;
use App\Models\Prestamo;
use App\Models\PrestamoIndividual;
use Illuminate\Support\Facades\Log;

final class CicloService
{
    /**
     * Promotes a client's cycle if they qualify, based on completed individual loans.
     * Safe to call multiple times — CicloHelper::puedeSubirCiclo is idempotent.
     */
    public function actualizarCicloCliente(Cliente $cliente): void
    {
        $completados = PrestamoIndividual::where('cliente_id', $cliente->id)
            ->whereIn('estado', ['Completado', 'Finalizado'])
            ->count();

        if (!CicloHelper::puedeSubirCiclo($cliente->ciclo, $completados)) {
            return;
        }

        $anterior  = $cliente->ciclo;
        $nuevo     = CicloHelper::calcularCicloPorPrestamos($completados);

        $cliente->updateQuietly(['ciclo' => $nuevo]);

        Log::info('Ciclo actualizado', [
            'cliente_id'           => $cliente->id,
            'ciclo_anterior'       => $anterior,
            'ciclo_nuevo'          => $nuevo,
            'prestamos_completados' => $completados,
        ]);
    }

    /**
     * Promotes the cycle for every active client in a loan group.
     * Called when the group loan is finalised.
     */
    public function actualizarCiclosDeGrupo(Prestamo $prestamo): void
    {
        if (!$prestamo->grupo) {
            return;
        }

        foreach ($prestamo->grupo->clientes as $cliente) {
            $this->actualizarCicloCliente($cliente);
        }
    }
}
