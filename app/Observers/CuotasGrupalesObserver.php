<?php

namespace App\Observers;

use App\Models\CuotasGrupales;
use App\Models\Prestamo;
use App\Models\PrestamoIndividual;
use Illuminate\Support\Facades\Log;

class CuotasGrupalesObserver
{
    public function updated(CuotasGrupales $cuota)
    {
        // Log para debug
        Log::info('CuotasGrupalesObserver: Cuota actualizada', [
            'cuota_id' => $cuota->id,
            'estado_pago' => $cuota->estado_pago,
            'prestamo_id' => $cuota->prestamo_id,
        ]);
        
        // Siempre que se actualiza una cuota, verificar si todas están pagadas
        $prestamo = $cuota->prestamo;
        if ($prestamo) {
            $totalCuotas = $prestamo->cuotasGrupales()->count();
            $cuotasPagadas = $prestamo->cuotasGrupales()->where('estado_pago', 'pagado')->count();
            $cuotasNoPagadas = $prestamo->cuotasGrupales()->where('estado_pago', '!=', 'pagado')->count();
            
            Log::info('CuotasGrupalesObserver: Verificando estado del préstamo', [
                'prestamo_id' => $prestamo->id,
                'estado_actual' => $prestamo->estado,
                'total_cuotas' => $totalCuotas,
                'cuotas_pagadas' => $cuotasPagadas,
                'cuotas_no_pagadas' => $cuotasNoPagadas,
            ]);
            
            $todasPagadas = $cuotasNoPagadas === 0;
            
            if ($todasPagadas && !in_array($prestamo->estado, ['Finalizado'])) {
                Log::info('CuotasGrupalesObserver: Verificando si se puede finalizar préstamo', [
                    'prestamo_id' => $prestamo->id,
                ]);
                
                // Verificar si hay integrantes que no retanquearon con deuda pendiente
                if ($prestamo->tieneIntegrantesNoRetanqueadosConDeudaPendiente()) {
                    Log::info('CuotasGrupalesObserver: No se puede finalizar - hay integrantes que no retanquearon con deuda pendiente', [
                        'prestamo_id' => $prestamo->id,
                    ]);
                    return; // No finalizar el préstamo
                }
                
                Log::info('CuotasGrupalesObserver: Cambiando estado a Finalizado', [
                    'prestamo_id' => $prestamo->id,
                ]);
                
                // Cambiar estado del préstamo y de los préstamos individuales a 'Finalizado'
                $prestamo->estado = 'Finalizado';
                $prestamo->save();
                
                PrestamoIndividual::where('prestamo_id', $prestamo->id)->update(['estado' => 'Finalizado']);
                
                // Si el préstamo estaba en estado Parcialmente_Retanqueado, 
                // mover a ex-integrantes a quienes no retanquearon
                if ($prestamo->estado === 'Parcialmente_Retanqueado') {
                    $prestamo->moverIntegrantesNoRetanqueadosAExIntegrantes();
                }
                
                // Actualizar ciclo de todos los clientes del préstamo
                $this->actualizarCiclosClientes($prestamo);
                
                Log::info('CuotasGrupalesObserver: Estado cambiado exitosamente', [
                    'prestamo_id' => $prestamo->id,
                    'nuevo_estado' => $prestamo->estado,
                ]);
            }
        }
    }

    /**
     * Actualiza el ciclo de todos los clientes cuando un préstamo se finaliza
     */
    private function actualizarCiclosClientes($prestamo): void
    {
        if (!$prestamo || !$prestamo->grupo) return;

        // Obtener todos los clientes del grupo con sus préstamos individuales
        $clientesDelGrupo = $prestamo->grupo->clientes;

        foreach ($clientesDelGrupo as $cliente) {
            // Contar préstamos completados del cliente (tanto Completado como Finalizado)
            $prestamosCompletados = PrestamoIndividual::where('cliente_id', $cliente->id)
                ->whereIn('estado', ['Completado', 'Finalizado'])
                ->count();

            // Verificar si puede subir de ciclo usando el helper
            if (\App\Helpers\CicloHelper::puedeSubirCiclo($cliente->ciclo, $prestamosCompletados)) {
                $cicloAnterior = $cliente->ciclo;
                $nuevoCiclo = \App\Helpers\CicloHelper::calcularCicloPorPrestamos($prestamosCompletados);
                
                $cliente->updateQuietly([
                    'ciclo' => $nuevoCiclo
                ]);

                // Log del cambio para auditoría
                \Illuminate\Support\Facades\Log::info('Cliente subió de ciclo automáticamente', [
                    'cliente_id' => $cliente->id,
                    'nombre_cliente' => $cliente->persona->nombre ?? 'N/A',
                    'ciclo_anterior' => $cicloAnterior,
                    'ciclo_nuevo' => $nuevoCiclo,
                    'prestamos_completados' => $prestamosCompletados,
                    'evento' => 'prestamo_finalizado_cuotas_pagadas',
                    'prestamo_id' => $prestamo->id
                ]);
            }
        }
    }
}
