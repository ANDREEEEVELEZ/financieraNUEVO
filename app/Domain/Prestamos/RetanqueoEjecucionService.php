<?php

namespace App\Domain\Prestamos;

use App\Actions\Retanqueo\RegistrarCoberturaRetanqueoAction;
use App\Contracts\RetanqueoEjecucionInterface;
use App\Contracts\SaldoCuotaServiceInterface;
use App\Domain\Prestamos\Concerns\ValidaParticipantesRetanqueoActivos;
use App\Models\Retanqueo;
use App\Models\Prestamo;
use App\Models\PrestamoIndividual;
use App\Models\CuotasGrupales;
use App\Models\Cliente;
use Illuminate\Support\Collection;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RetanqueoEjecucionService implements RetanqueoEjecucionInterface
{
    use ValidaParticipantesRetanqueoActivos;

    /**
     * Ejecuta un retanqueo aprobado.
     * Self-contained: does NOT call WorkflowService internally.
     */
    public function ejecutarRetanqueo(int $retanqueoId, array $datosCuenta = []): Retanqueo
    {
        return DB::transaction(function () use ($retanqueoId, $datosCuenta) {
            $retanqueo = Retanqueo::with([
                'prestamoAntiguo.grupo',
                'retanqueosIndividuales.cliente.persona',
            ])->find($retanqueoId);

            if (!$retanqueo) {
                throw new \Exception('Retanqueo no encontrado');
            }

            if (!$retanqueo->estaAprobado()) {
                throw new \Exception('Solo se pueden ejecutar retanqueos aprobados');
            }

            // Coordinación retanqueo ↔ separación (Eje 5): un integrante
            // original pudo haber sido separado del grupo entre la aprobación
            // y esta ejecución. Separación bloquea retanqueo, nunca al revés.
            self::validarParticipantesActivos($retanqueo, 'EJECUCIÓN');

            $prestamoAntiguo = $retanqueo->prestamoAntiguo;
            $grupo           = $prestamoAntiguo->grupo;

            // Validate and sanitize account data
            $datosCuentaLimpios = [];
            if (is_array($datosCuenta) && !empty($datosCuenta)) {
                if (!empty($datosCuenta['titular_cuenta_desembolso']) && is_string($datosCuenta['titular_cuenta_desembolso'])) {
                    $titular = trim($datosCuenta['titular_cuenta_desembolso']);
                    if (strlen($titular) >= 3 && preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/', $titular)) {
                        $datosCuentaLimpios['titular_cuenta_desembolso'] = $titular;
                    }
                }
                if (!empty($datosCuenta['numero_cuenta_desembolso']) && is_string($datosCuenta['numero_cuenta_desembolso'])) {
                    $numeroCuenta = trim($datosCuenta['numero_cuenta_desembolso']);
                    if (preg_match('/^[0-9]{14}$/', $numeroCuenta)) {
                        $datosCuentaLimpios['numero_cuenta_desembolso'] = $numeroCuenta;
                    }
                }
            }

            if ($retanqueo->prestamo_nuevo_id) {
                $nuevoPrestamo = Prestamo::find($retanqueo->prestamo_nuevo_id);

                if ($nuevoPrestamo && $nuevoPrestamo->estado === 'Pendiente') {
                    $actualizacion = ['estado' => 'Aprobado'];

                    if (!empty($datosCuentaLimpios['titular_cuenta_desembolso'])) {
                        $actualizacion['titular_cuenta_desembolso'] = $datosCuentaLimpios['titular_cuenta_desembolso'];
                    }
                    if (!empty($datosCuentaLimpios['numero_cuenta_desembolso'])) {
                        $actualizacion['numero_cuenta_desembolso'] = $datosCuentaLimpios['numero_cuenta_desembolso'];
                    }

                    $nuevoPrestamo->update($actualizacion);
                    $nuevoPrestamo->prestamoIndividual()->update(['estado' => 'Aprobado']);

                    Log::info('Préstamo existente activado (Pendiente -> Aprobado)', [
                        'prestamo_id'            => $nuevoPrestamo->id,
                        'retanqueo_id'           => $retanqueoId,
                        'datos_cuenta_actualizados' => !empty($datosCuentaLimpios),
                    ]);
                } else {
                    throw new \Exception('El préstamo asociado al retanqueo no existe o no está en estado Pendiente');
                }
            } else {
                $nuevoPrestamo = $this->crearNuevoPrestamo($retanqueo, $grupo, $datosCuentaLimpios);
                $this->crearPrestamosIndividualesNuevos($retanqueo, $nuevoPrestamo);
                $retanqueo->update(['prestamo_nuevo_id' => $nuevoPrestamo->id]);
            }

            $this->generarCuotasGrupalesNuevas($nuevoPrestamo);
            $this->gestionarCambiosMembresiaGrupo($retanqueo, $grupo);
            $this->actualizarPrestamoAntiguo($retanqueo);

            $retanqueo->update(['estado_retanqueo' => 'ejecutado']);

            Log::info('Retanqueo ejecutado exitosamente', [
                'retanqueo_id'      => $retanqueoId,
                'prestamo_antiguo_id' => $prestamoAntiguo->id,
                'prestamo_nuevo_id' => $nuevoPrestamo->id,
            ]);

            return $retanqueo->fresh(['prestamoNuevo']);
        });
    }

    // ─── Private helpers ────────────────────────────────────────────────────────

    private function crearNuevoPrestamo($retanqueo, $grupo, array $datosCuenta = [])
    {
        $prestamoAntiguo = $retanqueo->prestamoAntiguo;

        $numeroRetanqueo = Retanqueo::whereHas('prestamoAntiguo', function ($query) use ($grupo) {
            $query->where('grupo_id', $grupo->id);
        })->where('estado_retanqueo', 'ejecutado')->count() + 1;

        $descripcionRetanqueo = "RETANQUEO #{$numeroRetanqueo} {$grupo->nombre_grupo}";

        $nuevoPrestamoData = [
            'grupo_id'             => $grupo->id,
            'tasa_interes'         => $prestamoAntiguo->tasa_interes ?? 17,
            'monto_prestado_total' => $retanqueo->monto_retanqueo,
            'monto_devolver'       => 0,
            'cantidad_cuotas'      => $retanqueo->cantidad_cuotas_nuevo ?? 4,
            'fecha_prestamo'       => now(),
            'frecuencia'           => $prestamoAntiguo->frecuencia ?? 'semanal',
            'estado'               => 'Aprobado',
            'descripcion'          => $descripcionRetanqueo,
            'es_retanqueo'         => true,
            'prestamo_origen_id'   => $prestamoAntiguo->id,
        ];

        if (is_array($datosCuenta)) {
            if (!empty($datosCuenta['titular_cuenta_desembolso']) && is_string($datosCuenta['titular_cuenta_desembolso'])) {
                $titular = trim($datosCuenta['titular_cuenta_desembolso']);
                if (strlen($titular) >= 3 && preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/', $titular)) {
                    $nuevoPrestamoData['titular_cuenta_desembolso'] = $titular;
                }
            }
            if (!empty($datosCuenta['numero_cuenta_desembolso']) && is_string($datosCuenta['numero_cuenta_desembolso'])) {
                $numeroCuenta = trim($datosCuenta['numero_cuenta_desembolso']);
                if (preg_match('/^[0-9]{14}$/', $numeroCuenta)) {
                    $nuevoPrestamoData['numero_cuenta_desembolso'] = $numeroCuenta;
                }
            }
        }

        return Prestamo::create($nuevoPrestamoData);
    }

    private function crearPrestamosIndividualesNuevos($retanqueo, $nuevoPrestamo): void
    {
        $participantes      = $retanqueo->retanqueosIndividuales()
            ->whereIn('participacion_tipo', ['retanquea', 'nueva'])
            ->get();

        $montoTotalDevolver = 0;

        foreach ($participantes as $participante) {
            $cliente         = $participante->cliente;
            $montoSolicitado = $participante->monto_solicitado;
            $tasaInteres     = $nuevoPrestamo->tasa_interes ?? 17;
            $numCuotas       = $nuevoPrestamo->cantidad_cuotas;
            $seguro          = $this->calcularSeguro($montoSolicitado);
            $interes         = $montoSolicitado * ($tasaInteres / 100);
            $montoDevolver   = $montoSolicitado + $interes + $seguro;
            $cuotaIndividual = $montoDevolver / $numCuotas;

            PrestamoIndividual::create([
                'prestamo_id'                     => $nuevoPrestamo->id,
                'cliente_id'                      => $cliente->id,
                'monto_prestado_individual'       => $montoSolicitado,
                'monto_cuota_prestamo_individual' => round($cuotaIndividual, 2),
                'monto_devolver_individual'       => round($montoDevolver, 2),
                'seguro'                          => $seguro,
                'interes'                         => round($interes, 2),
                'estado'                          => 'Aprobado',
            ]);

            $montoTotalDevolver += $montoDevolver;
            $participante->update(['monto_cuota' => round($cuotaIndividual, 2)]);
        }

        $nuevoPrestamo->update(['monto_devolver' => round($montoTotalDevolver, 2)]);
    }

    private function calcularSeguro(float $monto): int
    {
        $montoInt = (int) $monto;

        if ($montoInt === 400) {
            return 7;
        }
        if ($montoInt === 500 || $montoInt === 600) {
            return 8;
        }
        if ($montoInt === 700 || $montoInt === 800) {
            return 9;
        }
        if ($montoInt === 900 || $montoInt === 1000) {
            return 10;
        }

        throw new \InvalidArgumentException("Monto no válido para cálculo de seguro: {$monto}");
    }

    private function generarCuotasGrupalesNuevas($nuevoPrestamo): void
    {
        $cuotaGrupal = $nuevoPrestamo->monto_devolver / $nuevoPrestamo->cantidad_cuotas;
        $fechaInicio = Carbon::parse($nuevoPrestamo->fecha_desembolso);

        for ($i = 1; $i <= $nuevoPrestamo->cantidad_cuotas; $i++) {
            $fechaVencimiento = $this->calcularFechaVencimiento($fechaInicio, $i, $nuevoPrestamo->frecuencia);

            CuotasGrupales::create([
                'prestamo_id'         => $nuevoPrestamo->id,
                'numero_cuota'        => $i,
                'monto_cuota_grupal'  => round($cuotaGrupal, 2),
                'fecha_vencimiento'   => $fechaVencimiento,
                'estado_cuota_grupal' => 'vigente',
                'estado_pago'         => 'pendiente',
            ]);
        }
    }

    private function calcularFechaVencimiento(Carbon $fechaInicio, int $numeroCuota, string $frecuencia): Carbon
    {
        return match ($frecuencia) {
            'quincenal' => $fechaInicio->copy()->addWeeks($numeroCuota * 2),
            'mensual'   => $fechaInicio->copy()->addMonths($numeroCuota),
            default     => $fechaInicio->copy()->addWeeks($numeroCuota), // semanal
        };
    }

    /**
     * Cubre las cuotas pendientes del préstamo antiguo con la cobertura de
     * retanqueo — vía ledger (RegistrarCoberturaRetanqueoAction), NUNCA
     * mutando `saldo_pendiente` directamente (SDD core-contable-seguridad,
     * Slice C, Req 1 Scenario 1.1/1.3). Decompuesto en métodos ≤50 líneas
     * (decision D8/D6).
     */
    private function actualizarPrestamoAntiguo(Retanqueo $retanqueo): void
    {
        $prestamoAntiguo     = $retanqueo->prestamoAntiguo;
        $montoUsadoCobertura = $retanqueo->monto_usado_para_cubrir_antiguo;

        if ($montoUsadoCobertura <= 0) {
            return;
        }

        $clientesQueRetanquean = $retanqueo->retanqueosIndividuales()
            ->whereIn('participacion_tipo', ['retanquea', 'nueva'])
            ->with('cliente')
            ->get();

        $totalIntegrantes = $prestamoAntiguo->grupo->clientes()->count();

        if ($clientesQueRetanquean->count() <= 0 || $totalIntegrantes <= 0) {
            return;
        }

        $this->cubrirCuotasPendientesConRetanqueo($retanqueo, $prestamoAntiguo, $clientesQueRetanquean);
        $this->actualizarEstadoPrestamoAntiguo($retanqueo, $prestamoAntiguo);
        $this->recalcularSaldoRestantePrestamoAntiguo($retanqueo, $prestamoAntiguo);
    }

    /**
     * Recorre las cuotas grupales pendientes (ledger-derived, no columna) y
     * aplica cobertura de retanqueo a cada una que aún tenga saldo.
     */
    private function cubrirCuotasPendientesConRetanqueo(
        Retanqueo $retanqueo,
        Prestamo $prestamoAntiguo,
        Collection $clientesQueRetanquean
    ): void {
        $saldoServicio = app(SaldoCuotaServiceInterface::class);

        $cuotasPendientes = $prestamoAntiguo->cuotasGrupales()
            ->where('estado_pago', '!=', 'pagado')
            ->orderBy('numero_cuota')
            ->lockForUpdate()
            ->get();

        foreach ($cuotasPendientes as $cuota) {
            if (bccomp($saldoServicio->saldoTotal($cuota), '0.00', 2) <= 0) {
                continue;
            }

            $this->cubrirCuotaIndividualmente($retanqueo, $cuota, $clientesQueRetanquean, $prestamoAntiguo, $saldoServicio);
        }
    }

    /**
     * Aplica la cobertura de retanqueo a UNA cuota grupal, capada al saldo
     * real (nunca sobrepaga el ledger). Delega la creación de Pago +
     * AplicacionPago + transición de estado a RegistrarCoberturaRetanqueoAction.
     */
    private function cubrirCuotaIndividualmente(
        Retanqueo $retanqueo,
        CuotasGrupales $cuota,
        Collection $clientesQueRetanquean,
        Prestamo $prestamoAntiguo,
        SaldoCuotaServiceInterface $saldoServicio
    ): void {
        $saldoActualCuota  = $saldoServicio->saldoTotal($cuota);
        $montoTotalACubrir = $this->calcularMontoACubrir($clientesQueRetanquean, $prestamoAntiguo);

        if (bccomp($montoTotalACubrir, '0.00', 2) <= 0) {
            return;
        }

        // Cap: la cobertura nunca puede exceder lo realmente adeudado.
        $montoCobertura = bccomp($montoTotalACubrir, $saldoActualCuota, 2) > 0
            ? $saldoActualCuota
            : $montoTotalACubrir;

        if (bccomp($montoCobertura, '0.00', 2) <= 0) {
            return;
        }

        app(RegistrarCoberturaRetanqueoAction::class)($cuota, $montoCobertura, $retanqueo);

        Log::info('RetanqueoEjecucionService: Cobertura individual aplicada correctamente', [
            'cuota_id'       => $cuota->id,
            'numero_cuota'   => $cuota->numero_cuota,
            'saldo_original' => $saldoActualCuota,
            'monto_cubierto' => $montoCobertura,
        ]);
    }

    /**
     * Suma la cuota individual (monto_cuota_prestamo_individual) de cada
     * cliente que retanquea — su contribución fija a la cobertura del
     * préstamo antiguo, independiente del saldo real de la cuota grupal.
     */
    private function calcularMontoACubrir(Collection $clientesQueRetanquean, Prestamo $prestamoAntiguo): string
    {
        $montoTotalACubrir = '0.00';

        foreach ($clientesQueRetanquean as $retanqueoIndividual) {
            $prestamoIndividual = $prestamoAntiguo->prestamoIndividual()
                ->where('cliente_id', $retanqueoIndividual->cliente_id)
                ->first();

            if ($prestamoIndividual) {
                $montoTotalACubrir = bcadd(
                    $montoTotalACubrir,
                    (string) $prestamoIndividual->monto_cuota_prestamo_individual,
                    2
                );
            }
        }

        return $montoTotalACubrir;
    }

    /**
     * Transiciona el préstamo antiguo a Activo (parcialmente retanqueado, si
     * quedan integrantes que no retanquearon) o Finalizado (todos retanquearon).
     * No depende de saldo_pendiente — solo cuenta participación.
     */
    private function actualizarEstadoPrestamoAntiguo(Retanqueo $retanqueo, Prestamo $prestamoAntiguo): void
    {
        $integrantesNoRetanqueados = $retanqueo->retanqueosIndividuales()
            ->where('participacion_tipo', 'no_retanquea')
            ->count();

        if ($integrantesNoRetanqueados > 0) {
            $prestamoAntiguo->update([
                'estado'                      => Prestamo::ESTADO_ACTIVO,
                'es_parcialmente_retanqueado' => true,
            ]);
            $retanqueo->update(['prestamo_antiguo_estado' => 0]);

            Log::info('RetanqueoEjecucionService: Préstamo marcado como Activo (parcialmente retanqueado)', [
                'prestamo_id'                 => $prestamoAntiguo->id,
                'integrantes_no_retanqueados' => $integrantesNoRetanqueados,
                'razon'                       => 'Hay integrantes que no retanquearon y deben seguir pagando',
            ]);
        } else {
            $prestamoAntiguo->update(['estado' => 'Finalizado']);
            $retanqueo->update(['prestamo_antiguo_estado' => 1]);

            Log::info('RetanqueoEjecucionService: Préstamo marcado como Finalizado', [
                'prestamo_id' => $prestamoAntiguo->id,
                'razon'       => 'Todos los integrantes retanquearon',
            ]);
        }
    }

    /**
     * Recalcula el saldo restante del préstamo antiguo desde el ledger
     * (SaldoCuotaService::saldoTotalGrupo) en vez de sumar la columna
     * cuotas_grupales.saldo_pendiente, que este método ya no escribe y
     * quedaría desactualizada para las cuotas recién cubiertas.
     */
    private function recalcularSaldoRestantePrestamoAntiguo(Retanqueo $retanqueo, Prestamo $prestamoAntiguo): void
    {
        $saldoRestanteTotal = app(SaldoCuotaServiceInterface::class)->saldoTotalGrupo($prestamoAntiguo);

        $retanqueo->update(['saldo_restante_prestamo_antiguo' => $saldoRestanteTotal]);
    }

    private function gestionarCambiosMembresiaGrupo($retanqueo, $grupo): void
    {
        $fecha                 = now()->toDateString();
        $retanqueosIndividuales = $retanqueo->retanqueosIndividuales;

        Log::info('Gestionando cambios de membresía del grupo', [
            'retanqueo_id'       => $retanqueo->id,
            'grupo_id'           => $grupo->id,
            'participantes_count' => $retanqueosIndividuales->count(),
        ]);

        foreach ($retanqueosIndividuales as $retanqueoIndividual) {
            $clienteId        = $retanqueoIndividual->cliente_id;
            $participacionTipo = $retanqueoIndividual->participacion_tipo;
            $esMiembroActual  = $grupo->clientes()->where('clientes.id', $clienteId)->exists();

            if ($participacionTipo === 'no_retanquea' && $esMiembroActual) {
                $grupo->clientes()->updateExistingPivot($clienteId, ['fecha_salida' => $fecha]);

                Log::info('Cliente marcado como ex-integrante', [
                    'cliente_id'  => $clienteId,
                    'grupo_id'    => $grupo->id,
                    'fecha_salida' => $fecha,
                ]);
            } elseif ($participacionTipo === 'nueva' && !$esMiembroActual) {
                $grupo->clientes()->attach($clienteId, [
                    'fecha_ingreso'         => $fecha,
                    'fecha_salida'          => null,
                    'estado_grupo_cliente'  => 'activo',
                ]);

                $cliente = Cliente::find($clienteId);
                if ($cliente && !$cliente->ciclo) {
                    $cliente->update(['ciclo' => 'I']);
                }

                Log::info('Cliente agregado como nuevo integrante', [
                    'cliente_id'  => $clienteId,
                    'grupo_id'    => $grupo->id,
                    'fecha_ingreso' => $fecha,
                ]);
            }
        }

        $integrantesActivos = $grupo->clientes()->count();
        $grupo->update(['numero_integrantes' => $integrantesActivos]);

        Log::info('Cambios de membresía completados', [
            'grupo_id'                  => $grupo->id,
            'nuevos_integrantes_activos' => $integrantesActivos,
        ]);
    }
}
