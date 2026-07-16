<?php

namespace App\Console\Commands;

use App\Contracts\SaldoCuotaServiceInterface;
use App\Models\CuotasGrupales;
use Illuminate\Console\Command;

/**
 * Audita divergencias entre el saldo legacy (columna, escrita ad-hoc por el
 * flujo de retanqueo previo al ledger) y el saldo ledger-derived
 * (SaldoCuotaService::saldoTotal()).
 *
 * SDD core-contable-seguridad, Slice D, Requisito 6 — este comando DEBE
 * ejecutarse y su salida DEBE quedar capturada como evidencia ANTES de que la
 * migración que elimina `cuotas_grupales.saldo_pendiente` se aplique
 * (Requisito 6 Escenario 6.3). Es de solo lectura: no modifica
 * `cuotas_grupales`, `pagos` ni `aplicacion_pago`.
 *
 * Alcance: se auditan TODAS las cuotas grupales (no solo las de préstamos
 * marcados `es_parcialmente_retanqueado`), porque no existe un marcador de
 * estado literal "Parcialmente_Retanqueado" a nivel de cuota en el esquema
 * actual — solo el flag `prestamos.es_parcialmente_retanqueado` — y limitar
 * el barrido a ese flag arriesgaría falsos negativos (Escenario 6.1 exige
 * cero falsos negativos).
 *
 * Evidencia de ejecución pre-drop (Escenario 6.3): ejecutado el 2026-07-15
 * contra `emprendeconmigopruebas` (MySQL, entorno de pruebas compartido de
 * este proyecto). Resultado: "Sin divergencias" — la tabla `cuotas_grupales`
 * tenía 0 filas en ese momento (proyecto pre-producción, sin tráfico real
 * todavía, decisión #241/#244). No es una renuncia al requisito: el comando
 * corrió genuinamente contra el único estado disponible. La lógica de
 * detección de divergencias está cubierta por
 * tests/Feature/Console/ReporteInconsistenciaSaldoCommandTest.php, que siembra
 * divergencias deliberadas y confirma cero falsos negativos/positivos.
 */
class ReporteInconsistenciaSaldoCommand extends Command
{
    protected $signature = 'retanqueo:auditar-saldos';

    protected $description = 'Audita divergencias entre cuotas_grupales.saldo_pendiente (legacy) y el saldo ledger-derived, antes de eliminar la columna legacy';

    public function handle(SaldoCuotaServiceInterface $saldoServicio): int
    {
        $this->info('Auditando cuotas_grupales.saldo_pendiente vs saldo ledger-derived...');

        $divergentes = $this->encontrarDivergencias($saldoServicio);

        if ($divergentes->isEmpty()) {
            $this->info('Sin divergencias: el saldo legacy coincide con el saldo ledger-derived en todas las cuotas.');

            return self::SUCCESS;
        }

        $this->warn("{$divergentes->count()} cuota(s) con saldo divergente encontrada(s):");

        $this->table(
            ['Cuota ID', 'Préstamo ID', 'Saldo Guardado (legacy)', 'Saldo Ledger', 'Delta'],
            $divergentes->map(fn (array $d) => [
                $d['cuota_id'],
                $d['prestamo_id'],
                'S/ '.$d['stored'],
                'S/ '.$d['ledger'],
                'S/ '.$d['delta'],
            ])->all()
        );

        return self::SUCCESS;
    }

    /**
     * @return \Illuminate\Support\Collection<int, array{cuota_id:int,prestamo_id:int,stored:string,ledger:string,delta:string}>
     */
    private function encontrarDivergencias(SaldoCuotaServiceInterface $saldoServicio): \Illuminate\Support\Collection
    {
        $divergentes = collect();

        CuotasGrupales::with('mora')
            ->withSum(['pagos as monto_pagado_aprobado_sum' => function ($q) {
                $q->where('estado_pago', 'aprobado');
            }], 'monto_pagado')
            ->withSum(['pagos as monto_mora_pagada_aprobado_sum' => function ($q) {
                $q->where('estado_pago', 'aprobado');
            }], 'monto_mora_pagada')
            ->orderBy('prestamo_id')
            ->orderBy('numero_cuota')
            ->get()
            ->each(function (CuotasGrupales $cuota) use ($saldoServicio, $divergentes) {
                $guardado = number_format((float) $cuota->getRawOriginal('saldo_pendiente'), 2, '.', '');
                $ledger = $saldoServicio->saldoTotal($cuota);

                if (bccomp($guardado, $ledger, 2) !== 0) {
                    $divergentes->push([
                        'cuota_id' => $cuota->id,
                        'prestamo_id' => $cuota->prestamo_id,
                        'stored' => $guardado,
                        'ledger' => $ledger,
                        'delta' => bcsub($guardado, $ledger, 2),
                    ]);
                }
            });

        return $divergentes;
    }
}
