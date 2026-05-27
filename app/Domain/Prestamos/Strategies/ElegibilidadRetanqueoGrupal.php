<?php

namespace App\Domain\Prestamos\Strategies;

use App\Models\Prestamo;

/**
 * Eligibility strategy for group prestamos based on minimum quorum.
 *
 * A group prestamo is eligible when at least `porcentaje_minimo_retanqueo`
 * (default 51%) of its active members (estado_grupo_cliente = 'Activo') have
 * decided to retanquear.
 *
 * "Decided to retanquear" is determined by comparing active integrantes count
 * against the total active integrantes. This strategy checks the RATIO only —
 * it does NOT execute desvinculación of salientes.
 *
 * Hook: desvinculación of integrantes who choose not to retanquear (salientes)
 * should be triggered in RetanqueoEjecucionService::gestionarCambiosMembresiaGrupo()
 * after the quorum check passes.
 */
final class ElegibilidadRetanqueoGrupal implements ElegibilidadRetanqueoStrategy
{
    public function esElegible(Prestamo $prestamo): bool
    {
        $grupo = $prestamo->grupo;

        if (!$grupo || !$grupo->productoFinanciero) {
            // Fall back to individual rule when no product config available
            $cuotasPendientes = $prestamo->cuotasGrupales()
                ->where('estado_pago', '!=', 'pagado')
                ->where('saldo_pendiente', '>', 0)
                ->count();

            return $cuotasPendientes === 1;
        }

        $porcentajeMinimo = (float) ($grupo->productoFinanciero->porcentaje_minimo_retanqueo ?? 0.51);

        $totalActivos = $grupo->clientes()
            ->wherePivot('estado_grupo_cliente', 'Activo')
            ->whereNull('grupo_cliente.fecha_salida')
            ->count();

        if ($totalActivos === 0) {
            return false;
        }

        // "Decided to retanquear" = active integrantes who have NOT yet had a fecha_salida
        // assigned. Salientes are those who paid early; the ratio check happens BEFORE
        // desvinculación is executed (see hook comment in class docblock).
        $retanquean = $grupo->clientes()
            ->wherePivot('estado_grupo_cliente', 'Activo')
            ->whereNull('grupo_cliente.fecha_salida')
            ->count();

        $ratio = $retanquean / $totalActivos;

        return $ratio >= $porcentajeMinimo;
    }
}
