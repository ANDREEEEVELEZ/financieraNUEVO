<?php

namespace App\Domain\Pagos;

use App\Contracts\AuditServiceInterface;
use App\Events\Domain\MoraCondonada;
use App\Models\AjusteDeuda;
use App\Models\CuotaIndividual;
use App\Models\Mora;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Servicio de condonación de mora.
 *
 * Ejecuta la condonación de mora con penalización al grupo o cliente,
 * registrando todo en una transacción atómica con auditoría.
 *
 * Solo puede ser ejecutado por JC o JO.
 */
class CondonacionMoraService
{
    /**
     * Condona un monto de mora con penalización.
     *
     * @param Mora|CuotaIndividual $objetivo          Mora o CuotaIndividual a condonar
     * @param float                $montoCondonado     Monto de mora a condonar
     * @param string               $tipoPenalizacion   'grupo' o 'cliente'
     * @param float                $montoPenalizacion  Monto de la penalización aplicada
     * @param string               $motivo             Justificación obligatoria
     * @return AjusteDeuda Registro del ajuste creado
     */
    public function condonar(
        Mora|CuotaIndividual $objetivo,
        float $montoCondonado,
        string $tipoPenalizacion,
        float $montoPenalizacion,
        string $motivo
    ): AjusteDeuda {
        if (empty(trim($motivo))) {
            throw new \Exception('El motivo es obligatorio para condonar mora.');
        }

        return DB::transaction(function () use ($objetivo, $montoCondonado, $tipoPenalizacion, $montoPenalizacion, $motivo) {
            // Determinar la cuota según el tipo de objetivo
            $cuota = $objetivo instanceof CuotaIndividual
                ? $objetivo
                : $this->obtenerCuotaDesdeMora($objetivo);

            $datosAnteriores = [
                'estado_mora' => $objetivo instanceof Mora ? $objetivo->estado_mora : null,
                'monto_mora' => $objetivo instanceof Mora ? $objetivo->monto_mora_calculado : $cuota->moraCalculada(),
            ];

            // 1. Crear el registro de ajuste de deuda
            $ajuste = AjusteDeuda::create([
                'cuota_id' => $cuota->id,
                'tipo' => 'condonacion_mora',
                'monto_ajuste' => $montoCondonado,
                'justificacion' => $motivo,
                'usuario_id' => auth()->id(),
            ]);

            // 2. Actualizar estado de mora si aplica
            if ($objetivo instanceof Mora) {
                $moraRestante = max(0, $objetivo->monto_mora_calculado - $montoCondonado);
                $objetivo->estado_mora = $moraRestante <= 0 ? 'condonada' : 'parcialmente_pagada';
                $objetivo->save();
            }

            // 3. Registrar auditoría
            app(AuditServiceInterface::class)->registrar(
                'condonar_mora',
                $objetivo,
                $datosAnteriores,
                [
                    'monto_condonado' => $montoCondonado,
                    'tipo_penalizacion' => $tipoPenalizacion,
                    'monto_penalizacion' => $montoPenalizacion,
                    'estado_mora' => $objetivo instanceof Mora ? $objetivo->estado_mora : 'condonada',
                ],
                $motivo
            );

            MoraCondonada::dispatch($objetivo, $montoCondonado);

            Log::info('Mora condonada exitosamente', [
                'objetivo_tipo' => get_class($objetivo),
                'objetivo_id' => $objetivo->id,
                'monto' => $montoCondonado,
                'penalizacion' => $montoPenalizacion,
                'usuario_id' => auth()->id(),
            ]);

            return $ajuste;
        });
    }

    /**
     * Obtiene la CuotaIndividual asociada a una Mora (via CuotaGrupal).
     */
    private function obtenerCuotaDesdeMora(Mora $mora): CuotaIndividual
    {
        // La Mora se relaciona con CuotaGrupal, que tiene el préstamo
        // Para condonación se necesita la CuotaIndividual correspondiente
        $cuotaGrupal = $mora->cuotaGrupal;

        if (!$cuotaGrupal || !$cuotaGrupal->prestamo) {
            throw new \Exception('No se encontró la cuota del préstamo asociada a la mora.');
        }

        // Buscar la primera cuota individual pendiente del préstamo
        $cuotaIndividual = CuotaIndividual::where('prestamo_id', $cuotaGrupal->prestamo_id)
            ->where('numero_cuota', $cuotaGrupal->numero_cuota ?? 0)
            ->first();

        if (!$cuotaIndividual) {
            throw new \Exception('No se encontró una cuota individual para registrar la condonación.');
        }

        return $cuotaIndividual;
    }
}
