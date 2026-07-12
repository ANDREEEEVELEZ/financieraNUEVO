<?php

namespace App\Actions\Retanqueo;

use App\Contracts\SaldoCuotaServiceInterface;
use App\Domain\Pagos\Concerns\DistribuyeEnCuotasIndividuales;
use App\Events\Domain\CoberturaRetanqueoRegistrada;
use App\Models\CuotasGrupales;
use App\Models\Pago;
use App\Models\Retanqueo;
use InvalidArgumentException;

/**
 * Registra la cobertura de retanqueo de una cuota del préstamo antiguo como
 * un evento de ledger real — NO como una mutación directa de columna
 * (SDD core-contable-seguridad, Slice C, Requirement 1, decision D6).
 *
 * Crea UN Pago{origen_pago:'retanqueo', estado_pago:'aprobado'} y sus
 * AplicacionPago{tipo_aplicacion:'retanqueo'} vía el mismo applicator
 * interés-primero que usa PagoService para pagos en efectivo (DistribuyeEn
 * CuotasIndividuales), para que saldoPendiente() refleje la cobertura sin
 * ningún caso especial. NO crea Ingreso: la cobertura de retanqueo no es
 * dinero cobrado (decision #241 pt.2 — excluida de cobranza).
 *
 * Debe invocarse dentro de la transacción del caller (ejecutarRetanqueo);
 * esta Action nunca abre su propia transacción ni hace commit/rollback.
 */
class RegistrarCoberturaRetanqueoAction
{
    use DistribuyeEnCuotasIndividuales;

    public function __construct(
        private readonly SaldoCuotaServiceInterface $saldoCuotaService,
    ) {}

    public function __invoke(
        CuotasGrupales $cuotaGrupal,
        string $montoCobertura,
        ?Retanqueo $retanqueo = null
    ): Pago {
        $montoCobertura = number_format((float) $montoCobertura, 2, '.', '');

        if (bccomp($montoCobertura, '0.00', 2) <= 0) {
            throw new InvalidArgumentException('El monto de cobertura de retanqueo debe ser mayor a 0.');
        }

        $pago = Pago::create([
            'cuota_grupal_id' => $cuotaGrupal->id,
            'origen_pago' => 'retanqueo',
            'tipo_pago' => 'retanqueo',
            'monto_pagado' => $montoCobertura,
            'monto_mora_pagada' => '0.00',
            'estado_pago' => 'aprobado',
            'fecha_pago' => now(),
            'observaciones' => 'COBERTURA POR RETANQUEO',
        ]);

        // Mora bucket queda en 0.00: el retanqueo cubre principal, no penalidades (decision D2).
        $this->distribuirEnCuotasIndividuales($pago, $cuotaGrupal, $montoCobertura, '0.00', 'retanqueo');

        $this->actualizarEstadoCuota($cuotaGrupal);

        CoberturaRetanqueoRegistrada::dispatch($retanqueo, $pago);

        return $pago;
    }

    /**
     * Deriva estado_pago/estado_cuota_grupal del saldo ledger — el mismo
     * criterio que PagoService::aprobarPago() usa para pagos en efectivo,
     * ahora también aplicado a la cobertura de retanqueo (Req 1 Scenario 1.2).
     */
    private function actualizarEstadoCuota(CuotasGrupales $cuota): void
    {
        $cuota = $cuota->fresh(['mora']);

        $saldoTotal = $this->saldoCuotaService->saldoTotal($cuota);
        $saldoMora = $this->saldoCuotaService->saldoMora($cuota);

        $cuota->estado_pago = bccomp($saldoTotal, '0.00', 2) === 0 ? 'pagado' : 'parcial';
        $cuota->estado_cuota_grupal = bccomp($saldoTotal, '0.00', 2) === 0
            ? 'cancelada'
            : (bccomp($saldoMora, '0.00', 2) > 0 ? 'mora' : 'vigente');

        $cuota->save();
    }
}
