<?php

namespace App\Domain\Prestamos;

use App\Contracts\RetanqueoWorkflowInterface;
use App\Models\Retanqueo;
use App\Models\RetanqueoIndividual;
use App\Models\Prestamo;
use App\Models\Cliente;
use App\Helpers\CicloHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RetanqueoWorkflowService implements RetanqueoWorkflowInterface
{
    /**
     * Crea una nueva solicitud de retanqueo.
     */
    public function crearSolicitudRetanqueo(int $prestamoId, array $participantes, array $datosRetanqueo = []): Retanqueo
    {
        return DB::transaction(function () use ($prestamoId, $participantes, $datosRetanqueo) {
            $prestamo = Prestamo::with('grupo.clientes')->find($prestamoId);

            if (!$prestamo) {
                throw new \Exception('Préstamo no encontrado');
            }

            if ($prestamo->estado !== 'Aprobado') {
                throw new \Exception('Solo se pueden retanquear préstamos aprobados');
            }

            $retanqueo = Retanqueo::create([
                'prestamo_id'                     => $prestamoId,
                'prestamo_nuevo_id'               => null,
                'total_cuotas_cubiertas_antiguo'  => 0,
                'monto_retanqueo'                 => 0,
                'monto_usado_para_cubrir_antiguo' => 0,
                'monto_desembolsar'               => 0,
                'monto_cuota'                     => $datosRetanqueo['monto_cuota'] ?? null,
                'cantidad_cuotas_nuevo'           => $datosRetanqueo['cantidad_cuotas'] ?? 4,
                'saldo_restante_prestamo_antiguo' => 0,
                'prestamo_antiguo_estado'         => 0,
                'fecha_aceptacion'                => null,
                'estado_retanqueo'                => 'solicitud_pendiente',
            ]);

            $totalRetanqueo = 0;
            $totalCobertura = 0;

            foreach ($participantes as $participante) {
                $cliente = Cliente::find($participante['cliente_id']);
                if (!$cliente) {
                    continue;
                }

                $ciclo          = CicloHelper::normalize($cliente->ciclo ?? 'I');
                $montoMaximo    = CicloHelper::getMontoMaximo($ciclo);
                $montoSolicitado = min($participante['monto_solicitado'] ?? 0, $montoMaximo);

                if (in_array($participante['participacion_tipo'], ['retanquea', 'nueva']) && $montoSolicitado < 100) {
                    throw new \Exception("El monto mínimo es S/ 100 para {$cliente->persona->nombre}");
                }

                if ($participante['participacion_tipo'] === 'no_retanquea') {
                    $montoSolicitado = 0;
                }

                $aporteCobertura = 0;
                if ($participante['participacion_tipo'] === 'retanquea') {
                    $aporteCobertura = $this->calcularAporteCoberturaIndividual($prestamo, $participantes, $participante['cliente_id']);
                }

                RetanqueoIndividual::create([
                    'retanqueo_id'                 => $retanqueo->id,
                    'cliente_id'                   => $participante['cliente_id'],
                    'participacion_tipo'            => $participante['participacion_tipo'],
                    'aporte_cobertura'             => $aporteCobertura,
                    'monto_solicitado'             => $montoSolicitado,
                    'monto_desembolsar'            => $montoSolicitado - $aporteCobertura,
                    'monto_cuota'                  => null,
                    'aceptacion_cliente'           => 0,
                    'estado_retanqueo_individual'  => 'propuesto',
                ]);

                if (in_array($participante['participacion_tipo'], ['retanquea', 'nueva'])) {
                    $totalRetanqueo += $montoSolicitado;
                }

                if ($participante['participacion_tipo'] === 'retanquea') {
                    $totalCobertura += $aporteCobertura;
                }
            }

            $retanqueo->update([
                'monto_retanqueo'                 => $totalRetanqueo,
                'monto_usado_para_cubrir_antiguo' => $totalCobertura,
                'monto_desembolsar'               => $totalRetanqueo - $totalCobertura,
                'saldo_restante_prestamo_antiguo' => $this->calcularSaldoRestante($prestamo, $totalCobertura),
            ]);

            if (!empty($datosRetanqueo['datos_cuenta'])) {
                $nuevoPrestamo = $this->crearNuevoPrestamoPendiente($retanqueo, $prestamo->grupo, $datosRetanqueo['datos_cuenta']);
                $retanqueo->update(['prestamo_nuevo_id' => $nuevoPrestamo->id]);

                Log::info('Préstamo pendiente creado automáticamente con la solicitud', [
                    'retanqueo_id'    => $retanqueo->id,
                    'prestamo_nuevo_id' => $nuevoPrestamo->id,
                ]);
            }

            Log::info('Solicitud de retanqueo creada', [
                'retanqueo_id'            => $retanqueo->id,
                'prestamo_id'             => $prestamoId,
                'total_participantes'     => count($participantes),
                'prestamo_pendiente_creado' => !empty($datosRetanqueo['datos_cuenta']),
            ]);

            return $retanqueo->fresh(['retanqueosIndividuales.cliente.persona']);
        });
    }

    /**
     * Aprueba una solicitud de retanqueo.
     */
    public function aprobarRetanqueo(int $retanqueoId, string $observaciones = ''): Retanqueo
    {
        return DB::transaction(function () use ($retanqueoId, $observaciones) {
            $retanqueo = Retanqueo::with([
                'prestamoAntiguo.grupo.clientes.persona',
                'retanqueosIndividuales.cliente.persona',
            ])->find($retanqueoId);

            if (!$retanqueo) {
                throw new \Exception('Retanqueo no encontrado');
            }

            if (!$retanqueo->esSolicitudPendiente()) {
                throw new \Exception('Solo se pueden aprobar solicitudes pendientes');
            }

            $prestamoAntiguo  = $retanqueo->prestamoAntiguo;
            $cuotasPendientes = $prestamoAntiguo->cuotasGrupales()
                ->where('estado_pago', '!=', 'pagado')
                ->where('saldo_pendiente', '>', 0)
                ->count();

            if ($cuotasPendientes !== 1) {
                throw new \Exception("APROBACIÓN BLOQUEADA: El préstamo {$prestamoAntiguo->id} tiene {$cuotasPendientes} cuotas pendientes. Solo se pueden aprobar retanqueos cuando queda EXACTAMENTE 1 cuota por pagar. Operación cancelada por seguridad financiera.");
            }

            $retanqueo->update([
                'estado_retanqueo' => 'aprobado',
                'fecha_aceptacion' => now(),
            ]);

            $retanqueo->retanqueosIndividuales()->update([
                'estado_retanqueo_individual' => 'aceptado',
                'aceptacion_cliente'          => 1,
            ]);

            Log::info('Retanqueo aprobado', [
                'retanqueo_id' => $retanqueoId,
                'observaciones' => $observaciones,
            ]);

            return $retanqueo->fresh();
        });
    }

    /**
     * Rechaza una solicitud de retanqueo.
     */
    public function rechazarRetanqueo(int $retanqueoId, string $motivo = ''): Retanqueo
    {
        return DB::transaction(function () use ($retanqueoId, $motivo) {
            $retanqueo = Retanqueo::with('prestamoNuevo')->find($retanqueoId);

            if (!$retanqueo) {
                throw new \Exception('Retanqueo no encontrado');
            }

            if (!$retanqueo->esSolicitudPendiente()) {
                throw new \Exception('Solo se pueden rechazar solicitudes pendientes');
            }

            if ($retanqueo->prestamo_nuevo_id && $retanqueo->prestamoNuevo) {
                $prestamoNuevo = $retanqueo->prestamoNuevo;

                if ($prestamoNuevo->estado === 'Pendiente') {
                    $prestamoNuevo->update(['estado' => 'Rechazado']);
                    $prestamoNuevo->prestamoIndividual()->update(['estado' => 'Rechazado']);

                    Log::info('Préstamo asociado al retanqueo rechazado actualizado', [
                        'retanqueo_id'     => $retanqueoId,
                        'prestamo_nuevo_id' => $prestamoNuevo->id,
                        'estado_anterior'  => 'Pendiente',
                        'estado_nuevo'     => 'Rechazado',
                    ]);
                }
            }

            $retanqueo->update(['estado_retanqueo' => 'rechazado']);
            $retanqueo->retanqueosIndividuales()->update([
                'estado_retanqueo_individual' => 'rechazado',
            ]);

            Log::info('Retanqueo rechazado', [
                'retanqueo_id' => $retanqueoId,
                'motivo'       => $motivo,
            ]);

            return $retanqueo->fresh();
        });
    }

    // ─── Private helpers ────────────────────────────────────────────────────────

    private function calcularAporteCoberturaIndividual($prestamo, array $participantes, ?int $clienteId = null): float
    {
        if ($clienteId) {
            $prestamoIndividual = $prestamo->prestamoIndividual()
                ->where('cliente_id', $clienteId)
                ->first();

            if (!$prestamoIndividual) {
                return 0;
            }

            $cuotasPendientes = $prestamo->cuotasGrupales()
                ->where('estado_pago', '!=', 'pagado')
                ->count();

            $aporteCobertura = round($prestamoIndividual->monto_cuota_prestamo_individual * $cuotasPendientes, 2);

            Log::info('RetanqueoWorkflowService: Calculando aporte de cobertura individual', [
                'cliente_id'                  => $clienteId,
                'cuota_individual_original'   => $prestamoIndividual->monto_cuota_prestamo_individual,
                'cuotas_pendientes'           => $cuotasPendientes,
                'aporte_cobertura'            => $aporteCobertura,
            ]);

            return $aporteCobertura;
        }

        return 0;
    }

    private function calcularSaldoRestante($prestamo, float $totalCobertura): float
    {
        $saldoPendienteTotal = $prestamo->cuotasGrupales()
            ->where('estado_pago', '!=', 'pagado')
            ->sum('saldo_pendiente');

        return max(0, $saldoPendienteTotal - $totalCobertura);
    }

    private function crearNuevoPrestamoPendiente($retanqueo, $grupo, array $datosCuenta = [])
    {
        $prestamoAntiguo = $retanqueo->prestamoAntiguo;

        $numeroRetanqueo = \App\Models\Retanqueo::whereHas('prestamoAntiguo', function ($query) use ($grupo) {
            $query->where('grupo_id', $grupo->id);
        })->where('estado_retanqueo', 'ejecutado')->count() + 1;

        $descripcionRetanqueo = "RETANQUEO #{$numeroRetanqueo} {$grupo->nombre_grupo}";

        $nuevoPrestamoData = [
            'grupo_id'           => $grupo->id,
            'tasa_interes'       => $prestamoAntiguo->tasa_interes ?? 17,
            'monto_prestado_total' => $retanqueo->monto_retanqueo,
            'monto_devolver'     => 0,
            'cantidad_cuotas'    => $retanqueo->cantidad_cuotas_nuevo ?? 4,
            'fecha_prestamo'     => now(),
            'frecuencia'         => $prestamoAntiguo->frecuencia ?? 'semanal',
            'estado'             => 'Pendiente',
            'descripcion'        => $descripcionRetanqueo,
            'es_retanqueo'       => true,
            'prestamo_origen_id' => $prestamoAntiguo->id,
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

        $nuevoPrestamo = \App\Models\Prestamo::create($nuevoPrestamoData);

        $this->crearPrestamosIndividualesPendientes($retanqueo, $nuevoPrestamo);

        return $nuevoPrestamo;
    }

    private function crearPrestamosIndividualesPendientes($retanqueo, $nuevoPrestamo): void
    {
        $participantes     = $retanqueo->retanqueosIndividuales()
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

            \App\Models\PrestamoIndividual::create([
                'prestamo_id'                       => $nuevoPrestamo->id,
                'cliente_id'                        => $cliente->id,
                'monto_prestado_individual'         => $montoSolicitado,
                'monto_cuota_prestamo_individual'   => round($cuotaIndividual, 2),
                'monto_devolver_individual'         => round($montoDevolver, 2),
                'seguro'                            => $seguro,
                'interes'                           => round($interes, 2),
                'estado'                            => 'Pendiente',
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
}
