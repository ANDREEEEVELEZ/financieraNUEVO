<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\Grupo;
use App\Models\Prestamo;
use App\Models\SeparacionCliente;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Servicio para separar clientes morosos de un préstamo grupal.
 *
 * Cuando un integrante entra en mora y no puede regularizarse,
 * JC o JO pueden separarlo del grupo. Este servicio:
 *   1. Calcula la deuda individual (capital + mora)
 *   2. Aplica penalización al grupo
 *   3. Registra la separación
 *   4. Remueve al cliente del grupo
 *   5. Crea registro de auditoría
 *
 * Todo opera en DB::transaction() para atomicidad.
 */
class SeparacionClienteService
{
    /**
     * Separa un cliente moroso de un préstamo grupal.
     *
     * @param Prestamo $prestamo   Préstamo grupal
     * @param int      $clienteId  ID del cliente a separar
     * @param string   $motivo     Justificación obligatoria
     * @param float    $penalizacion Monto de penalización al grupo (0 = sin penalización)
     * @return SeparacionCliente El registro de separación creado
     */
    public function separar(
        Prestamo $prestamo,
        int $clienteId,
        string $motivo,
        float $penalizacion = 0
    ): SeparacionCliente {
        // Validaciones
        if (!$prestamo->grupo) {
            throw new \Exception('Solo se pueden separar clientes de préstamos grupales.');
        }

        $cliente = Cliente::findOrFail($clienteId);

        // Verificar que el cliente pertenece al grupo
        $perteneceAlGrupo = $prestamo->grupo->clientes()
            ->where('clientes.id', $clienteId)
            ->exists();

        if (!$perteneceAlGrupo) {
            throw new \Exception('El cliente no pertenece al grupo de este préstamo.');
        }

        if (empty(trim($motivo))) {
            throw new \Exception('El motivo es obligatorio para separar un cliente.');
        }

        return DB::transaction(function () use ($prestamo, $cliente, $motivo, $penalizacion) {
            // 1. Calcular deuda individual del cliente
            $deudaCapital = $this->calcularDeudaCapital($prestamo, $cliente->id);
            $deudaMora = $this->calcularDeudaMora($prestamo, $cliente->id);

            // 2. Crear registro de separación
            $separacion = SeparacionCliente::create([
                'prestamo_origen_id' => $prestamo->id,
                'grupo_origen_id' => $prestamo->grupo_id,
                'cliente_id' => $cliente->id,
                'ejecutado_por' => auth()->id(),
                'deuda_capital' => $deudaCapital,
                'deuda_mora' => $deudaMora,
                'penalizacion_grupo' => $penalizacion,
                'motivo' => $motivo,
                'estado' => 'ejecutada',
            ]);

            // 3. Remover cliente del grupo
            $prestamo->grupo->removerCliente($cliente->id);

            // 4. Auditoría
            AuditService::registrar(
                'separar_cliente',
                $separacion,
                [
                    'grupo_id' => $prestamo->grupo_id,
                    'cliente_id' => $cliente->id,
                    'integrantes' => $prestamo->grupo->clientes()->count(),
                ],
                [
                    'deuda_capital' => $deudaCapital,
                    'deuda_mora' => $deudaMora,
                    'penalizacion_grupo' => $penalizacion,
                    'cliente_removido' => true,
                ],
                $motivo
            );

            Log::info('Cliente separado del grupo exitosamente', [
                'prestamo_id' => $prestamo->id,
                'cliente_id' => $cliente->id,
                'deuda_total' => $deudaCapital + $deudaMora,
            ]);

            return $separacion;
        });
    }

    /**
     * Calcula la deuda de capital pendiente del cliente en el préstamo.
     */
    private function calcularDeudaCapital(Prestamo $prestamo, int $clienteId): float
    {
        return $prestamo->prestamoIndividual()
            ->where('cliente_id', $clienteId)
            ->sum('saldo_capital') ?? 0;
    }

    /**
     * Calcula la mora acumulada del cliente.
     */
    private function calcularDeudaMora(Prestamo $prestamo, int $clienteId): float
    {
        $cuotasIndividuales = $prestamo->cuotasIndividuales()
            ->where('cliente_id', $clienteId)
            ->get();

        $totalMora = 0;
        foreach ($cuotasIndividuales as $cuota) {
            $totalMora += $cuota->moraCalculada();
        }

        return $totalMora;
    }
}
