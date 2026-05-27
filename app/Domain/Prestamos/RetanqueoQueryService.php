<?php

namespace App\Domain\Prestamos;

use App\Contracts\RetanqueoQueryInterface;
use App\Domain\Prestamos\Strategies\ElegibilidadRetanqueoStrategy;
use App\Models\Grupo;
use App\Models\Prestamo;
use Illuminate\Support\Collection;

class RetanqueoQueryService implements RetanqueoQueryInterface
{
    public function __construct(
        private readonly ElegibilidadRetanqueoStrategy $elegibilidadStrategy
    ) {}

    /**
     * Obtiene los grupos elegibles para retanqueo.
     * RESTRICCIÓN: Solo préstamos que tengan EXACTAMENTE 1 cuota pendiente.
     */
    public function obtenerGruposElegibles(?int $asesorId = null): Collection
    {
        $query = Grupo::with(['prestamos.cuotasGrupales'])
            ->where('estado_grupo', 'Activo');

        if ($asesorId) {
            $query->where('asesor_id', $asesorId);
        }

        return $query->get()->filter(function ($grupo) {
            $prestamoActivo = $grupo->prestamos()
                ->where('estado', 'Aprobado')
                ->whereHas('cuotasGrupales', function ($q) {
                    $q->where('estado_pago', '!=', 'pagado')
                      ->where('saldo_pendiente', '>', 0);
                })
                ->first();

            if (!$prestamoActivo) {
                return false;
            }

            return $this->elegibilidadStrategy->esElegible($prestamoActivo);
        });
    }

    /**
     * Calcula el estado actual de un préstamo para retanqueo.
     * VALIDACIÓN CRÍTICA: Verificar que solo quede 1 cuota pendiente.
     */
    public function calcularEstadoPrestamo(int $prestamoId): array
    {
        $prestamo = Prestamo::with(['cuotasGrupales', 'grupo.clientes'])->find($prestamoId);

        if (!$prestamo) {
            throw new \Exception('Préstamo no encontrado');
        }

        $cuotasTotal     = $prestamo->cuotasGrupales->count();
        $cuotasPagadas   = $prestamo->cuotasGrupales->where('estado_pago', 'pagado')->count();
        $cuotasPendientes = $cuotasTotal - $cuotasPagadas;

        if ($cuotasPendientes !== 1) {
            throw new \Exception("Error: Este préstamo no es elegible para retanqueo. Tiene {$cuotasPendientes} cuotas pendientes, pero solo se permiten retanqueos cuando queda EXACTAMENTE 1 cuota por pagar.");
        }

        $saldoPendienteTotal = $prestamo->cuotasGrupales
            ->where('estado_pago', '!=', 'pagado')
            ->sum('saldo_pendiente');

        $montoPagado = $prestamo->monto_devolver - $saldoPendienteTotal;

        return [
            'prestamo'                  => $prestamo,
            'monto_prestado_original'   => $prestamo->monto_prestado_total,
            'monto_devolver_original'   => $prestamo->monto_devolver,
            'monto_pagado'              => $montoPagado,
            'saldo_pendiente_total'     => $saldoPendienteTotal,
            'cuotas_total'              => $cuotasTotal,
            'cuotas_pagadas'            => $cuotasPagadas,
            'cuotas_pendientes'         => $cuotasPendientes,
            'porcentaje_pagado'         => $cuotasTotal > 0 ? ($cuotasPagadas / $cuotasTotal) * 100 : 0,
            'puede_retanquear'          => $cuotasPagadas > 0 && $saldoPendienteTotal > 0,
        ];
    }
}
