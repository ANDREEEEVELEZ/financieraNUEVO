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
     * RESTRICCIÓN: Solo préstamos que tengan EXACTAMENTE 1 cuota pendiente
     */
    public function obtenerGruposElegibles($asesorId = null)
    {
        $query = Grupo::with(['prestamos.cuotasGrupales'])
            ->where('estado_grupo', 'Activo');

        if ($asesorId) {
            $query->where('asesor_id', $asesorId);
        }

        return $query->get()->filter(function ($grupo) {
            // Verificar que tenga préstamos activos con EXACTAMENTE 1 cuota pendiente
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

            // VALIDACIÓN CRÍTICA: Contar exactamente las cuotas pendientes
            $cuotasPendientes = $prestamoActivo->cuotasGrupales()
                ->where('estado_pago', '!=', 'pagado')
                ->where('saldo_pendiente', '>', 0)
                ->count();

            // Solo permitir retanqueo si queda EXACTAMENTE 1 cuota pendiente
            return $cuotasPendientes === 1;
        });
    }

    /**
     * Calcula el estado actual de un préstamo para retanqueo
     * VALIDACIÓN CRÍTICA: Verificar que solo quede 1 cuota pendiente
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
        
        // VALIDACIÓN CRÍTICA FINANCIERA: Solo permitir retanqueo con EXACTAMENTE 1 cuota pendiente
        if ($cuotasPendientes !== 1) {
            throw new \Exception("Error: Este préstamo no es elegible para retanqueo. Tiene {$cuotasPendientes} cuotas pendientes, pero solo se permiten retanqueos cuando queda EXACTAMENTE 1 cuota por pagar.");
        }
        
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
                'cantidad_cuotas_nuevo' => $datosRetanqueo['cantidad_cuotas'] ?? 4, // Fijo en 4 cuotas como préstamos regulares
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
                    $aporteCobertura = $this->calcularAporteCoberturaIndividual($prestamo, $participantes, $participante['cliente_id']);
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

            // NUEVO: Si hay datos de cuenta, crear préstamo pendiente inmediatamente
            if (!empty($datosRetanqueo['datos_cuenta'])) {
                $nuevoPrestamo = $this->crearNuevoPrestamoPendiente($retanqueo, $prestamo->grupo, $datosRetanqueo['datos_cuenta']);
                
                // Actualizar retanqueo con el ID del nuevo préstamo
                $retanqueo->update(['prestamo_nuevo_id' => $nuevoPrestamo->id]);
                
                Log::info('Préstamo pendiente creado automáticamente con la solicitud', [
                    'retanqueo_id' => $retanqueo->id,
                    'prestamo_nuevo_id' => $nuevoPrestamo->id
                ]);
            }

            Log::info('Solicitud de retanqueo creada', [
                'retanqueo_id' => $retanqueo->id,
                'prestamo_id' => $prestamoId,
                'total_participantes' => count($participantes),
                'prestamo_pendiente_creado' => !empty($datosRetanqueo['datos_cuenta'])
            ]);

            return $retanqueo->fresh(['retanqueosIndividuales.cliente.persona']);
        });
    }

    /**
     * Calcula el aporte de cobertura individual basado en la cuota individual original del cliente
     */
    private function calcularAporteCoberturaIndividual($prestamo, $participantes, $clienteId = null)
    {
        // Si se proporciona un cliente específico, calcular su aporte individual
        if ($clienteId) {
            $prestamoIndividual = $prestamo->prestamoIndividual()
                ->where('cliente_id', $clienteId)
                ->first();
                
            if (!$prestamoIndividual) {
                return 0;
            }
            
            // Calcular cuántas cuotas pendientes tiene el préstamo
            $cuotasPendientes = $prestamo->cuotasGrupales()
                ->where('estado_pago', '!=', 'pagado')
                ->count();
                
            // El aporte es la cuota individual original multiplicada por las cuotas pendientes
            $aporteCobertura = round($prestamoIndividual->monto_cuota_prestamo_individual * $cuotasPendientes, 2);
            
            Log::info('RetanqueoService: Calculando aporte de cobertura individual', [
                'cliente_id' => $clienteId,
                'cuota_individual_original' => $prestamoIndividual->monto_cuota_prestamo_individual,
                'cuotas_pendientes' => $cuotasPendientes,
                'aporte_cobertura' => $aporteCobertura
            ]);
            
            return $aporteCobertura;
        }
        
        return 0;
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
    public function ejecutarRetanqueo($retanqueoId, $datosCuenta = [])
    {
        return DB::transaction(function () use ($retanqueoId, $datosCuenta) {
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
            
            // VALIDACIÓN CRÍTICA FINANCIERA: Verificar que el préstamo antiguo tenga EXACTAMENTE 1 cuota pendiente
            $cuotasPendientes = $prestamoAntiguo->cuotasGrupales()
                ->where('estado_pago', '!=', 'pagado')
                ->where('saldo_pendiente', '>', 0)
                ->count();
                
            if ($cuotasPendientes !== 1) {
                throw new \Exception("EJECUTIÓN BLOQUEADA: El préstamo {$prestamoAntiguo->id} tiene {$cuotasPendientes} cuotas pendientes. Solo se permiten retanqueos cuando queda EXACTAMENTE 1 cuota por pagar. Operación cancelada por seguridad financiera.");
            }
            
            $grupo = $prestamoAntiguo->grupo;

            // SEGURO: Validar y limpiar datos de cuenta con reglas estrictas
            $datosCuentaLimpios = [];
            if (is_array($datosCuenta)) {
                // Validar titular: solo letras, espacios y acentos, mínimo 3 caracteres
                if (!empty($datosCuenta['titular_cuenta_desembolso']) && 
                    is_string($datosCuenta['titular_cuenta_desembolso'])) {
                    $titular = trim($datosCuenta['titular_cuenta_desembolso']);
                    if (strlen($titular) >= 3 && preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/', $titular)) {
                        $datosCuentaLimpios['titular_cuenta_desembolso'] = $titular;
                    }
                }
                
                // Validar número de cuenta: exactamente 14 dígitos numéricos
                if (!empty($datosCuenta['numero_cuenta_desembolso']) && 
                    is_string($datosCuenta['numero_cuenta_desembolso'])) {
                    $numeroCuenta = trim($datosCuenta['numero_cuenta_desembolso']);
                    if (preg_match('/^[0-9]{14}$/', $numeroCuenta)) {
                        $datosCuentaLimpios['numero_cuenta_desembolso'] = $numeroCuenta;
                    }
                }
            }

            // NUEVO FLUJO: Verificar si ya existe un préstamo pendiente
            if ($retanqueo->prestamo_nuevo_id) {
                $nuevoPrestamo = Prestamo::find($retanqueo->prestamo_nuevo_id);
                
                if ($nuevoPrestamo && $nuevoPrestamo->estado === 'Pendiente') {
                    // El préstamo ya existe en estado Pendiente, solo cambiar estado
                    $nuevoPrestamo->update(['estado' => 'Aprobado']);
                    
                    // Actualizar préstamos individuales a Aprobado
                    $nuevoPrestamo->prestamoIndividual()->update(['estado' => 'Aprobado']);
                    
                    Log::info('Préstamo existente activado (Pendiente -> Aprobado)', [
                        'prestamo_id' => $nuevoPrestamo->id,
                        'retanqueo_id' => $retanqueoId
                    ]);
                } else {
                    throw new \Exception('El préstamo asociado al retanqueo no existe o no está en estado Pendiente');
                }
            } else {
                // FLUJO ORIGINAL: Crear nuevo préstamo (con datos de cuenta validados)
                $nuevoPrestamo = $this->crearNuevoPrestamo($retanqueo, $grupo, $datosCuentaLimpios);

                // Crear préstamos individuales del nuevo préstamo
                $this->crearPrestamosIndividualesNuevos($retanqueo, $nuevoPrestamo);
                
                // Actualizar retanqueo con el ID del nuevo préstamo
                $retanqueo->update(['prestamo_nuevo_id' => $nuevoPrestamo->id]);
            }

            // Generar cuotas grupales del nuevo préstamo
            $this->generarCuotasGrupalesNuevas($nuevoPrestamo);

            // Gestionar cambios de membresía del grupo
            $this->gestionarCambiosMembresiaGrupo($retanqueo, $grupo);

            // Actualizar préstamo antiguo
            $this->actualizarPrestamoAntiguo($retanqueo);

            // Actualizar estado del retanqueo
            $retanqueo->update([
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
    private function crearNuevoPrestamo($retanqueo, $grupo, $datosCuenta = [])
    {
        $prestamoAntiguo = $retanqueo->prestamoAntiguo;
        
        // Contar cuántos retanqueos previos ha tenido este grupo
        $numeroRetanqueo = Retanqueo::whereHas('prestamoAntiguo', function($query) use ($grupo) {
            $query->where('grupo_id', $grupo->id);
        })->where('estado_retanqueo', 'ejecutado')->count() + 1;
        
        // Generar descripción identificativa del retanqueo - SIEMPRE con prefijo RETANQUEO
        $descripcionRetanqueo = "RETANQUEO #{$numeroRetanqueo} {$grupo->nombre_grupo}";
        
        // Datos base del préstamo (lógica crítica sin modificar)
        $nuevoPrestamoData = [
            'grupo_id' => $grupo->id,
            'tasa_interes' => $prestamoAntiguo->tasa_interes ?? 17,
            'monto_prestado_total' => $retanqueo->monto_retanqueo,
            'monto_devolver' => 0, // Se calculará después
            'cantidad_cuotas' => $retanqueo->cantidad_cuotas_nuevo ?? 4, // Fijo en 4 cuotas como préstamos regulares
            'fecha_prestamo' => now(),
            'frecuencia' => $prestamoAntiguo->frecuencia ?? 'semanal',
            'estado' => 'Aprobado',
            // 'calificacion' => 'A',
            'descripcion' => $descripcionRetanqueo,
            'es_retanqueo' => true,
            'prestamo_origen_id' => $prestamoAntiguo->id
        ];

        // SEGURO: Agregar datos de cuenta solo si están presentes y válidos
        if (is_array($datosCuenta)) {
            // Validar titular: solo letras, espacios y acentos
            if (!empty($datosCuenta['titular_cuenta_desembolso']) && 
                is_string($datosCuenta['titular_cuenta_desembolso'])) {
                $titular = trim($datosCuenta['titular_cuenta_desembolso']);
                if (strlen($titular) >= 3 && preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/', $titular)) {
                    $nuevoPrestamoData['titular_cuenta_desembolso'] = $titular;
                }
            }
            
            // Validar número de cuenta: exactamente 14 dígitos
            if (!empty($datosCuenta['numero_cuenta_desembolso']) && 
                is_string($datosCuenta['numero_cuenta_desembolso'])) {
                $numeroCuenta = trim($datosCuenta['numero_cuenta_desembolso']);
                if (preg_match('/^[0-9]{14}$/', $numeroCuenta)) {
                    $nuevoPrestamoData['numero_cuenta_desembolso'] = $numeroCuenta;
                }
            }
        }

        return Prestamo::create($nuevoPrestamoData);
    }

    /**
     * Crea el nuevo préstamo en estado PENDIENTE para la solicitud de retanqueo
     */
    private function crearNuevoPrestamoPendiente($retanqueo, $grupo, $datosCuenta = [])
    {
        $prestamoAntiguo = $retanqueo->prestamoAntiguo;
        
        // Contar cuántos retanqueos previos ha tenido este grupo
        $numeroRetanqueo = Retanqueo::whereHas('prestamoAntiguo', function($query) use ($grupo) {
            $query->where('grupo_id', $grupo->id);
        })->where('estado_retanqueo', 'ejecutado')->count() + 1;
        
        // Generar descripción identificativa del retanqueo - SIEMPRE con prefijo RETANQUEO
        $descripcionRetanqueo = "RETANQUEO #{$numeroRetanqueo} {$grupo->nombre_grupo}";
        
        // Datos base del préstamo (similar a crearNuevoPrestamo pero en estado PENDIENTE)
        $nuevoPrestamoData = [
            'grupo_id' => $grupo->id,
            'tasa_interes' => $prestamoAntiguo->tasa_interes ?? 17,
            'monto_prestado_total' => $retanqueo->monto_retanqueo,
            'monto_devolver' => 0, // Se calculará después
            'cantidad_cuotas' => $retanqueo->cantidad_cuotas_nuevo ?? 4, // Fijo en 4 cuotas como préstamos regulares
            'fecha_prestamo' => now(),
            'frecuencia' => $prestamoAntiguo->frecuencia ?? 'semanal',
            'estado' => 'Pendiente', // DIFERENCIA CLAVE: Estado Pendiente en lugar de Aprobado
            // 'calificacion' => 'A',
            'descripcion' => $descripcionRetanqueo,
            'es_retanqueo' => true,
            'prestamo_origen_id' => $prestamoAntiguo->id
        ];

        // SEGURO: Agregar datos de cuenta solo si están presentes y válidos
        if (is_array($datosCuenta)) {
            // Validar titular: solo letras, espacios y acentos
            if (!empty($datosCuenta['titular_cuenta_desembolso']) && 
                is_string($datosCuenta['titular_cuenta_desembolso'])) {
                $titular = trim($datosCuenta['titular_cuenta_desembolso']);
                if (strlen($titular) >= 3 && preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/', $titular)) {
                    $nuevoPrestamoData['titular_cuenta_desembolso'] = $titular;
                }
            }
            
            // Validar número de cuenta: exactamente 14 dígitos
            if (!empty($datosCuenta['numero_cuenta_desembolso']) && 
                is_string($datosCuenta['numero_cuenta_desembolso'])) {
                $numeroCuenta = trim($datosCuenta['numero_cuenta_desembolso']);
                if (preg_match('/^[0-9]{14}$/', $numeroCuenta)) {
                    $nuevoPrestamoData['numero_cuenta_desembolso'] = $numeroCuenta;
                }
            }
        }

        $nuevoPrestamo = Prestamo::create($nuevoPrestamoData);
        
        // Crear los préstamos individuales en estado Pendiente
        $this->crearPrestamosIndividualesPendientes($retanqueo, $nuevoPrestamo);
        
        return $nuevoPrestamo;
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

        // Actualizar monto a devolver del préstamo
        $nuevoPrestamo->update([
            'monto_devolver' => round($montoTotalDevolver, 2)
        ]);
    }

    /**
     * Crea los préstamos individuales en estado PENDIENTE para la solicitud
     */
    private function crearPrestamosIndividualesPendientes($retanqueo, $nuevoPrestamo)
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
                'estado' => 'Pendiente' // DIFERENCIA CLAVE: Estado Pendiente en lugar de Aprobado
            ]);

            $montoTotalDevolver += $montoDevolver;

            // Actualizar el retanqueo individual con la cuota calculada
            $participante->update([
                'monto_cuota' => round($cuotaIndividual, 2)
            ]);
        }

        // Actualizar monto a devolver del préstamo
        $nuevoPrestamo->update([
            'monto_devolver' => round($montoTotalDevolver, 2)
        ]);
    }

    /**
     * Calcula el seguro según el monto exacto y ciclo
     * Basado en tabla oficial de montos y ciclos
     */
    private function calcularSeguro($monto)
    {
        // Convertir a entero para comparación exacta
        $montoInt = (int) $monto;
        
        // Ciclo I
        if ($montoInt === 400) {
            return 7;
        }
        
        // Ciclo II  
        if ($montoInt === 500 || $montoInt === 600) {
            return 8;
        }
        
        // Ciclo III
        if ($montoInt === 700 || $montoInt === 800) {
            return 9;
        }
        
        // Ciclo IV
        if ($montoInt === 900 || $montoInt === 1000) {
            return 10;
        }
        
        // Fallback seguro - no debería llegar aquí
        throw new \InvalidArgumentException("Monto no válido para cálculo de seguro: {$monto}");
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
     * Actualiza el préstamo antiguo después del retanqueo - COBERTURA POR CUOTAS INDIVIDUALES ORIGINALES
     */
    private function actualizarPrestamoAntiguo($retanqueo)
    {
        $prestamoAntiguo = $retanqueo->prestamoAntiguo;
        $montoUsadoCobertura = $retanqueo->monto_usado_para_cubrir_antiguo;

        if ($montoUsadoCobertura <= 0) {
            return; // No hay nada que cubrir
        }

        // Obtener información del retanqueo
        $totalIntegrantes = $prestamoAntiguo->grupo->clientes()->count();
        $clientesQueRetanquean = $retanqueo->retanqueosIndividuales()
            ->whereIn('participacion_tipo', ['retanquea', 'nueva'])
            ->with('cliente')
            ->get();
        
        if ($clientesQueRetanquean->count() <= 0 || $totalIntegrantes <= 0) {
            return; // No hay información válida
        }

        // Obtener cuotas pendientes ordenadas por fecha
        $cuotasPendientes = $prestamoAntiguo->cuotasGrupales()
            ->where('estado_pago', '!=', 'pagado')
            ->where('saldo_pendiente', '>', 0)
            ->orderBy('numero_cuota')
            ->get();

        // LÓGICA CORREGIDA: Cada persona que retanquea cubre SOLO su propia cuota individual
        // Las personas que NO retanquean mantienen su deuda individual pendiente
        foreach ($cuotasPendientes as $cuota) {
            $saldoActualCuota = $cuota->saldo_pendiente;
            $montoTotalACubrir = 0;
            
            // Calcular cuánto cubre cada cliente que retanquea - SOLO su propia parte individual
            foreach ($clientesQueRetanquean as $retanqueoIndividual) {
                $prestamoIndividual = $prestamoAntiguo->prestamoIndividual()
                    ->where('cliente_id', $retanqueoIndividual->cliente_id)
                    ->first();
                    
                if ($prestamoIndividual) {
                    // Cada persona que retanquea cubre SOLO su cuota individual, no más
                    $montoTotalACubrir += $prestamoIndividual->monto_cuota_prestamo_individual;
                }
            }
            
            // Aplicar la cobertura - pero mantener saldo pendiente para quienes NO retanquearon
            $nuevoSaldoPendiente = round($saldoActualCuota - $montoTotalACubrir, 2);
            
            // Actualizar la cuota con el nuevo saldo
            $cuota->update([
                'saldo_pendiente' => max(0, $nuevoSaldoPendiente)
            ]);
            
            // CRÍTICO: NO marcar como 'pagado' si hay integrantes que no retanquearon
            // Solo marcar como pagada si TODOS retanquearon O si el saldo realmente es 0
            $hayIntegrantesQueNoRetanquearon = $clientesQueRetanquean->count() < $totalIntegrantes;
            
            if ($nuevoSaldoPendiente <= 0 && !$hayIntegrantesQueNoRetanquearon) {
                // Solo si TODOS retanquearon y el saldo es 0, marcar como pagada
                $cuota->update([
                    'estado_pago' => 'pagado',
                    'estado_cuota_grupal' => 'cancelada'
                ]);
                
                Log::info('RetanqueoService: Cuota marcada como pagada - todos retanquearon', [
                    'cuota_id' => $cuota->id,
                    'numero_cuota' => $cuota->numero_cuota
                ]);
            } else {
                // Si hay integrantes que no retanquearon, mantener la cuota activa
                // para que puedan seguir pagando su parte individual
                Log::info('RetanqueoService: Cuota parcialmente cubierta - pendiente para quien no retanqueó', [
                    'cuota_id' => $cuota->id,
                    'numero_cuota' => $cuota->numero_cuota,
                    'saldo_restante' => $nuevoSaldoPendiente,
                    'integrantes_no_retanquearon' => $totalIntegrantes - $clientesQueRetanquean->count(),
                    'razon' => 'Algunos integrantes no retanquearon y deben pagar su cuota individual'
                ]);
            }
            
            Log::info('RetanqueoService: Cobertura individual aplicada correctamente', [
                'cuota_id' => $cuota->id,
                'numero_cuota' => $cuota->numero_cuota,
                'saldo_original' => $saldoActualCuota,
                'monto_cubierto' => $montoTotalACubrir,
                'nuevo_saldo_pendiente' => $nuevoSaldoPendiente,
                'clientes_que_retanquean' => $clientesQueRetanquean->count(),
                'total_integrantes' => $totalIntegrantes,
                'hay_pendientes' => $hayIntegrantesQueNoRetanquearon
            ]);
        }

        // Determinar estado del préstamo - LÓGICA CORREGIDA
        // Verificar si hay integrantes que NO retanquearon
        $integrantesNoRetanqueados = $retanqueo->retanqueosIndividuales()
            ->where('participacion_tipo', 'no_retanquea')
            ->count();
        
        if ($integrantesNoRetanqueados > 0) {
            // HAY integrantes que NO retanquearon - SIEMPRE marcar como Parcialmente_Retanqueado
            $prestamoAntiguo->update(['estado' => 'Parcialmente_Retanqueado']);
            $retanqueo->update(['prestamo_antiguo_estado' => 0]);
            
            Log::info('RetanqueoService: Préstamo marcado como Parcialmente_Retanqueado', [
                'prestamo_id' => $prestamoAntiguo->id,
                'integrantes_no_retanqueados' => $integrantesNoRetanqueados,
                'razon' => 'Hay integrantes que no retanquearon y deben seguir pagando'
            ]);
        } else {
            // TODOS retanquearon - finalizar inmediatamente
            $prestamoAntiguo->update(['estado' => 'Finalizado']);
            $retanqueo->update(['prestamo_antiguo_estado' => 1]);
            
            Log::info('RetanqueoService: Préstamo marcado como Finalizado', [
                'prestamo_id' => $prestamoAntiguo->id,
                'razon' => 'Todos los integrantes retanquearon'
            ]);
        }

        // Actualizar saldo restante en el retanqueo
        $saldoRestanteTotal = $prestamoAntiguo->cuotasGrupales()
            ->where('estado_pago', '!=', 'pagado')
            ->sum('saldo_pendiente');

        $retanqueo->update([
            'saldo_restante_prestamo_antiguo' => $saldoRestanteTotal
        ]);
    }

    /**
     * Gestiona los cambios de membresía del grupo durante el retanqueo
     * - Marca como ex-integrantes a quienes no retanquean
     * - Agrega como nuevos integrantes a los clientes nuevos
     */
    private function gestionarCambiosMembresiaGrupo($retanqueo, $grupo)
    {
        $fecha = now()->toDateString();
        $retanqueosIndividuales = $retanqueo->retanqueosIndividuales;

        Log::info('Gestionando cambios de membresía del grupo', [
            'retanqueo_id' => $retanqueo->id,
            'grupo_id' => $grupo->id,
            'participantes_count' => $retanqueosIndividuales->count()
        ]);

        foreach ($retanqueosIndividuales as $retanqueoIndividual) {
            $clienteId = $retanqueoIndividual->cliente_id;
            $participacionTipo = $retanqueoIndividual->participacion_tipo;
            
            // Verificar si el cliente ya está en el grupo
            $esMiembroActual = $grupo->clientes()->where('clientes.id', $clienteId)->exists();

            if ($participacionTipo === 'no_retanquea' && $esMiembroActual) {
                // CASO 1: Cliente actual que no retanquea -> Marcar como ex-integrante
                $grupo->clientes()->updateExistingPivot($clienteId, [
                    'fecha_salida' => $fecha
                ]);

                Log::info('Cliente marcado como ex-integrante', [
                    'cliente_id' => $clienteId,
                    'grupo_id' => $grupo->id,
                    'fecha_salida' => $fecha
                ]);

            } elseif ($participacionTipo === 'nueva' && !$esMiembroActual) {
                // CASO 2: Cliente nuevo que retanquea -> Agregar como nuevo integrante
                $grupo->clientes()->attach($clienteId, [
                    'fecha_ingreso' => $fecha,
                    'fecha_salida' => null,
                    'estado_grupo_cliente' => 'activo'
                ]);

                // Actualizar ciclo del cliente a I si es su primer préstamo
                $cliente = \App\Models\Cliente::find($clienteId);
                if ($cliente && !$cliente->ciclo) {
                    $cliente->update(['ciclo' => 'I']);
                }

                Log::info('Cliente agregado como nuevo integrante', [
                    'cliente_id' => $clienteId,
                    'grupo_id' => $grupo->id,
                    'fecha_ingreso' => $fecha
                ]);
            }
        }

        // Actualizar contador de integrantes del grupo
        $integrantesActivos = $grupo->clientes()->count();
        $grupo->update(['numero_integrantes' => $integrantesActivos]);

        Log::info('Cambios de membresía completados', [
            'grupo_id' => $grupo->id,
            'nuevos_integrantes_activos' => $integrantesActivos
        ]);
    }
}
