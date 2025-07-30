<?php

namespace App\Services;

use App\Models\Retanqueo;
use App\Models\RetanqueoIndividual;
use App\Models\Prestamo;
use App\Models\PrestamoIndividual;
use App\Models\CuotasGrupales;
use App\Models\Grupo;
use App\Models\Cliente;
use App\Helpers\CicloHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class RetanqueoService
{
    /**
     * Obtiene los grupos elegibles para retanqueo
     */
    public function obtenerGruposElegibles($asesorId = null)
    {
        $query = Grupo::with(['prestamos.cuotasGrupales'])
            ->where('estado_grupo', 'Activo');

        if ($asesorId) {
            $query->where('asesor_id', $asesorId);
        }

        return $query->get()->filter(function ($grupo) {
            // Verificar que tenga préstamos activos con saldo pendiente
            $prestamoActivo = $grupo->prestamos()
                ->where('estado', 'Aprobado')
                ->whereHas('cuotasGrupales', function ($q) {
                    $q->where('estado_pago', '!=', 'pagado')
                      ->where('saldo_pendiente', '>', 0);
                })
                ->first();

            return $prestamoActivo !== null;
        });
    }

    /**
     * Calcula el estado actual de un préstamo para retanqueo
     */
    public function calcularEstadoPrestamo($prestamoId)
    {
        $prestamo = Prestamo::with(['cuotasGrupales', 'grupo.clientes'])->find($prestamoId);
        
        if (!$prestamo) {
            throw new \Exception('Préstamo no encontrado');
        }

        $cuotasTotal = $prestamo->cuotasGrupales->count();
        $cuotasPagadas = $prestamo->cuotasGrupales->where('estado_pago', 'pagado')->count();
        $cuotasPendientes = $cuotasTotal - $cuotasPagadas;
        
        $saldoPendienteTotal = $prestamo->cuotasGrupales
            ->where('estado_pago', '!=', 'pagado')
            ->sum('saldo_pendiente');

        $montoPagado = $prestamo->monto_devolver - $saldoPendienteTotal;

        return [
            'prestamo' => $prestamo,
            'monto_prestado_original' => $prestamo->monto_prestado_total,
            'monto_devolver_original' => $prestamo->monto_devolver,
            'monto_pagado' => $montoPagado,
            'saldo_pendiente_total' => $saldoPendienteTotal,
            'cuotas_total' => $cuotasTotal,
            'cuotas_pagadas' => $cuotasPagadas,
            'cuotas_pendientes' => $cuotasPendientes,
            'porcentaje_pagado' => $cuotasTotal > 0 ? ($cuotasPagadas / $cuotasTotal) * 100 : 0,
            'puede_retanquear' => $cuotasPagadas > 0 && $saldoPendienteTotal > 0
        ];
    }

    /**
     * Crea una nueva solicitud de retanqueo
     */
    public function crearSolicitudRetanqueo($prestamoId, $participantes, $datosRetanqueo = [])
    {
        return DB::transaction(function () use ($prestamoId, $participantes, $datosRetanqueo) {
            $prestamo = Prestamo::with('grupo.clientes')->find($prestamoId);
            
            if (!$prestamo) {
                throw new \Exception('Préstamo no encontrado');
            }

            if ($prestamo->estado !== 'Aprobado') {
                throw new \Exception('Solo se pueden retanquear préstamos aprobados');
            }

            // Crear el retanqueo principal
            $retanqueo = Retanqueo::create([
                'prestamo_id' => $prestamoId,
                'prestamo_nuevo_id' => null, // Se asignará cuando se ejecute
                'total_cuotas_cubiertas_antiguo' => 0, // Se calculará
                'monto_retanqueo' => 0, // Se calculará
                'monto_usado_para_cubrir_antiguo' => 0, // Se calculará
                'monto_desembolsar' => 0, // Se calculará
                'monto_cuota' => $datosRetanqueo['monto_cuota'] ?? null,
                'cantidad_cuotas_nuevo' => $datosRetanqueo['cantidad_cuotas'] ?? 20,
                'saldo_restante_prestamo_antiguo' => 0, // Se calculará
                'prestamo_antiguo_estado' => 0, // Se calculará
                'fecha_aceptacion' => null,
                'estado_retanqueo' => 'solicitud_pendiente'
            ]);

            // Crear los retanqueos individuales
            $totalRetanqueo = 0;
            $totalCobertura = 0;

            foreach ($participantes as $participante) {
                $cliente = Cliente::find($participante['cliente_id']);
                if (!$cliente) {
                    continue;
                }

                // Validar monto según ciclo del cliente
                $ciclo = CicloHelper::normalize($cliente->ciclo ?? 'I');
                $montoMaximo = CicloHelper::getMontoMaximo($ciclo);
                $montoSolicitado = min($participante['monto_solicitado'] ?? 0, $montoMaximo);

                // Solo validar mínimo si el participante va a solicitar dinero
                if (in_array($participante['participacion_tipo'], ['retanquea', 'nueva']) && $montoSolicitado < 100) {
                    throw new \Exception("El monto mínimo es S/ 100 para {$cliente->persona->nombre}");
                }

                // Si no retanquea, el monto puede ser 0
                if ($participante['participacion_tipo'] === 'no_retanquea') {
                    $montoSolicitado = 0;
                }

                // Calcular aporte de cobertura si retanquea
                $aporteCobertura = 0;
                if ($participante['participacion_tipo'] === 'retanquea') {
                    $aporteCobertura = $this->calcularAporteCoberturaIndividual($prestamo, $participantes);
                }

                RetanqueoIndividual::create([
                    'retanqueo_id' => $retanqueo->id,
                    'cliente_id' => $participante['cliente_id'],
                    'participacion_tipo' => $participante['participacion_tipo'],
                    'aporte_cobertura' => $aporteCobertura,
                    'monto_solicitado' => $montoSolicitado,
                    'monto_desembolsar' => $montoSolicitado - $aporteCobertura,
                    'monto_cuota' => null, // Se calculará al aprobar
                    'aceptacion_cliente' => 0,
                    'estado_retanqueo_individual' => 'propuesto'
                ]);

                if (in_array($participante['participacion_tipo'], ['retanquea', 'nueva'])) {
                    $totalRetanqueo += $montoSolicitado;
                }

                if ($participante['participacion_tipo'] === 'retanquea') {
                    $totalCobertura += $aporteCobertura;
                }
            }

            // Actualizar totales del retanqueo
            $retanqueo->update([
                'monto_retanqueo' => $totalRetanqueo,
                'monto_usado_para_cubrir_antiguo' => $totalCobertura,
                'monto_desembolsar' => $totalRetanqueo - $totalCobertura,
                'saldo_restante_prestamo_antiguo' => $this->calcularSaldoRestante($prestamo, $totalCobertura)
            ]);

            Log::info('Solicitud de retanqueo creada', [
                'retanqueo_id' => $retanqueo->id,
                'prestamo_id' => $prestamoId,
                'total_participantes' => count($participantes)
            ]);

            return $retanqueo->fresh(['retanqueosIndividuales.cliente.persona']);
        });
    }

    /**
     * Calcula el aporte de cobertura individual
     */
    private function calcularAporteCoberturaIndividual($prestamo, $participantes)
    {
        $saldoPendienteTotal = $prestamo->cuotasGrupales()
            ->where('estado_pago', '!=', 'pagado')
            ->sum('saldo_pendiente');

        $integrantesQueRetanquean = collect($participantes)
            ->where('participacion_tipo', 'retanquea')
            ->count();

        if ($integrantesQueRetanquean <= 0 || $saldoPendienteTotal <= 0) {
            return 0;
        }

        return round($saldoPendienteTotal / $integrantesQueRetanquean, 2);
    }

    /**
     * Calcula el saldo restante del préstamo antiguo
     */
    private function calcularSaldoRestante($prestamo, $totalCobertura)
    {
        $saldoPendienteTotal = $prestamo->cuotasGrupales()
            ->where('estado_pago', '!=', 'pagado')
            ->sum('saldo_pendiente');

        return max(0, $saldoPendienteTotal - $totalCobertura);
    }

    /**
     * Aprueba una solicitud de retanqueo
     */
    public function aprobarRetanqueo($retanqueoId, $observaciones = '')
    {
        return DB::transaction(function () use ($retanqueoId, $observaciones) {
            $retanqueo = Retanqueo::with([
                'prestamoAntiguo.grupo.clientes.persona',
                'retanqueosIndividuales.cliente.persona'
            ])->find($retanqueoId);

            if (!$retanqueo) {
                throw new \Exception('Retanqueo no encontrado');
            }

            if (!$retanqueo->esSolicitudPendiente()) {
                throw new \Exception('Solo se pueden aprobar solicitudes pendientes');
            }

            // Actualizar estado
            $retanqueo->update([
                'estado_retanqueo' => 'aprobado',
                'fecha_aceptacion' => now()
            ]);

            // Actualizar retanqueos individuales
            $retanqueo->retanqueosIndividuales()->update([
                'estado_retanqueo_individual' => 'aceptado',
                'aceptacion_cliente' => 1
            ]);

            Log::info('Retanqueo aprobado', [
                'retanqueo_id' => $retanqueoId,
                'observaciones' => $observaciones
            ]);

            return $retanqueo->fresh();
        });
    }

    /**
     * Rechaza una solicitud de retanqueo
     */
    public function rechazarRetanqueo($retanqueoId, $motivo = '')
    {
        return DB::transaction(function () use ($retanqueoId, $motivo) {
            $retanqueo = Retanqueo::find($retanqueoId);

            if (!$retanqueo) {
                throw new \Exception('Retanqueo no encontrado');
            }

            if (!$retanqueo->esSolicitudPendiente()) {
                throw new \Exception('Solo se pueden rechazar solicitudes pendientes');
            }

            // Actualizar estado
            $retanqueo->update([
                'estado_retanqueo' => 'rechazado'
            ]);

            // Actualizar retanqueos individuales
            $retanqueo->retanqueosIndividuales()->update([
                'estado_retanqueo_individual' => 'rechazado'
            ]);

            Log::info('Retanqueo rechazado', [
                'retanqueo_id' => $retanqueoId,
                'motivo' => $motivo
            ]);

            return $retanqueo->fresh();
        });
    }

    /**
     * Ejecuta un retanqueo aprobado
     */
    public function ejecutarRetanqueo($retanqueoId)
    {
        return DB::transaction(function () use ($retanqueoId) {
            $retanqueo = Retanqueo::with([
                'prestamoAntiguo.grupo',
                'retanqueosIndividuales.cliente.persona'
            ])->find($retanqueoId);

            if (!$retanqueo) {
                throw new \Exception('Retanqueo no encontrado');
            }

            if (!$retanqueo->estaAprobado()) {
                throw new \Exception('Solo se pueden ejecutar retanqueos aprobados');
            }

            $prestamoAntiguo = $retanqueo->prestamoAntiguo;
            $grupo = $prestamoAntiguo->grupo;

            // 1. Crear nuevo préstamo
            $nuevoPrestamo = $this->crearNuevoPrestamo($retanqueo, $grupo);

            // 2. Crear préstamos individuales del nuevo préstamo
            $this->crearPrestamosIndividualesNuevos($retanqueo, $nuevoPrestamo);

            // 3. Generar cuotas grupales del nuevo préstamo
            $this->generarCuotasGrupalesNuevas($nuevoPrestamo);

            // 4. Actualizar préstamo antiguo
            $this->actualizarPrestamoAntiguo($retanqueo);

            // 5. Actualizar estado del retanqueo
            $retanqueo->update([
                'prestamo_nuevo_id' => $nuevoPrestamo->id,
                'estado_retanqueo' => 'ejecutado'
            ]);

            Log::info('Retanqueo ejecutado exitosamente', [
                'retanqueo_id' => $retanqueoId,
                'prestamo_antiguo_id' => $prestamoAntiguo->id,
                'prestamo_nuevo_id' => $nuevoPrestamo->id
            ]);

            return $retanqueo->fresh(['prestamoNuevo']);
        });
    }

    /**
     * Crea el nuevo préstamo para el retanqueo
     */
    private function crearNuevoPrestamo($retanqueo, $grupo)
    {
        $prestamoAntiguo = $retanqueo->prestamoAntiguo;
        
        // Contar cuántos retanqueos previos ha tenido este grupo
        $numeroRetanqueo = Retanqueo::whereHas('prestamoAntiguo', function($query) use ($grupo) {
            $query->where('grupo_id', $grupo->id);
        })->where('estado_retanqueo', 'ejecutado')->count() + 1;
        
        // Generar descripción identificativa del retanqueo
        $descripcionRetanqueo = "Retanqueo #{$numeroRetanqueo} - {$grupo->nombre_grupo}";
        
        return Prestamo::create([
            'grupo_id' => $grupo->id,
            'tasa_interes' => $prestamoAntiguo->tasa_interes ?? 17,
            'monto_prestado_total' => $retanqueo->monto_retanqueo,
            'monto_devolver' => 0, // Se calculará después
            'cantidad_cuotas' => $retanqueo->cantidad_cuotas_nuevo ?? 20,
            'fecha_prestamo' => now(),
            'frecuencia' => $prestamoAntiguo->frecuencia ?? 'semanal',
            'estado' => 'Aprobado',
            'calificacion' => 'A',
            'descripcion' => $descripcionRetanqueo,
            'es_retanqueo' => true,
            'prestamo_origen_id' => $prestamoAntiguo->id
        ]);
    }

    /**
     * Crea los préstamos individuales para el nuevo préstamo
     */
    private function crearPrestamosIndividualesNuevos($retanqueo, $nuevoPrestamo)
    {
        $participantes = $retanqueo->retanqueosIndividuales()
            ->whereIn('participacion_tipo', ['retanquea', 'nueva'])
            ->get();

        $montoTotalDevolver = 0;

        foreach ($participantes as $participante) {
            $cliente = $participante->cliente;
            $montoSolicitado = $participante->monto_solicitado;
            $tasaInteres = $nuevoPrestamo->tasa_interes ?? 17;
            $numCuotas = $nuevoPrestamo->cantidad_cuotas;

            // Calcular seguro según monto
            $seguro = $this->calcularSeguro($montoSolicitado);
            
            // Calcular interés y total a devolver
            $interes = $montoSolicitado * ($tasaInteres / 100);
            $montoDevolver = $montoSolicitado + $interes + $seguro;
            $cuotaIndividual = $montoDevolver / $numCuotas;

            PrestamoIndividual::create([
                'prestamo_id' => $nuevoPrestamo->id,
                'cliente_id' => $cliente->id,
                'monto_prestado_individual' => $montoSolicitado,
                'monto_cuota_prestamo_individual' => round($cuotaIndividual, 2),
                'monto_devolver_individual' => round($montoDevolver, 2),
                'seguro' => $seguro,
                'interes' => round($interes, 2),
                'estado' => 'Aprobado'
            ]);

            $montoTotalDevolver += $montoDevolver;

            // Actualizar el retanqueo individual con la cuota calculada
            $participante->update([
                'monto_cuota' => round($cuotaIndividual, 2)
            ]);
        }

        // Actualizar el monto total a devolver del nuevo préstamo
        $nuevoPrestamo->update([
            'monto_devolver' => round($montoTotalDevolver, 2)
        ]);
    }

    /**
     * Calcula el seguro según el monto
     */
    private function calcularSeguro($monto)
    {
        if ($monto <= 400) return 6;
        if ($monto <= 600) return 7;
        if ($monto <= 800) return 8;
        return 9;
    }

    /**
     * Genera las cuotas grupales para el nuevo préstamo
     */
    private function generarCuotasGrupalesNuevas($nuevoPrestamo)
    {
        $cuotaGrupal = $nuevoPrestamo->monto_devolver / $nuevoPrestamo->cantidad_cuotas;
        $fechaInicio = Carbon::parse($nuevoPrestamo->fecha_prestamo);

        for ($i = 1; $i <= $nuevoPrestamo->cantidad_cuotas; $i++) {
            $fechaVencimiento = $this->calcularFechaVencimiento($fechaInicio, $i, $nuevoPrestamo->frecuencia);

            CuotasGrupales::create([
                'prestamo_id' => $nuevoPrestamo->id,
                'numero_cuota' => $i,
                'monto_cuota_grupal' => round($cuotaGrupal, 2),
                'fecha_vencimiento' => $fechaVencimiento,
                'saldo_pendiente' => round($cuotaGrupal, 2),
                'estado_cuota_grupal' => 'vigente',
                'estado_pago' => 'pendiente'
            ]);
        }
    }

    /**
     * Calcula la fecha de vencimiento según la frecuencia
     */
    private function calcularFechaVencimiento($fechaInicio, $numeroCuota, $frecuencia)
    {
        switch ($frecuencia) {
            case 'semanal':
                return $fechaInicio->copy()->addWeeks($numeroCuota);
            case 'quincenal':
                return $fechaInicio->copy()->addWeeks($numeroCuota * 2);
            case 'mensual':
                return $fechaInicio->copy()->addMonths($numeroCuota);
            default:
                return $fechaInicio->copy()->addWeeks($numeroCuota);
        }
    }

    /**
     * Actualiza el préstamo antiguo después del retanqueo
     */
    private function actualizarPrestamoAntiguo($retanqueo)
    {
        $prestamoAntiguo = $retanqueo->prestamoAntiguo;
        $montoUsadoCobertura = $retanqueo->monto_usado_para_cubrir_antiguo;

        if ($montoUsadoCobertura <= 0) {
            return; // No hay nada que cubrir
        }

        // Obtener cuotas pendientes ordenadas por fecha
        $cuotasPendientes = $prestamoAntiguo->cuotasGrupales()
            ->where('estado_pago', '!=', 'pagado')
            ->where('saldo_pendiente', '>', 0)
            ->orderBy('numero_cuota')
            ->get();

        $montoRestante = $montoUsadoCobertura;

        foreach ($cuotasPendientes as $cuota) {
            if ($montoRestante <= 0) break;

            $saldoCuota = $cuota->saldo_pendiente;
            
            if ($montoRestante >= $saldoCuota) {
                // Cubrir completamente esta cuota
                $cuota->update([
                    'saldo_pendiente' => 0,
                    'estado_pago' => 'pagado',
                    'estado_cuota_grupal' => 'cancelada'
                ]);
                $montoRestante -= $saldoCuota;
            } else {
                // Cubrir parcialmente esta cuota
                $cuota->update([
                    'saldo_pendiente' => $saldoCuota - $montoRestante
                ]);
                $montoRestante = 0;
            }
        }

        // Verificar si todos los integrantes retanquearon
        $totalIntegrantes = $prestamoAntiguo->grupo->clientes()->count();
        $integrantesQueRetanquean = $retanqueo->retanqueosIndividuales()
            ->whereIn('participacion_tipo', ['retanquea', 'nueva'])
            ->count();

        // Verificar cuotas pendientes restantes
        $cuotasPendientesRestantes = $prestamoAntiguo->cuotasGrupales()
            ->where('estado_pago', '!=', 'pagado')
            ->where('saldo_pendiente', '>', 0)
            ->count();

        if ($cuotasPendientesRestantes === 0) {
            // Todas las cuotas están pagadas - finalizar préstamo
            $prestamoAntiguo->update(['estado' => 'Finalizado']);
            $retanqueo->update(['prestamo_antiguo_estado' => 1]);
            
            // Si todos retanquearon, no hay ex-integrantes por manejar
            // Si algunos no retanquearon, ya se cubrió todo con el retanqueo
        } else {
            // Hay cuotas pendientes
            if ($integrantesQueRetanquean === $totalIntegrantes) {
                // Todos retanquearon pero aún hay saldo - cambiar a finalizado de todos modos
                $prestamoAntiguo->update(['estado' => 'Finalizado']);
                $retanqueo->update(['prestamo_antiguo_estado' => 1]);
            } else {
                // Algunos no retanquearon - cambiar estado a parcialmente retanqueado
                $prestamoAntiguo->update(['estado' => 'Parcialmente_Retanqueado']);
                $retanqueo->update(['prestamo_antiguo_estado' => 0]);
            }
        }

        // Actualizar saldo restante en el retanqueo
        $saldoRestanteTotal = $prestamoAntiguo->cuotasGrupales()
            ->where('estado_pago', '!=', 'pagado')
            ->sum('saldo_pendiente');

        $retanqueo->update([
            'saldo_restante_prestamo_antiguo' => $saldoRestanteTotal
        ]);
    }
}
