<?php

namespace App\Observers;

use App\Models\Prestamo;
use App\Models\CuotasGrupales;
use App\Models\Egreso;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class PrestamoObserver
{
    public function created(Prestamo $prestamo): void
    {
        Log::info('PrestamoObserver: Préstamo creado', ['prestamo_id' => $prestamo->id]);
        
        // Crear cuotas grupales
        $montoTotal = $prestamo->monto_devolver;
        $cantidadCuotas = $prestamo->cantidad_cuotas;
        $montoPorCuota = $montoTotal / $cantidadCuotas;
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
                'monto_cuota_grupal' => round($montoPorCuota, 2),
                'saldo_pendiente' => round($montoPorCuota, 2),
                'fecha_vencimiento' => $fechaInicio->copy()->addDays($dias * $i),
                'estado_cuota_grupal' => 'vigente',
                'estado_pago' => 'pendiente',
            ]);
        }
    }


    public function updated(Prestamo $prestamo): void
    {
        Log::info('PrestamoObserver: updated() ejecutado', [
            'prestamo_id' => $prestamo->id,
            'estado_actual' => $prestamo->estado,
            'was_changed' => $prestamo->wasChanged('estado'),
            'changed_attributes' => $prestamo->getChanges()
        ]);
        
     
        if ($prestamo->wasChanged('estado') && strtolower($prestamo->estado) === 'aprobado') {
            Log::info('PrestamoObserver: Préstamo aprobado detectado', ['prestamo_id' => $prestamo->id]);
            
            $existeEgreso = Egreso::where('prestamo_id', $prestamo->id)
                ->where('tipo_egreso', 'desembolso')
                ->exists();

            Log::info('PrestamoObserver: Verificando egreso existente', [
                'prestamo_id' => $prestamo->id,
                'existe_egreso' => $existeEgreso
            ]);

            if (!$existeEgreso) {
                $grupo = $prestamo->grupo;
                
                Log::info('PrestamoObserver: Datos del préstamo para egreso', [
                    'prestamo_id' => $prestamo->id,
                    'tiene_grupo' => !is_null($grupo),
                    'grupo_id' => $grupo?->id,
                    'grupo_nombre' => $grupo?->nombre_grupo,
                    'monto_prestado_total' => $prestamo->monto_prestado_total,
                    'fecha_prestamo' => $prestamo->fecha_prestamo
                ]);
                
                if ($grupo) {
                    try {
                        $egreso = Egreso::create([
                            'tipo_egreso' => 'desembolso',
                            'prestamo_id' => $prestamo->id,
                            'fecha' => $prestamo->fecha_prestamo,
                            'monto' => $prestamo->monto_prestado_total,
                            'descripcion' => 'Desembolso al grupo ' . $grupo->nombre_grupo,
                            'categoria_id' => null,
                            'subcategoria_id' => null,
                        ]);
                        
                        Log::info('PrestamoObserver: Egreso creado exitosamente', [
                            'egreso_id' => $egreso->id,
                            'prestamo_id' => $prestamo->id,
                            'monto' => $egreso->monto
                        ]);
                        
                    } catch (\Exception $e) {
                        Log::error('PrestamoObserver: Error al crear egreso', [
                            'prestamo_id' => $prestamo->id,
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString()
                        ]);
                        
                        // Re-lanzar la excepción para que sea visible
                        throw $e;
                    }
                } else {
                    Log::warning('PrestamoObserver: Préstamo sin grupo asociado', [
                        'prestamo_id' => $prestamo->id
                    ]);
                }
            } else {
                Log::info('PrestamoObserver: Ya existe egreso para este préstamo', [
                    'prestamo_id' => $prestamo->id
                ]);
            }
        } else {
            Log::info('PrestamoObserver: No se cumplieron condiciones para crear egreso', [
                'prestamo_id' => $prestamo->id,
                'estado_actual' => $prestamo->estado,
                'was_changed' => $prestamo->wasChanged('estado'),
                'es_aprobado' => strtolower($prestamo->estado) === 'aprobado'
            ]);
        }
    }
}