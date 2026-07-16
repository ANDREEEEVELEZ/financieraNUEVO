<?php

namespace App\Domain\Prestamos;

use App\Contracts\SaldoCuotaServiceInterface;
use App\Events\Domain\ReagrupacionEjecutada;
use App\Models\Grupo;
use App\Models\Prestamo;
use App\Models\Reagrupacion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Servicio para reagrupación parcial de préstamos grupales.
 *
 * Permite dividir un grupo cuando algunos integrantes quieren
 * renovar/retanquear y otros están en mora.
 *
 * Tipos:
 *   - 'retanqueo': Nuevo grupo con descuento de última cuota del préstamo anterior
 *   - 'renovacion': Nuevo grupo sin descuentos
 *
 * Los integrantes morosos permanecen en el grupo original con su deuda.
 */
class ReagrupacionParcialService
{
    /**
     * Ejecuta la reagrupación parcial.
     *
     * @param Prestamo $prestamo             Préstamo del grupo a dividir
     * @param array    $clientesTrasladados  IDs de clientes que van al nuevo grupo
     * @param string   $tipo                 'retanqueo' o 'renovacion'
     * @param string   $observaciones        Notas adicionales
     * @return Reagrupacion El registro de reagrupación creado
     */
    public function reagrupar(
        Prestamo $prestamo,
        array $clientesTrasladados,
        string $tipo = 'retanqueo',
        string $observaciones = ''
    ): Reagrupacion {
        // Validaciones
        if (!$prestamo->grupo) {
            throw new \Exception('Solo se pueden reagrupar préstamos grupales.');
        }

        if (empty($clientesTrasladados)) {
            throw new \Exception('Debe seleccionar al menos un cliente para trasladar.');
        }

        if (!in_array($tipo, ['retanqueo', 'renovacion'])) {
            throw new \Exception("Tipo de reagrupación inválido: {$tipo}. Use 'retanqueo' o 'renovacion'.");
        }

        $grupoOrigen = $prestamo->grupo;

        // Verificar que los clientes seleccionados pertenecen al grupo
        $clientesDelGrupo = $grupoOrigen->clientes()->pluck('clientes.id')->toArray();
        $clientesInvalidos = array_diff($clientesTrasladados, $clientesDelGrupo);

        if (!empty($clientesInvalidos)) {
            throw new \Exception('Algunos clientes seleccionados no pertenecen al grupo.');
        }

        // Los clientes retenidos son los que quedan en el grupo original
        $clientesRetenidos = array_diff($clientesDelGrupo, $clientesTrasladados);

        if (empty($clientesRetenidos)) {
            throw new \Exception('No se puede trasladar a todos los integrantes. Al menos uno debe permanecer en el grupo original.');
        }

        $reagrupacion = DB::transaction(function () use ($prestamo, $grupoOrigen, $clientesTrasladados, $clientesRetenidos, $tipo, $observaciones) {
            // 1. Crear nuevo grupo con los mismos datos base
            $grupoNuevo = Grupo::create([
                'nombre_grupo' => $grupoOrigen->nombre_grupo . ' (Reagrupado)',
                'asesor_id' => $grupoOrigen->asesor_id,
                'estado_grupo' => 'Activo',
                'numero_integrantes' => count($clientesTrasladados),
            ]);

            // 2. Trasladar clientes al nuevo grupo
            foreach ($clientesTrasladados as $clienteId) {
                $grupoOrigen->transferirClienteAGrupo($clienteId, $grupoNuevo->id);
            }

            // 3. Calcular descuento si es retanqueo
            $montoDescuento = 0;
            if ($tipo === 'retanqueo') {
                $montoDescuento = $this->calcularDescuentoRetanqueo($prestamo, $clientesTrasladados);
            }

            // 4. Crear registro de reagrupación
            $reagrupacion = Reagrupacion::create([
                'grupo_origen_id' => $grupoOrigen->id,
                'grupo_nuevo_id' => $grupoNuevo->id,
                'prestamo_origen_id' => $prestamo->id,
                'ejecutado_por' => auth()->id(),
                'tipo' => $tipo,
                'monto_descuento' => $montoDescuento,
                'observaciones' => $observaciones,
            ]);

            // Pivot: attach con tipo via array de pares
            $pivotData = [];
            foreach ($clientesTrasladados as $cId) {
                $pivotData[$cId] = ['tipo' => 'trasladado'];
            }
            foreach ($clientesRetenidos as $cId) {
                $pivotData[$cId] = ['tipo' => 'retenido'];
            }
            $reagrupacion->clientes()->attach($pivotData);

            Log::info('Reagrupación parcial ejecutada', [
                'grupo_origen' => $grupoOrigen->id,
                'grupo_nuevo' => $grupoNuevo->id,
                'tipo' => $tipo,
                'trasladados' => count($clientesTrasladados),
            ]);

            return $reagrupacion;
        });

        // 5. Dispatch event OUTSIDE transaction — listener failure must not roll back committed data
        ReagrupacionEjecutada::dispatch($reagrupacion);

        return $reagrupacion;
    }

    /**
     * Calcula el descuento de la última cuota para retanqueo.
     */
    private function calcularDescuentoRetanqueo(Prestamo $prestamo, array $clienteIds): float
    {
        // La última cuota pendiente del préstamo original
        $ultimaCuotaPendiente = $prestamo->cuotasGrupales()
            ->where('estado_pago', '!=', 'pagado')
            ->orderBy('numero_cuota', 'desc')
            ->first();

        if (!$ultimaCuotaPendiente) {
            return 0;
        }

        // Proporción del descuento según los clientes que retanquean
        $totalIntegrantes = $prestamo->grupo->clientes()->count();
        $integrantesQueRetanquean = count($clienteIds);

        if ($totalIntegrantes <= 0) {
            return 0;
        }

        $proporcion = $integrantesQueRetanquean / $totalIntegrantes;
        $saldoLedger = (float) app(SaldoCuotaServiceInterface::class)->saldoTotal($ultimaCuotaPendiente);

        return round($saldoLedger * $proporcion, 2);
    }
}
