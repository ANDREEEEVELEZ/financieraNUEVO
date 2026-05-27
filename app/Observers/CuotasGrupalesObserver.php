<?php

namespace App\Observers;

use App\Models\CuotasGrupales;
use App\Models\PrestamoIndividual;
use App\Domain\Grupos\CicloService;

class CuotasGrupalesObserver
{
    public function __construct(private readonly CicloService $cicloService) {}

    public function updated(CuotasGrupales $cuota): void
    {
        $prestamo = $cuota->prestamo;

        if (!$prestamo) {
            return;
        }

        $sinPagar = $prestamo->cuotasGrupales()
            ->where('estado_pago', '!=', 'pagado')
            ->count();

        if ($sinPagar > 0 || in_array($prestamo->estado, ['Finalizado'])) {
            return;
        }

        if ($prestamo->tieneIntegrantesNoRetanqueadosConDeudaPendiente()) {
            return;
        }

        $prestamo->estado = 'Finalizado';
        $prestamo->save();

        PrestamoIndividual::where('prestamo_id', $prestamo->id)
            ->update(['estado' => 'Finalizado']);

        if ($prestamo->es_parcialmente_retanqueado) {
            $prestamo->moverIntegrantesNoRetanqueadosAExIntegrantes();
        }

        $prestamo->loadMissing('grupo.clientes');
        $this->cicloService->actualizarCiclosDeGrupo($prestamo);
    }
}
