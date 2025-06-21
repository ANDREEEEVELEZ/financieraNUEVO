<?php

namespace App\Observers;

use App\Models\Prestamo;
use App\Models\CuotasGrupales;
use App\Models\Egreso;
use App\Models\PrestamoIndividual;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class PrestamoObserver
{
    public function created(Prestamo $prestamo): void
    {
        Log::info('PrestamoObserver: Préstamo creado', ['prestamo_id' => $prestamo->id]);
        // Ya no se crean cuotas aquí
    }

    public function updated(Prestamo $prestamo): void
    {
        Log::info('PrestamoObserver: updated() ejecutado', [
            'prestamo_id' => $prestamo->id,
            'estado_actual' => $prestamo->estado,
            'was_changed' => $prestamo->wasChanged('estado'),
            'changed_attributes' => $prestamo->getChanges()
        ]);

        // Actualizar estado de los préstamos individuales
        if ($prestamo->wasChanged('estado')) {
            if (in_array($prestamo->estado, ['Pendiente', 'Aprobado', 'Rechazado'])) {
                PrestamoIndividual::where('prestamo_id', $prestamo->id)
                    ->update(['estado' => $prestamo->estado]);
            }
        }

        // Si el estado se cambió a aprobado, crear cuotas y egreso
        if ($prestamo->wasChanged('estado') && strtolower($prestamo->estado) === 'aprobado') {
            Log::info('PrestamoObserver: Préstamo aprobado detectado', ['prestamo_id' => $prestamo->id]);

            // Crear cuotas grupales si no existen aún
            $yaTieneCuotas = CuotasGrupales::where('prestamo_id', $prestamo->id)->exists();
            if (!$yaTieneCuotas) {
                $totalSeguro = $prestamo->prestamoIndividual()->sum('seguro');
                $montoTotal = $prestamo->monto_devolver;
                $cantidadCuotas = $prestamo->cantidad_cuotas;
                $montoPorCuota = $montoTotal / $cantidadCuotas;
                $seguroPorCuota = $totalSeguro / $cantidadCuotas;
                $fechaInicio = Carbon::parse($prestamo->fecha_prestamo);
                $dias = match($prestamo->frecuencia) {
                    'mensual' => 30,
                    'quincenal' => 15,
                    'semanal' => 7,
                    default => 30,
                };

                for ($i = 1; $i <= $cantidadCuotas; $i++) {
                    CuotasGrupales::create([
                        'prestamo_id' => $prestamo->id,
                        'numero_cuota' => $i,
                        'monto_cuota_grupal' => round($montoPorCuota + $seguroPorCuota, 2),
                        'saldo_pendiente' => round($montoPorCuota + $seguroPorCuota, 2),
                        'fecha_vencimiento' => $fechaInicio->copy()->addDays($dias * $i),
                        'estado_cuota_grupal' => 'vigente',
                        'estado_pago' => 'pendiente',
                    ]);
                }

                Log::info('PrestamoObserver: Cuotas grupales creadas exitosamente', ['prestamo_id' => $prestamo->id]);
            }

            // Crear egreso si no existe
            $existeEgreso = Egreso::where('prestamo_id', $prestamo->id)
                ->where('tipo_egreso', 'desembolso')
                ->exists();

            if (!$existeEgreso && $prestamo->grupo) {
                try {
                    $egreso = Egreso::create([
                        'tipo_egreso' => 'desembolso',
                        'prestamo_id' => $prestamo->id,
                        'fecha' => $prestamo->fecha_prestamo,
                        'monto' => $prestamo->monto_prestado_total,
                        'descripcion' => 'Desembolso al grupo ' . $prestamo->grupo->nombre_grupo,
                        'categoria_id' => null,
                        'subcategoria_id' => null,
                    ]);
                    Log::info('PrestamoObserver: Egreso creado', ['egreso_id' => $egreso->id]);
                } catch (\Exception $e) {
                    Log::error('PrestamoObserver: Error al crear egreso', [
                        'prestamo_id' => $prestamo->id,
                        'error' => $e->getMessage()
                    ]);
                    throw $e;
                }
            }
        }
    }
}
