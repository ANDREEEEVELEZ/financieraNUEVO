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

        // CREAR PRESTAMOS INDIVIDUALES
        $grupo = $prestamo->grupo;
        if ($grupo) {
            $clientes = $grupo->clientes()->get();
            $tasaInteres = $prestamo->tasa_interes ?? 17;
            $numCuotas = $prestamo->cantidad_cuotas;
            // Definir los montos máximos y seguros por ciclo
            $ciclos = [
                1 => ['max' => 400, 'seguro' => 6],
                2 => ['max' => 600, 'seguro' => 7],
                3 => ['max' => 800, 'seguro' => 8],
                4 => ['max' => 1000, 'seguro' => 9],
            ];
            foreach ($clientes as $cliente) {
                $ciclo = (int)($cliente->ciclo ?? 1);
                $ciclo = $ciclo > 4 ? 4 : ($ciclo < 1 ? 1 : $ciclo);
                $maxPrestamo = $ciclos[$ciclo]['max'];
                $seguro = $ciclos[$ciclo]['seguro'];
                // Buscar el monto solicitado por el cliente (debe venir del formulario)
                $montoSolicitado = 0;
                if (isset($prestamo->clientes_grupo) && is_array($prestamo->clientes_grupo)) {
                    foreach ($prestamo->clientes_grupo as $cli) {
                        if ((int)$cli['id'] === (int)$cliente->id) {
                            $montoSolicitado = min(floatval($cli['monto']), $maxPrestamo);
                            break;
                        }
                    }
                }
                // Si no se encuentra, usar 0
                if ($montoSolicitado <= 0) continue;
                $interes = $montoSolicitado * ($tasaInteres / 100);
                $montoDevolver = $montoSolicitado + $interes + $seguro;
                $cuotaSemanal = $montoDevolver / $numCuotas;
                PrestamoIndividual::create([
                    'prestamo_id' => $prestamo->id,
                    'cliente_id' => $cliente->id,
                    'monto_prestado_individual' => $montoSolicitado,
                    'monto_cuota_prestamo_individual' => round($cuotaSemanal, 2),
                    'monto_devolver_individual' => round($montoDevolver, 2),
                    'seguro' => $seguro,
                    'interes' => $tasaInteres,
                    'estado' => 'Pendiente',
                ]);
            }
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