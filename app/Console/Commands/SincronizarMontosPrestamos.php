<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Prestamo;
use App\Models\PrestamoIndividual;

class SincronizarMontosPrestamos extends Command
{
    protected $signature = 'prestamos:sincronizar-montos';
    protected $description = 'Sincroniza los montos totales de préstamos basándose en los préstamos individuales';    public function handle()
    {
        $this->info('Iniciando sincronización de montos de préstamos...');
        
        $prestamos = Prestamo::with(['prestamoIndividual', 'cuotasGrupales'])->get();
        $prestamosActualizados = 0;
        $cuotasActualizadas = 0;
        
        foreach ($prestamos as $prestamo) {
            $prestamosActualizados++;
              if ($prestamo->prestamoIndividual->count() > 0) {
                $montoTotal = $prestamo->prestamoIndividual->sum('monto_prestado_individual');
                $montoDevolver = $prestamo->prestamoIndividual->sum('monto_devolver_individual');
                
                $montoTotalActual = (float)$prestamo->monto_prestado_total;
                $montoDevolverActual = (float)$prestamo->monto_devolver;
                
                $needsUpdate = false;
                
                if ($montoTotalActual != $montoTotal) {
                    $this->line("Préstamo ID {$prestamo->id}: Monto total actual: {$montoTotalActual}, Calculado: {$montoTotal}");
                    $needsUpdate = true;
                }
                
                if ($montoDevolverActual != $montoDevolver) {
                    $this->line("Préstamo ID {$prestamo->id}: Monto devolver actual: {$montoDevolverActual}, Calculado: {$montoDevolver}");
                    $needsUpdate = true;
                }
                
                if ($needsUpdate) {
                    $prestamo->updateQuietly([
                        'monto_prestado_total' => round($montoTotal, 2),
                        'monto_devolver' => round($montoDevolver, 2),
                    ]);
                    $this->info("✓ Préstamo ID {$prestamo->id} actualizado");
                    
                    // Actualizar cuotas grupales si existen
                    if ($prestamo->cuotasGrupales->count() > 0) {
                        $montoCorrecto = $montoDevolver / $prestamo->cantidad_cuotas;
                        
                        foreach ($prestamo->cuotasGrupales as $cuota) {
                            if (abs((float)$cuota->monto_cuota_grupal - $montoCorrecto) > 0.01) {
                                $cuota->update([
                                    'monto_cuota_grupal' => round($montoCorrecto, 2),
                                    'saldo_pendiente' => round($montoCorrecto, 2)
                                ]);
                                $cuotasActualizadas++;
                                $this->line("  ✓ Cuota {$cuota->numero_cuota} actualizada");
                            }
                        }
                    }
                }
            }
        }
        
        $this->info("Sincronización completada:");
        $this->info("- {$prestamosActualizados} préstamos revisados");
        $this->info("- {$cuotasActualizadas} cuotas actualizadas");
        
        return 0;
    }
}
