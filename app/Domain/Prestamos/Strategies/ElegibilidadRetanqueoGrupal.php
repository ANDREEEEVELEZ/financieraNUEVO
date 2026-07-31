<?php

namespace App\Domain\Prestamos\Strategies;

use App\Domain\Prestamos\Concerns\FiltraCuotasPendientesConSaldo;
use App\Models\Prestamo;
use App\Models\Retanqueo;

/**
 * Eligibility strategy for group prestamos.
 *
 * Two distinct checks live here, evaluated at two distinct points in the
 * retanqueo lifecycle:
 *
 * - `esElegible()` — structural, discovery-time check (SR-4 contract with
 *   ElegibilidadRetanqueoStrategy). Used by RetanqueoQueryService::obtenerGruposElegibles()
 *   BEFORE any Retanqueo solicitud exists — there is no participation data
 *   (RetanqueoIndividual rows) to evaluate a quorum against yet, so this only
 *   verifies the same structural rule as ElegibilidadRetanqueoIndividual:
 *   exactly 1 cuota pendiente con saldo.
 *
 * - `cumpleQuorum()` — the REAL quorum check: at least `porcentaje_minimo_retanqueo`
 *   (default 51%) of the ORIGINAL group members (RetanqueoIndividual.participacion_tipo
 *   IN ['retanquea', 'no_retanquea'] — excludes 'nueva', brand-new incoming
 *   members) must have chosen 'retanquea'. Only meaningful once a Retanqueo
 *   with its RetanqueoIndividual rows exists, i.e. called from
 *   RetanqueoWorkflowService::aprobarRetanqueo().
 */
final class ElegibilidadRetanqueoGrupal implements ElegibilidadRetanqueoStrategy
{
    use FiltraCuotasPendientesConSaldo;

    private const MIEMBROS_ORIGINALES = ['retanquea', 'no_retanquea'];

    /**
     * Structural, discovery-time check — no participation data available yet.
     */
    public function esElegible(Prestamo $prestamo): bool
    {
        return self::cuotasPendientesConSaldo($prestamo)->count() === 1;
    }

    /**
     * Real quorum check, evaluated once a Retanqueo solicitud (with its
     * RetanqueoIndividual rows) already exists.
     */
    public function cumpleQuorum(Retanqueo $retanqueo): bool
    {
        [$retanquean, $totalOriginales, $porcentajeMinimo] = $this->datosQuorum($retanqueo);

        if ($totalOriginales === 0) {
            return false;
        }

        return ($retanquean / $totalOriginales) >= $porcentajeMinimo;
    }

    /**
     * Raw quorum figures: [retanquean, totalOriginales, porcentajeMinimo].
     * Exposed so callers can build an actionable error message without
     * re-running the same queries.
     */
    public function datosQuorum(Retanqueo $retanqueo): array
    {
        $porcentajeMinimo = (float) ($retanqueo->prestamoAntiguo?->producto?->porcentaje_minimo_retanqueo ?? 0.51);

        $totalOriginales = $retanqueo->retanqueosIndividuales()
            ->whereIn('participacion_tipo', self::MIEMBROS_ORIGINALES)
            ->count();

        $retanquean = $retanqueo->retanqueosIndividuales()
            ->where('participacion_tipo', 'retanquea')
            ->count();

        return [$retanquean, $totalOriginales, $porcentajeMinimo];
    }
}
