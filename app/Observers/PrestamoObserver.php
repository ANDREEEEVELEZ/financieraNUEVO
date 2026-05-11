<?php

namespace App\Observers;

use App\Models\Prestamo;
use App\Models\CuotasGrupales;
use App\Models\CuotaIndividual;
use App\Models\Egreso;
use App\Models\MovimientoFinanciero;
use App\Models\PrestamoIndividual;
use App\Models\Retanqueo;
use App\Services\CacheService;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class PrestamoObserver
{
    public function created(Prestamo $prestamo): void
    {
        Log::info('PrestamoObserver: Préstamo creado', ['prestamo_id' => $prestamo->id]);

        // Invalidar cache de estadísticas
        CacheService::invalidateStatsCache();

        // Invalidar notificaciones de supervisores (nuevo préstamo pendiente)
        $supervisores = \App\Models\User::role(['super_admin', 'Jefe de operaciones', 'Jefe de creditos'])->get();
        foreach ($supervisores as $supervisor) {
            NotificationService::invalidateNotificationsCache($supervisor->id);
        }

        Log::debug('PrestamoObserver: Cache invalidated for new prestamo', [
            'prestamo_id' => $prestamo->id,
        ]);
    }

    public function updated(Prestamo $prestamo): void
    {
        Log::info('PrestamoObserver: updated() ejecutado', [
            'prestamo_id' => $prestamo->id,
            'estado_actual' => $prestamo->estado,
            'was_changed' => $prestamo->wasChanged('estado'),
        ]);

        // Actualizar estado de los préstamos individuales (compatibilidad)
        if ($prestamo->wasChanged('estado')) {
            if (in_array($prestamo->estado, ['Pendiente', 'Aprobado', 'Rechazado'])) {
                PrestamoIndividual::where('prestamo_id', $prestamo->id)
                    ->update(['estado' => $prestamo->estado]);
            }

            // Invalidar cache de estadísticas y notificaciones
            CacheService::invalidateStatsCache();

            // Invalidar notificaciones de supervisores
            $supervisores = \App\Models\User::role(['super_admin', 'Jefe de operaciones', 'Jefe de creditos'])->get();
            foreach ($supervisores as $supervisor) {
                NotificationService::invalidateNotificationsCache($supervisor->id);
            }

            // Invalidar notificaciones del asesor
            if ($prestamo->grupo && $prestamo->grupo->asesor && $prestamo->grupo->asesor->user_id) {
                NotificationService::invalidateNotificationsCache($prestamo->grupo->asesor->user_id);
            }

            Log::debug('PrestamoObserver: Cache invalidated for prestamo update', [
                'prestamo_id' => $prestamo->id,
                'new_estado' => $prestamo->estado,
            ]);
        }

        // ── Transición: Firmado → Activo (Desembolso por JO) ─────────────────
        if (
            $prestamo->wasChanged('estado') &&
            strtolower($prestamo->estado) === 'activo' &&
            strtolower($prestamo->getRawOriginal('estado') ?? '') === 'firmado'
        ) {
            $this->procesarDesembolso($prestamo);
        }

        // ── Retrocompatibilidad: "Desembolsado" (datos históricos) ────────────
        // Solo para registros anteriores a la refactorización del FSM.
        if (
            $prestamo->wasChanged('estado') &&
            strtolower($prestamo->estado) === 'desembolsado' &&
            strtolower($prestamo->getRawOriginal('estado') ?? '') === 'por desembolsar'
        ) {
            $this->procesarDesembolso($prestamo);
        }
    }

    /**
     * Procesa el desembolso de un préstamo:
     * 1. Crea CuotaIndividual para cada cliente del grupo (SNAPSHOT)
     * 2. Crea CuotasGrupales para compatibilidad
     * 3. Registra MovimientoFinanciero
     */
    private function procesarDesembolso(Prestamo $prestamo): void
    {
        Log::info('PrestamoObserver: Procesando desembolso', ['prestamo_id' => $prestamo->id]);

        $fechaDesembolso = $prestamo->fecha_desembolso ?? now()->toDateString();

        // Validaciones de seguridad
        $yaTieneCuotasGrupales = CuotasGrupales::where('prestamo_id', $prestamo->id)->exists();
        $yaTieneCuotasIndividuales = CuotaIndividual::where('prestamo_id', $prestamo->id)->exists();
        $esRetanqueo = stripos($prestamo->descripcion ?? '', 'RETANQUEO') !== false;
        $tienePrestamoAntiguo = Retanqueo::where('prestamo_nuevo_id', $prestamo->id)->exists();

        if ($yaTieneCuotasIndividuales || $esRetanqueo || $tienePrestamoAntiguo) {
            Log::info('PrestamoObserver: Cuotas NO creadas por seguridad', [
                'prestamo_id' => $prestamo->id,
                'ya_tiene_cuotas_individuales' => $yaTieneCuotasIndividuales,
            ]);
            return;
        }

        // Validar fecha de desembolso
        if (!$prestamo->fecha_desembolso) {
            throw new \Exception('No se puede crear las cuotas sin fecha de desembolso');
        }

        // Calcular parámetros
        $fechaInicio = Carbon::parse($prestamo->fecha_desembolso);
        $dias = match ($prestamo->frecuencia) {
            'mensual' => 30,
            'quincenal' => 15,
            'semanal' => 7,
            default => 30,
        };
        $cantidadCuotas = $prestamo->cantidad_cuotas;

        // Determinar clientes del préstamo
        $clientes = $this->obtenerClientesDelPrestamo($prestamo);

        if ($clientes->isEmpty()) {
            Log::warning('PrestamoObserver: No hay clientes para crear cuotas', ['prestamo_id' => $prestamo->id]);
            return;
        }

        // Obtener montos individuales desde PrestamoIndividual
        $prestamosIndividuales = $prestamo->prestamoIndividual()->get()->keyBy('cliente_id');

        // Crear cuotas para cada cliente (SNAPSHOT)
        foreach ($clientes as $cliente) {
            $prestamoInd = $prestamosIndividuales->get($cliente->id);

            if (!$prestamoInd) {
                Log::warning('PrestamoObserver: Cliente sin PrestamoIndividual', [
                    'prestamo_id' => $prestamo->id,
                    'cliente_id' => $cliente->id,
                ]);
                continue;
            }

            // Calcular monto por cuota para este cliente
            $montoTotalCliente = $prestamoInd->monto_devolver_individual ??
                ($prestamoInd->monto_prestado_individual + $prestamoInd->interes + $prestamoInd->seguro);
            $capitalPorCuota = round($prestamoInd->monto_prestado_individual / $cantidadCuotas, 2);
            $interesPorCuota = round($prestamoInd->interes / $cantidadCuotas, 2);
            $seguroPorCuota = round(($prestamoInd->seguro ?? 0) / $cantidadCuotas, 2);

            for ($i = 1; $i <= $cantidadCuotas; $i++) {
                $fechaVencimiento = $fechaInicio->copy()->addDays($dias * $i);

                CuotaIndividual::create([
                    'prestamo_id' => $prestamo->id,
                    'cliente_id' => $cliente->id,
                    'numero_cuota' => $i,
                    'monto_capital_original' => $capitalPorCuota,
                    'monto_interes_original' => $interesPorCuota,
                    'monto_seguro' => $seguroPorCuota,
                    'saldo_capital' => $capitalPorCuota,
                    'saldo_interes' => $interesPorCuota,
                    'fecha_vencimiento' => $fechaVencimiento,
                    'estado' => 'pendiente',
                ]);
            }

            Log::info('PrestamoObserver: Cuotas individuales creadas', [
                'prestamo_id' => $prestamo->id,
                'cliente_id' => $cliente->id,
                'cantidad' => $cantidadCuotas,
            ]);
        }

        // Crear cuotas grupales para compatibilidad (si es préstamo grupal)
        if (!$yaTieneCuotasGrupales && $prestamo->esGrupal()) {
            $this->crearCuotasGrupalesCompatibilidad($prestamo, $fechaInicio, $dias);
        }

        // Registrar movimiento financiero (desembolso)
        $this->registrarDesembolso($prestamo, $fechaDesembolso);
    }

    /**
     * Obtiene los clientes del préstamo (grupal o individual).
     */
    private function obtenerClientesDelPrestamo(Prestamo $prestamo)
    {
        if ($prestamo->esIndividual() && $prestamo->cliente_id) {
            return collect([$prestamo->cliente]);
        }

        if ($prestamo->grupo) {
            return $prestamo->grupo->clientes;
        }

        return collect();
    }

    /**
     * Crea cuotas grupales para mantener compatibilidad con el sistema anterior.
     */
    private function crearCuotasGrupalesCompatibilidad(Prestamo $prestamo, Carbon $fechaInicio, int $dias): void
    {
        $montoTotalDevolver = $prestamo->monto_devolver;
        $cantidadCuotas = $prestamo->cantidad_cuotas;
        $montoPorCuota = $montoTotalDevolver / $cantidadCuotas;

        for ($i = 1; $i <= $cantidadCuotas; $i++) {
            $fechaVencimiento = $fechaInicio->copy()->addDays($dias * $i);

            CuotasGrupales::create([
                'prestamo_id' => $prestamo->id,
                'numero_cuota' => $i,
                'monto_cuota_grupal' => round($montoPorCuota, 2),
                'saldo_pendiente' => round($montoPorCuota, 2),
                'fecha_vencimiento' => $fechaVencimiento,
                'estado_cuota_grupal' => 'vigente',
                'estado_pago' => 'pendiente',
            ]);
        }

        Log::info('PrestamoObserver: Cuotas grupales creadas (compatibilidad)', ['prestamo_id' => $prestamo->id]);
    }

    /**
     * Registra el desembolso usando MovimientoFinanciero Y Egreso (compatibilidad).
     */
    private function registrarDesembolso(Prestamo $prestamo, string $fechaAprobacion): void
    {
        // Nuevo sistema: MovimientoFinanciero
        $existeMovimiento = MovimientoFinanciero::where('referencia_tipo', Prestamo::class)
            ->where('referencia_id', $prestamo->id)
            ->where('concepto', 'desembolso')
            ->exists();

        if (!$existeMovimiento) {
            MovimientoFinanciero::registrarDesembolso($prestamo);
            Log::info('PrestamoObserver: MovimientoFinanciero creado', ['prestamo_id' => $prestamo->id]);
        }

        // Compatibilidad: Egreso (mantener por ahora)
        $descripcion = $prestamo->esGrupal() && $prestamo->grupo
            ? 'Desembolso al grupo ' . $prestamo->grupo->nombre_grupo
            : 'Desembolso préstamo #' . $prestamo->id;

        $existeEgreso = Egreso::where('prestamo_id', $prestamo->id)
            ->where('tipo_egreso', 'desembolso')
            ->exists();

        if (!$existeEgreso) {
            Egreso::create([
                'tipo_egreso' => 'desembolso',
                'prestamo_id' => $prestamo->id,
                'fecha' => $fechaAprobacion,
                'monto' => $prestamo->monto_prestado_total,
                'descripcion' => $descripcion,
                'categoria_id' => null,
                'subcategoria_id' => null,
            ]);
            Log::info('PrestamoObserver: Egreso creado (compatibilidad)', ['prestamo_id' => $prestamo->id]);
        }
    }
}

