<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Prestamo extends Model
{
    use HasFactory;

    protected $table = 'prestamos';

    protected $fillable = [
        'grupo_id',
        'tasa_interes',
        'monto_prestado_total',
        'monto_devolver',
        'cantidad_cuotas',
        'fecha_prestamo',
        'frecuencia',
        'estado',
        'calificacion',
        'descripcion',
        'es_retanqueo',
        'prestamo_origen_id',
    ];

    protected $casts = [
        'tasa_interes' => 'integer',
        'monto_prestado_total' => 'decimal:2',
        'monto_devolver' => 'decimal:2',
        'cantidad_cuotas' => 'integer',
        'fecha_prestamo' => 'date',
        'es_retanqueo' => 'boolean',
    ];

    // Relaciones
    public function grupo()
    {
        return $this->belongsTo(Grupo::class, 'grupo_id');
    }

    public function cuotasGrupales()
    {
        return $this->hasMany(CuotasGrupales::class, 'prestamo_id');
    }

    public function prestamoIndividual()
    {
        return $this->hasMany(PrestamoIndividual::class);
    }

    public function egresos()
    {
        return $this->hasMany(Egreso::class);
    }

    // Relaciones para retanqueos
    public function prestamoOrigen()
    {
        return $this->belongsTo(Prestamo::class, 'prestamo_origen_id');
    }

    public function prestamosRetanqueados()
    {
        return $this->hasMany(Prestamo::class, 'prestamo_origen_id');
    }

    public function retanqueoComoAntiguo()
    {
        return $this->hasOne(Retanqueo::class, 'prestamo_id');
    }

    public function retanqueoComoNuevo()
    {
        return $this->hasOne(Retanqueo::class, 'prestamo_nuevo_id');
    }


    public function scopeVisiblePorUsuario($query, $user)
    {
        if ($user->hasRole('Asesor')) {
            $asesor = \App\Models\Asesor::where('user_id', $user->id)->first();
            if ($asesor) {
                return $query->whereHas('grupo', function ($subQuery) use ($asesor) {
                    $subQuery->where('asesor_id', $asesor->id);
                });
            }
        } elseif ($user->hasAnyRole(['super_admin', 'Jefe de operaciones', 'Jefe de creditos'])) {
            return $query;
        }

        return $query->whereRaw('1 = 0');
    }

    public function estaFinalizado()
    {
        return $this->estado === 'Finalizado';
    }

    // Accessor para estado visible en tabla
    public function getEstadoVisibleAttribute()
    {
        if ($this->estado === 'Aprobado') {
            $total = $this->cuotasGrupales()->count();
            $pagadas = $this->cuotasGrupales()->where('estado_pago', 'pagado')->count();
            if ($pagadas > 0 && $pagadas < $total) {
                return 'Activo';
            }
        }

        return $this->estado;
    }

    // Accessor para asegurar que el monto total se calcule correctamente
    public function getMontoTotalCalculadoAttribute()
    {
        return $this->prestamoIndividual()->sum('monto_prestado_individual');
    }

    public function getMontoTotalDevolverCalculadoAttribute()
    {
        return $this->prestamoIndividual()->sum('monto_devolver_individual');
    }

    // Método para actualizar el estado automáticamente
    public function actualizarEstadoAutomaticamente()
    {
        if ($this->estado === 'Aprobado') {
            $total = $this->cuotasGrupales()->count();
            $pagadas = $this->cuotasGrupales()->where('estado_pago', 'pagado')->count();

            if ($total > 0 && $total === $pagadas) {
                // Verificar si hay retanqueos parciales donde algunas personas no retanquearon
                if ($this->tieneIntegrantesNoRetanqueadosConDeudaPendiente()) {
                    \Illuminate\Support\Facades\Log::info('No se puede finalizar automáticamente el préstamo: hay integrantes que no retanquearon con deuda pendiente', [
                        'prestamo_id' => $this->id,
                    ]);
                    return false;
                }
                
                $this->estado = 'Finalizado';
                $this->save();
                return true;
            }
        }
        return false;
    }

    /**
     * Método para verificar y actualizar el estado del préstamo basándose en el estado de las cuotas
     */
    public function verificarYActualizarEstado()
    {
        if ($this->estado === 'Aprobado' || $this->estado === 'Parcialmente_Retanqueado') {
            $totalCuotas = $this->cuotasGrupales()->count();
            $cuotasPagadas = $this->cuotasGrupales()->where('estado_pago', 'pagado')->count();
            
            \Illuminate\Support\Facades\Log::info('Verificando estado del préstamo', [
                'prestamo_id' => $this->id,
                'estado_actual' => $this->estado,
                'total_cuotas' => $totalCuotas,
                'cuotas_pagadas' => $cuotasPagadas,
            ]);
            
            if ($totalCuotas > 0 && $cuotasPagadas === $totalCuotas) {
                // Verificar si hay retanqueos parciales donde algunas personas no retanquearon
                if ($this->tieneIntegrantesNoRetanqueadosConDeudaPendiente()) {
                    \Illuminate\Support\Facades\Log::info('No se puede finalizar el préstamo: hay integrantes que no retanquearon con deuda pendiente', [
                        'prestamo_id' => $this->id,
                    ]);
                    return false;
                }
                
                $this->estado = 'Finalizado';
                $this->save();
                
                // Actualizar también los préstamos individuales
                $this->prestamoIndividual()->update(['estado' => 'Finalizado']);
                
                // Mover integrantes que no retanquearon a ex-integrantes
                $this->moverIntegrantesNoRetanqueadosAExIntegrantes();
                
                \Illuminate\Support\Facades\Log::info('Estado del préstamo actualizado a Finalizado', [
                    'prestamo_id' => $this->id,
                ]);
                
                return true;
            }
        }
        
        return false;
    }

    /**
     * Obtiene integrantes para contrato según el tipo de préstamo
     * Para préstamos originales: TODOS los integrantes históricos (inmutable)
     * Para retanqueos: Solo los que participaron en el retanqueo
     */
    public function getIntegrantesParaContrato()
    {
        if ($this->es_retanqueo) {
            // Para retanqueos: solo los que participaron
            return $this->prestamoIndividual()
                ->with('cliente.persona')
                ->where('monto_prestado_individual', '>', 0)
                ->get()
                ->map(function($prestamoIndividual) {
                    return [
                        'cliente' => $prestamoIndividual->cliente,
                        'persona' => $prestamoIndividual->cliente->persona,
                        'monto_prestado' => $prestamoIndividual->monto_prestado_individual,
                        'monto_devolver' => $prestamoIndividual->monto_devolver_individual,
                        'estado' => 'activo'
                    ];
                });
        } else {
            // Para préstamos originales: TODOS los integrantes históricos
            // Incluir tanto activos como ex-integrantes que estuvieron en el momento del préstamo
            $integrantesActivos = $this->grupo->clientes()->with('persona')->get();
            $exIntegrantes = $this->grupo->exIntegrantes()->with('persona')->get();
            
            $todosLosIntegrantes = $integrantesActivos->concat($exIntegrantes)->unique('id');
            
            return $todosLosIntegrantes->map(function($cliente) {
                $prestamoIndividual = $this->prestamoIndividual()
                    ->where('cliente_id', $cliente->id)
                    ->first();
                
                return [
                    'cliente' => $cliente,
                    'persona' => $cliente->persona,
                    'monto_prestado' => $prestamoIndividual ? $prestamoIndividual->monto_prestado_individual : 0,
                    'monto_devolver' => $prestamoIndividual ? $prestamoIndividual->monto_devolver_individual : 0,
                    'estado' => $this->grupo->clientes()->where('clientes.id', $cliente->id)->exists() ? 'activo' : 'ex_integrante'
                ];
            });
        }
    }

    /**
     * Método para generar descripción de retanqueo
     */
    public function generarDescripcionRetanqueo()
    {
        if (!$this->grupo) return $this->descripcion;
        
        // Contar cuántos retanqueos previos ha tenido este grupo
        $numeroRetanqueo = self::whereHas('grupo', function($query) {
            $query->where('id', $this->grupo_id);
        })->where('es_retanqueo', true)->count();
        
        return "RETANQUEO #{$numeroRetanqueo} {$this->grupo->nombre_grupo}";
    }

    /**
     * Mueve integrantes que no retanquearon a ex-integrantes cuando terminan de pagar
     */
    public function moverIntegrantesNoRetanqueadosAExIntegrantes()
    {
        // Buscar retanqueos relacionados con este préstamo
        $retanqueos = \App\Models\Retanqueo::where('prestamo_id', $this->id)
            ->where('estado_retanqueo', 'ejecutado')
            ->get();

        foreach ($retanqueos as $retanqueo) {
            // Obtener integrantes que NO retanquearon
            $integrantesNoRetanqueados = $retanqueo->retanqueosIndividuales()
                ->where('participacion_tipo', 'no_retanquea')
                ->with('cliente')
                ->get();

            foreach ($integrantesNoRetanqueados as $retanqueoIndividual) {
                $cliente = $retanqueoIndividual->cliente;
                if ($cliente && $this->grupo) {
                    // Mover a ex-integrante
                    $this->grupo->removerCliente($cliente->id, now());
                    
                    \Illuminate\Support\Facades\Log::info('Cliente movido a ex-integrante por completar pagos post-retanqueo', [
                        'cliente_id' => $cliente->id,
                        'grupo_id' => $this->grupo->id,
                        'prestamo_id' => $this->id
                    ]);
                }
            }
        }
    }

    /**
     * Verifica si este préstamo puede ser finalizado considerando retanqueos
     */
    public function puedeSerFinalizado()
    {
        $totalCuotas = $this->cuotasGrupales()->count();
        $cuotasPagadas = $this->cuotasGrupales()->where('estado_pago', 'pagado')->count();

        return $totalCuotas > 0 && $totalCuotas === $cuotasPagadas;
    }

    /**
     * Verifica si hay integrantes que no retanquearon y aún tienen deuda pendiente
     */
    public function tieneIntegrantesNoRetanqueadosConDeudaPendiente()
    {
        // Buscar retanqueos ejecutados relacionados con este préstamo
        $retanqueos = \App\Models\Retanqueo::where('prestamo_id', $this->id)
            ->where('estado_retanqueo', 'ejecutado')
            ->get();

        foreach ($retanqueos as $retanqueo) {
            // Obtener integrantes que NO retanquearon
            $integrantesNoRetanqueados = $retanqueo->retanqueosIndividuales()
                ->where('participacion_tipo', 'no_retanquea')
                ->with('cliente')
                ->get();

            foreach ($integrantesNoRetanqueados as $retanqueoIndividual) {
                $cliente = $retanqueoIndividual->cliente;
                if ($cliente && $this->grupo) {
                    // Verificar si el cliente aún está en el grupo (no ha sido movido a ex-integrante)
                    $sigueEnGrupo = $this->grupo->clientes()->where('clientes.id', $cliente->id)->exists();
                    
                    if ($sigueEnGrupo) {
                        // Verificar si tiene préstamos individuales activos (no finalizados) en este préstamo
                        $prestamoIndividual = $this->prestamoIndividual()
                            ->where('cliente_id', $cliente->id)
                            ->whereNotIn('estado', ['Finalizado', 'Completado'])
                            ->first();
                        
                        if ($prestamoIndividual) {
                            // También verificar que el monto a devolver sea mayor a 0
                            $montoDevolver = (float)$prestamoIndividual->monto_devolver_individual;
                            if ($montoDevolver > 0) {
                                \Illuminate\Support\Facades\Log::info('Integrante que no retanqueó aún tiene deuda pendiente', [
                                    'prestamo_id' => $this->id,
                                    'cliente_id' => $cliente->id,
                                    'prestamo_individual_id' => $prestamoIndividual->id,
                                    'estado_prestamo_individual' => $prestamoIndividual->estado,
                                    'monto_devolver' => $montoDevolver
                                ]);
                                return true;
                            }
                        }
                    }
                }
            }
        }

        return false;
    }    /**
     * Método para aprobar un préstamo
     */
    public function aprobar()
    {
        if (strtolower($this->estado) !== 'pendiente') {
            return;
        }

        $this->estado = 'Aprobado';
        $this->save();

        // Actualizar el estado del grupo asociado
        if ($this->grupo) {
            $this->grupo->estado_grupo = 'Activo';
            $this->grupo->save();
        }

        // Actualizar el estado de los préstamos individuales
        $this->prestamoIndividual()->update(['estado' => 'Aprobado']);
    }

    /**
     * Método para rechazar un préstamo
     */
    public function rechazar()
    {
        if (strtolower($this->estado) !== 'pendiente') {
            return;
        }

        $this->estado = 'Rechazado';
        $this->save();

        // Actualizar el estado del grupo asociado
        if ($this->grupo) {
            $this->grupo->estado_grupo = 'Activo';
            $this->grupo->save();
        }

        // Actualizar el estado de los préstamos individuales
        $this->prestamoIndividual()->update(['estado' => 'Rechazado']);
    }

    // Método para sincronizar los montos totales basándose en los préstamos individuales
    public function sincronizarMontosTotal()
    {
        if ($this->prestamoIndividual()->count() > 0) {
            $montoTotal = $this->prestamoIndividual()->sum('monto_prestado_individual');
            $montoDevolver = $this->prestamoIndividual()->sum('monto_devolver_individual');
            
            $this->updateQuietly([
                'monto_prestado_total' => round($montoTotal, 2),
                'monto_devolver' => round($montoDevolver, 2),
            ]);
            
            return $this->fresh();
        }
        
        return $this;
    }

    /**
     * Obtiene detalles del retanqueo para mostrar en pagos
     */
    public function getDetalleRetanqueoParaPago()
    {
        if ($this->estado !== 'Parcialmente_Retanqueado') {
            return null;
        }

        $retanqueo = $this->retanqueos()->where('estado_retanqueo', 'ejecutado')->first();
        if (!$retanqueo) {
            return null;
        }

        $clientesRetanqueados = $retanqueo->retanqueosIndividuales()
            ->where('participacion_tipo', 'retanquea')
            ->with('cliente.persona')
            ->get();

        $detalles = [];
        $totalCubierto = 0;

        foreach ($clientesRetanqueados as $individual) {
            $cliente = $individual->cliente;
            if ($cliente && $cliente->persona) {
                $montoCubierto = $individual->aporte_cobertura ?? 0;
                $detalles[] = [
                    'nombre' => $cliente->persona->nombre . ' ' . $cliente->persona->apellidos,
                    'monto_cubierto' => $montoCubierto
                ];
                $totalCubierto += $montoCubierto;
            }
        }

        return [
            'fecha_retanqueo' => $retanqueo->fecha_ejecucion,
            'clientes' => $detalles,
            'total_cubierto' => $totalCubierto
        ];
    }

    /**
     * Genera mensaje automático para observaciones de pago
     */
    public function generarMensajeRetanqueoPago()
    {
        $detalles = $this->getDetalleRetanqueoParaPago();
        if (!$detalles) {
            return '';
        }

        $fecha = \Carbon\Carbon::parse($detalles['fecha_retanqueo'])->format('d/m/Y');
        $mensaje = "Cobertura automática por retanqueo ({$fecha}):\n";
        
        foreach ($detalles['clientes'] as $cliente) {
            $mensaje .= "• {$cliente['nombre']}: S/ " . number_format($cliente['monto_cubierto'], 2) . " cubierto\n";
        }
        
        $mensaje .= "Total: S/ " . number_format($detalles['total_cubierto'], 2) . " cubierto automáticamente";
        
        return $mensaje;
    }
}
