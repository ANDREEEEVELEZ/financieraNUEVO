<?php

namespace App\Domain\Pagos;

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

        $ajuste = DB::transaction(function () use ($objetivo, $montoCondonado, $tipoPenalizacion, $montoPenalizacion, $motivo) {
            $cuota = $objetivo instanceof CuotaIndividual
                ? $objetivo
                : $this->obtenerCuotaDesdeMora($objetivo);

            $ajuste = AjusteDeuda::create([
                'cuota_id' => $cuota->id,
                'tipo' => 'condonacion_mora',
                'monto_ajuste' => $montoCondonado,
                'justificacion' => $motivo,
                'usuario_id' => auth()->id(),
            ]);

            if ($objetivo instanceof Mora) {
                $moraRestante = max(0, $objetivo->monto_mora_calculado - $montoCondonado);
                $objetivo->estado_mora = $moraRestante <= 0 ? 'condonada' : 'parcialmente_pagada';
                $objetivo->save();
            }

            return $ajuste;
        });

        // Dispatch outside transaction — listener failures must not roll back committed data
        MoraCondonada::dispatch($objetivo, $montoCondonado);

        Log::info('Mora condonada exitosamente', [
            'objetivo_tipo' => get_class($objetivo),
            'objetivo_id'   => $objetivo->id,
            'monto'         => $montoCondonado,
            'usuario_id'    => auth()->id(),
        ]);

        return $ajuste;
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

        // La Mora se relaciona con CuotaGrupal, que es compartida por TODOS los
        // integrantes del grupo para ese numero_cuota — puede haber más de una
        // CuotaIndividual candidata (una por integrante). Tomar ->first() sin
        // más contexto arriesgaba condonar al integrante equivocado. Si hay más
        // de un candidato, es ambiguo: el llamador debe resolver e invocar
        // condonar() con la CuotaIndividual específica en vez de una Mora.
        $cuotasIndividuales = CuotaIndividual::where('prestamo_id', $cuotaGrupal->prestamo_id)
            ->where('numero_cuota', $cuotaGrupal->numero_cuota ?? 0)
            ->get();

        if ($cuotasIndividuales->isEmpty()) {
            throw new \Exception('No se encontró una cuota individual para registrar la condonación.');
        }

        if ($cuotasIndividuales->count() > 1) {
            throw new \Exception(
                'La mora corresponde a más de una cuota individual (grupo con múltiples integrantes); '
                . 'debe indicarse explícitamente la CuotaIndividual del integrante a condonar en vez de la Mora.'
            );
        }

        return $cuotasIndividuales->first();
    }
}
