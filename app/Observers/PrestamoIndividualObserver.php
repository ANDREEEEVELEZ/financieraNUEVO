<?php

namespace App\Observers;

use App\Models\Prestamo;
use App\Models\PrestamoIndividual;
use App\Domain\Grupos\CicloService;

class PrestamoIndividualObserver
{
    public function __construct(private readonly CicloService $cicloService) {}

    public function created(PrestamoIndividual $pi): void
    {
        $this->recalcularCamposIndividuales($pi, isCreation: true);
        $this->recalcularTotales($pi->prestamo_id);
    }

    public function updated(PrestamoIndividual $pi): void
    {
        if ($pi->isDirty('estado') && in_array($pi->estado, ['Completado', 'Finalizado'])) {
            $pi->loadMissing('cliente');
            $this->cicloService->actualizarCicloCliente($pi->cliente);
        }

        $this->recalcularCamposIndividuales($pi, isCreation: false);
        $this->recalcularTotales($pi->prestamo_id);
    }

    public function deleted(PrestamoIndividual $pi): void
    {
        $this->recalcularTotales($pi->prestamo_id);
    }

    private function recalcularTotales(?int $prestamoId): void
    {
        if (!$prestamoId) {
            return;
        }

        $prestamo = Prestamo::find($prestamoId);

        if (!$prestamo) {
            return;
        }

        // Do not recalculate on active/finalised loans — amounts are locked
        $bloqueados = array_merge(Prestamo::ESTADOS_ACTIVOS, [
            Prestamo::ESTADO_APROBADO,
            Prestamo::ESTADO_FINALIZADO,
        ]);

        if (in_array($prestamo->estado, $bloqueados)) {
            return;
        }

        $totals = PrestamoIndividual::where('prestamo_id', $prestamoId)
            ->selectRaw('SUM(monto_prestado_individual) as tp, SUM(monto_devolver_individual) as td')
            ->first();

        $totalPrestado = (float) ($totals->tp ?? 0);
        $totalDevolver = (float) ($totals->td ?? 0);

        if ($prestamo->monto_prestado_total != $totalPrestado || $prestamo->monto_devolver != $totalDevolver) {
            $prestamo->updateQuietly([
                'monto_prestado_total' => round($totalPrestado, 2),
                'monto_devolver'       => round($totalDevolver, 2),
            ]);

            $this->actualizarCuotasGrupales($prestamo, $totalDevolver);
        }
    }

    private function actualizarCuotasGrupales(Prestamo $prestamo, float $totalDevolver): void
    {
        if (in_array($prestamo->estado, [Prestamo::ESTADO_FINALIZADO, Prestamo::ESTADO_CANCELADO])) {
            return;
        }

        $cuotas    = $prestamo->cuotasGrupales;
        $numCuotas = $prestamo->cantidad_cuotas;

        if ($cuotas->isEmpty() || $numCuotas < 1) {
            return;
        }

        $montoPorCuota = $totalDevolver / $numCuotas;

        foreach ($cuotas as $cuota) {
            if (abs((float) $cuota->monto_cuota_grupal - $montoPorCuota) > 0.01) {
                $cuota->updateQuietly([
                    'monto_cuota_grupal' => round($montoPorCuota, 2),
                    'saldo_pendiente'    => round($montoPorCuota, 2),
                ]);
            }
        }
    }

    private function recalcularCamposIndividuales(PrestamoIndividual $pi, bool $isCreation): void
    {
        if (!$isCreation && !$pi->isDirty('monto_prestado_individual')) {
            return;
        }

        $prestamo = $pi->prestamo;

        if (!$prestamo) {
            return;
        }

        $monto       = (float) $pi->monto_prestado_individual;
        $tasaInteres = (float) ($prestamo->tasa_interes ?? 17);
        $numCuotas   = (int) ($prestamo->cantidad_cuotas ?? 1);

        $seguro = match ((int) $monto) {
            400     => 7,
            500, 600 => 8,
            700, 800 => 9,
            900, 1000 => 10,
            default  => 7,
        };

        $interes      = $monto * ($tasaInteres / 100);
        $montoDevolver = $monto + $interes + $seguro;
        $cuotaIndiv   = $montoDevolver / max($numCuotas, 1);

        $pi->updateQuietly([
            'seguro'                       => round($seguro, 2),
            'interes'                      => round($interes, 2),
            'monto_devolver_individual'    => round($montoDevolver, 2),
            'monto_cuota_prestamo_individual' => round($cuotaIndiv, 2),
        ]);
    }
}
