<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Prestamo extends Model
{
    use HasFactory;

    protected $table = 'prestamos';

    // ─── 10 estados funcionales del préstamo ──────────────────────────
    public const ESTADO_PENDIENTE    = 'Pendiente';
    public const ESTADO_APROBADO     = 'Aprobado';
    public const ESTADO_FIRMADO      = 'Firmado';
    public const ESTADO_ACTIVO       = 'Activo';
    public const ESTADO_AL_DIA       = 'Al_Día';
    public const ESTADO_EN_MORA      = 'En_Mora';
    public const ESTADO_RECHAZADO    = 'Rechazado';
    public const ESTADO_REFORMULADO  = 'Reformulado';
    public const ESTADO_FINALIZADO   = 'Finalizado';
    public const ESTADO_CANCELADO    = 'Cancelado';

    /**
     * Lista de todos los estados para validaciones y selects.
     */
    public const ESTADOS = [
        self::ESTADO_PENDIENTE,
        self::ESTADO_APROBADO,
        self::ESTADO_FIRMADO,
        self::ESTADO_ACTIVO,
        self::ESTADO_AL_DIA,
        self::ESTADO_EN_MORA,
        self::ESTADO_RECHAZADO,
        self::ESTADO_REFORMULADO,
        self::ESTADO_FINALIZADO,
        self::ESTADO_CANCELADO,
    ];

    /**
     * Colores de badge por estado para la UI (Filament).
     */
    public const ESTADO_COLORES = [
        self::ESTADO_PENDIENTE   => 'gray',
        self::ESTADO_APROBADO    => 'warning',
        self::ESTADO_FIRMADO     => 'info',
        self::ESTADO_ACTIVO      => 'primary',
        self::ESTADO_AL_DIA      => 'success',
        self::ESTADO_EN_MORA     => 'danger',
        self::ESTADO_RECHAZADO   => 'danger',
        self::ESTADO_REFORMULADO => 'warning',
        self::ESTADO_FINALIZADO  => 'success',
        self::ESTADO_CANCELADO   => 'gray',
    ];

    /**
     * Estados que se consideran "activos" (préstamo en curso).
     */
    public const ESTADOS_ACTIVOS = [
        self::ESTADO_ACTIVO,
        self::ESTADO_AL_DIA,
        self::ESTADO_EN_MORA,
    ];

    protected $fillable = [
        'grupo_id',
        'tasa_interes',
        'monto_prestado_total',
        'monto_devolver',
        'cantidad_cuotas',
        'fecha_prestamo',
        'fecha_desembolso',
        'frecuencia',
        'estado',
        'calificacion',
        'descripcion',
        'es_retanqueo',
        'prestamo_origen_id',
        'titular_cuenta_desembolso',
        'numero_cuenta_desembolso',
    ];

    protected $casts = [
        'tasa_interes' => 'integer',
        'monto_prestado_total' => 'decimal:2',
        'monto_devolver' => 'decimal:2',
        'cantidad_cuotas' => 'integer',
        'fecha_prestamo' => 'date',
        'fecha_desembolso' => 'date',
        'es_retanqueo' => 'boolean',
        'es_parcialmente_retanqueado' => 'boolean',
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

    // Nuevas relaciones del modelo ERP
    public function producto()
    {
        return $this->belongsTo(ProductoFinanciero::class, 'producto_id');
    }

    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function cuotasIndividuales()
    {
        return $this->hasMany(CuotaIndividual::class, 'prestamo_id');
    }

    public function movimientos()
    {
        return $this->morphMany(MovimientoFinanciero::class, 'referencia');
    }

    /**
     * Determina si el préstamo es grupal o individual.
     */
    public function esGrupal(): bool
    {
        return !is_null($this->grupo_id);
    }

    public function esIndividual(): bool
    {
        return is_null($this->grupo_id) && !is_null($this->cliente_id);
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

    /**
     * Accessor: estado legible para la UI.
     * Si el préstamo es parcialmente retanqueado, lo refleja.
     */
    public function getEstadoVisibleAttribute(): string
    {
        $estado = str_replace('_', ' ', $this->estado);

        if ($this->es_parcialmente_retanqueado) {
            return $estado . ' (Retanqueo Parcial)';
        }

        return $estado;
    }

    /**
     * Accessor: color de badge para Filament.
     */
    public function getEstadoBadgeColorAttribute(): string
    {
        return self::ESTADO_COLORES[$this->estado] ?? 'gray';
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
        if (in_array($this->estado, [self::ESTADO_ACTIVO, self::ESTADO_AL_DIA, self::ESTADO_EN_MORA])) {
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
     * Para préstamos originales: Solo los que participaron efectivamente en el préstamo
     * Para retanqueos: Solo los que participaron en el retanqueo
     */
    public function getIntegrantesParaContrato()
    {
        // Tanto para préstamos originales como retanqueos:
        // Solo incluir integrantes que tienen registros PrestamoIndividual con monto > 0
        // Esto garantiza consistencia entre formulario → base de datos → contrato
        return $this->prestamoIndividual()
            ->with('cliente.persona')
            ->where('monto_prestado_individual', '>', 0)
            ->get()
            ->map(function ($prestamoIndividual) {
                return [
                    'cliente' => $prestamoIndividual->cliente,
                    'persona' => $prestamoIndividual->cliente->persona,
                    'monto_prestado' => $prestamoIndividual->monto_prestado_individual,
                    'monto_devolver' => $prestamoIndividual->monto_devolver_individual,
                    'estado' => 'participante'
                ];
            });
    }

    /**
     * Método para generar descripción de retanqueo
     */
    public function generarDescripcionRetanqueo()
    {
        if (!$this->grupo)
            return $this->descripcion;

        // Contar cuántos retanqueos previos ha tenido este grupo
        $numeroRetanqueo = self::whereHas('grupo', function ($query) {
            $query->where('id', $this->grupo_id);
        })->where('es_retanqueo', true)->count();

        return "RETANQUEO #{$numeroRetanqueo} {$this->grupo->nombre_grupo}";
    }

    /**
     * Mueve integrantes que no retanquearon a ex-integrantes cuando terminan de pagar
     */
    public function moverIntegrantesNoRetanqueadosAExIntegrantes()
    {
        // Buscar retanqueo ejecutado relacionado con este préstamo
        $retanqueo = $this->retanqueoComoAntiguo()->where('estado_retanqueo', 'ejecutado')->first();

        if (!$retanqueo) {
            return;
        }

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
        // Obtener el retanqueo ejecutado (si existe)
        $retanqueo = $this->retanqueoComoAntiguo()->where('estado_retanqueo', 'ejecutado')->first();

        if (!$retanqueo) {
            // Si no hay retanqueo, no hay integrantes que no retanquearon
            return false;
        }

        // Obtener integrantes que NO retanquearon
        $integrantesNoRetanqueados = $retanqueo->retanqueosIndividuales()
            ->where('participacion_tipo', 'no_retanquea')
            ->with('cliente')
            ->get();

        if ($integrantesNoRetanqueados->isEmpty()) {
            // Si no hay integrantes que no retanquearon, no hay deuda pendiente individual
            return false;
        }

        \Illuminate\Support\Facades\Log::info('Verificando integrantes que no retanquearon', [
            'prestamo_id' => $this->id,
            'estado' => $this->estado,
            'total_no_retanqueados' => $integrantesNoRetanqueados->count()
        ]);

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
                        $montoDevolver = (float) $prestamoIndividual->monto_devolver_individual;
                        if ($montoDevolver > 0) {
                            \Illuminate\Support\Facades\Log::info('Integrante que no retanqueó aún tiene deuda pendiente', [
                                'prestamo_id' => $this->id,
                                'cliente_id' => $cliente->id,
                                'prestamo_individual_id' => $prestamoIndividual->id,
                                'estado_prestamo_individual' => $prestamoIndividual->estado,
                                'monto_devolver' => $montoDevolver,
                                'sigue_en_grupo' => $sigueEnGrupo
                            ]);
                            return true;
                        }
                    }
                }
            }
        }

        // Verificación adicional para préstamos parcialmente retanqueados:
        // También verificar si hay cuotas grupales con saldo pendiente que correspondan
        // a la parte no cubierta por quienes no retanquearon
        if ($this->es_parcialmente_retanqueado) {
            $cuotasConSaldoPendiente = $this->cuotasGrupales()
                ->where('estado_pago', '!=', 'pagado')
                ->where('saldo_pendiente', '>', 0)
                ->count();

            Log::info('Verificación adicional de cuotas grupales pendientes', [
                'prestamo_id' => $this->id,
                'cuotas_con_saldo_pendiente' => $cuotasConSaldoPendiente
            ]);

            if ($cuotasConSaldoPendiente > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Aprueba un préstamo: Pendiente → Aprobado (solo JC).
     */
    public function aprobar()
    {
        try {
            Log::info('Intentando aprobar préstamo', [
                'prestamo_id' => $this->id,
                'estado_actual' => $this->estado,
            ]);

            $this->refresh();

            if ($this->estado !== self::ESTADO_PENDIENTE) {
                Log::warning('No se puede aprobar: estado incorrecto', [
                    'prestamo_id' => $this->id,
                    'estado_actual' => $this->estado,
                ]);
                return false;
            }

            DB::beginTransaction();

            $this->estado = self::ESTADO_APROBADO;
            $guardado = $this->save();

            if (!$guardado) {
                throw new \Exception('Error al guardar el estado del préstamo');
            }

            if ($this->grupo) {
                $this->grupo->update(['estado_grupo' => 'Activo']);
            }

            $this->prestamoIndividual()->update(['estado' => self::ESTADO_APROBADO]);

            Log::info('Préstamo aprobado exitosamente', [
                'prestamo_id' => $this->id,
                'nuevo_estado' => $this->estado,
            ]);

            DB::commit();
            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al aprobar préstamo', [
                'prestamo_id' => $this->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Método para rechazar un préstamo
     */
    public function rechazar()
    {
        // Se puede rechazar tanto desde Pendiente como desde Aprobado
        if (!in_array($this->estado, [self::ESTADO_PENDIENTE, self::ESTADO_APROBADO])) {
            return false;
        }

        $this->estado = self::ESTADO_RECHAZADO;
        $this->save();

        // Actualizar el estado del grupo asociado
        if ($this->grupo) {
            $this->grupo->update(['estado_grupo' => 'Activo']);
        }

        // Actualizar el estado de los préstamos individuales
        $this->prestamoIndividual()->update(['estado' => self::ESTADO_RECHAZADO]);

        return true;
    }

    /**
     * Firma el contrato: Aprobado → Firmado (solo Asesor).
     */
    public function firmar(): bool
    {
        if ($this->estado !== self::ESTADO_APROBADO) {
            return false;
        }

        DB::beginTransaction();
        try {
            $this->estado = self::ESTADO_FIRMADO;
            $this->save();

            $this->prestamoIndividual()->update(['estado' => self::ESTADO_FIRMADO]);

            Log::info('Contrato firmado exitosamente', [
                'prestamo_id' => $this->id,
                'usuario_id' => auth()->id(),
            ]);

            DB::commit();
            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al firmar contrato', [
                'prestamo_id' => $this->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Desembolsa un préstamo: Firmado → Activo (solo JO, atómico).
     */
    public function desembolsar(?string $fechaDesembolso = null): bool
    {
        if ($this->estado !== self::ESTADO_FIRMADO) {
            return false;
        }

        DB::beginTransaction();
        try {
            $this->estado = self::ESTADO_ACTIVO;
            $this->fecha_desembolso = $fechaDesembolso
                ? \Carbon\Carbon::parse($fechaDesembolso)
                : now();
            $this->save();

            $this->prestamoIndividual()->update(['estado' => self::ESTADO_ACTIVO]);

            Log::info('Préstamo desembolsado y activado', [
                'prestamo_id' => $this->id,
                'fecha_desembolso' => $this->fecha_desembolso,
            ]);

            DB::commit();
            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al desembolsar préstamo', [
                'prestamo_id' => $this->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    // ─── Nuevos métodos de transición de estado ─────────────────────

    /**
     * Marca préstamo como Al Día: Activo/En_Mora → Al_Día (Sistema).
     */
    public function marcarAlDia(): bool
    {
        if (!in_array($this->estado, [self::ESTADO_ACTIVO, self::ESTADO_EN_MORA])) {
            return false;
        }
        $this->estado = self::ESTADO_AL_DIA;
        return $this->save();
    }

    /**
     * Marca préstamo como En Mora: Activo/Al_Día → En_Mora (Sistema).
     */
    public function marcarEnMora(): bool
    {
        if (!in_array($this->estado, [self::ESTADO_ACTIVO, self::ESTADO_AL_DIA])) {
            return false;
        }
        $this->estado = self::ESTADO_EN_MORA;
        return $this->save();
    }

    /**
     * Cancela un préstamo: Al_Día/En_Mora → Cancelado (solo JO).
     */
    public function cancelar(): bool
    {
        if (!in_array($this->estado, [self::ESTADO_AL_DIA, self::ESTADO_EN_MORA])) {
            return false;
        }
        $this->estado = self::ESTADO_CANCELADO;
        $this->prestamoIndividual()->update(['estado' => self::ESTADO_CANCELADO]);
        return $this->save();
    }

    /**
     * Reformula solicitud rechazada: Rechazado → Reformulado (solo Asesor).
     */
    public function reformular(): bool
    {
        if ($this->estado !== self::ESTADO_RECHAZADO) {
            return false;
        }
        $this->estado = self::ESTADO_REFORMULADO;
        return $this->save();
    }

    /**
     * Reenvía solicitud reformulada: Reformulado → Pendiente (solo Asesor).
     */
    public function reenviar(): bool
    {
        if ($this->estado !== self::ESTADO_REFORMULADO) {
            return false;
        }
        $this->estado = self::ESTADO_PENDIENTE;
        $this->prestamoIndividual()->update(['estado' => self::ESTADO_PENDIENTE]);
        return $this->save();
    }

    /**
     * Reduce el monto del préstamo de forma proporcional.
     * Permitido en estados 'Aprobado' y 'Firmado'.
     */
    public function reducirMonto(float $nuevoMontoTotal, string $justificacion): bool
    {
        // Solo permitido en estados Aprobado o Firmado
        if (!in_array($this->estado, [self::ESTADO_APROBADO, self::ESTADO_FIRMADO])) {
            return false;
        }

        // No permitir aumento (solo reducción)
        if ($nuevoMontoTotal >= (float) $this->monto_prestado_total) {
            return false;
        }

        DB::beginTransaction();
        try {
            $factor = $nuevoMontoTotal / (float) $this->monto_prestado_total;

            // Actualizar cada préstamo individual proporcionalmente
            foreach ($this->prestamoIndividual as $pi) {
                $nuevoMonto = round((float) $pi->monto_prestado_individual * $factor, 2);
                $nuevoInteres = round((float) $pi->interes * $factor, 2);
                $nuevoSeguro = round((float) $pi->seguro * $factor, 2);

                $pi->update([
                    'monto_prestado_individual' => $nuevoMonto,
                    'interes' => $nuevoInteres,
                    'seguro' => $nuevoSeguro,
                    'monto_devolver_individual' => $nuevoMonto + $nuevoInteres + $nuevoSeguro,
                ]);
            }

            // Sincronizar montos del préstamo grupal
            $this->sincronizarMontosTotal();

            // Registrar en log
            Log::info('Monto de préstamo reducido', [
                'prestamo_id' => $this->id,
                'monto_anterior' => $this->getOriginal('monto_prestado_total'),
                'monto_nuevo' => $nuevoMontoTotal,
                'factor' => $factor,
                'justificacion' => $justificacion,
                'usuario_id' => auth()->id(),
            ]);

            DB::commit();
            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al reducir monto', [
                'prestamo_id' => $this->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Verifica si el préstamo puede ser aprobado (Pendiente → Aprobado).
     */
    public function puedeSerAprobado(): bool
    {
        return $this->estado === self::ESTADO_PENDIENTE;
    }

    /**
     * Verifica si el préstamo puede ser firmado (Aprobado → Firmado).
     */
    public function puedeFirmar(): bool
    {
        return $this->estado === self::ESTADO_APROBADO;
    }

    /**
     * Verifica si el préstamo puede ser desembolsado (Firmado → Activo).
     */
    public function puedeDesembolsar(): bool
    {
        return $this->estado === self::ESTADO_FIRMADO;
    }

    /**
     * Verifica si se puede reducir el monto (solo en Aprobado o Firmado).
     */
    public function puedeReducirMonto(): bool
    {
        return in_array($this->estado, [self::ESTADO_APROBADO, self::ESTADO_FIRMADO]);
    }

    /**
     * Verifica si puede ser reformulado (Rechazado → Reformulado).
     */
    public function puedeReformular(): bool
    {
        return $this->estado === self::ESTADO_RECHAZADO;
    }

    /**
     * Verifica si puede ser cancelado (Al_Día o En_Mora → Cancelado).
     */
    public function puedeCancelar(): bool
    {
        return in_array($this->estado, [self::ESTADO_AL_DIA, self::ESTADO_EN_MORA]);
    }

    /**
     * Verifica si el préstamo puede ser rechazado
     */
    public function puedeSerRechazado(): bool
    {
        return $this->estado === self::ESTADO_PENDIENTE;
    }

    // Método para sincronizar los montos totales basándose en los préstamos individuales
    public function sincronizarMontosTotal()
    {
        // Solo sincronizar si está en estado Pendiente y NO es un retanqueo
        if ($this->estado !== 'Pendiente' || $this->es_retanqueo) {
            \Illuminate\Support\Facades\Log::warning('Intento de sincronización bloqueado', [
                'prestamo_id' => $this->id,
                'estado' => $this->estado,
                'es_retanqueo' => $this->es_retanqueo,
                'motivo' => 'El préstamo no está en estado Pendiente o es un retanqueo'
            ]);
            return $this;
        }

        if ($this->prestamoIndividual()->count() > 0) {
            $montoTotal = $this->prestamoIndividual()->sum('monto_prestado_individual');
            $montoDevolver = $this->prestamoIndividual()->sum('monto_devolver_individual');

            $this->updateQuietly([
                'monto_prestado_total' => round($montoTotal, 2),
                'monto_devolver' => round($montoDevolver, 2),
            ]);

            \Illuminate\Support\Facades\Log::info('Sincronización de montos completada', [
                'prestamo_id' => $this->id,
                'monto_total_anterior' => $this->getOriginal('monto_prestado_total'),
                'monto_total_nuevo' => round($montoTotal, 2),
                'monto_devolver_anterior' => $this->getOriginal('monto_devolver'),
                'monto_devolver_nuevo' => round($montoDevolver, 2),
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
        if (!$this->es_parcialmente_retanqueado) {
            return null;
        }

        $retanqueo = $this->retanqueoComoAntiguo()->where('estado_retanqueo', 'ejecutado')->first();
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
