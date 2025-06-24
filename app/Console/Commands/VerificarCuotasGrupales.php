<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\CuotasGrupales;
use App\Models\Prestamo;

class VerificarCuotasGrupales extends Command
{
    protected $signature = 'cuotas:verificar {--fix : Corregir automáticamente los problemas encontrados}';
    protected $description = 'Verifica y opcionalmente corrige las cuotas grupales basándose en los montos de préstamos';

    public function handle()
    {
        $this->info('Iniciando verificación de cuotas grupales...');
        
        $cuotasProblemas = 0;
        $cuotasCorregidas = 0;
        $prestamosRevisuados = 0;
        
        $prestamos = Prestamo::with(['cuotasGrupales', 'prestamoIndividual'])->get();
        
        foreach ($prestamos as $prestamo) {
            $prestamosRevisuados++;
            $this->line("Revisando préstamo ID {$prestamo->id}...");
            
            // Verificar si el préstamo tiene cuotas
            if ($prestamo->cuotasGrupales->count() === 0) {
                $this->warn("  ⚠️ Préstamo ID {$prestamo->id} no tiene cuotas grupales");
                continue;
            }
              // Calcular el monto correcto por cuota
            $montoTotalDevolver = (float)$prestamo->monto_devolver;
            $cantidadCuotas = $prestamo->cantidad_cuotas;
            $montoCorrecto = $montoTotalDevolver / $cantidadCuotas;
            
            $this->line("  Monto total a devolver: S/ {$montoTotalDevolver}");
            $this->line("  Cantidad de cuotas: {$cantidadCuotas}");
            $this->line("  Monto correcto por cuota: S/ " . round($montoCorrecto, 2));
            
            // Verificar cada cuota
            foreach ($prestamo->cuotasGrupales as $cuota) {
                $montoActual = (float)$cuota->monto_cuota_grupal;
                $diferencia = abs($montoActual - $montoCorrecto);
                
                if ($diferencia > 0.01) { // Tolerancia de 1 centavo
                    $cuotasProblemas++;
                    $this->error("  ❌ Cuota {$cuota->numero_cuota}: Actual S/ {$montoActual}, Debería ser S/ " . round($montoCorrecto, 2));
                    
                    if ($this->option('fix')) {
                        $cuota->update([
                            'monto_cuota_grupal' => round($montoCorrecto, 2),
                            'saldo_pendiente' => round($montoCorrecto, 2)
                        ]);
                        $cuotasCorregidas++;
                        $this->info("  ✅ Cuota {$cuota->numero_cuota} corregida");
                    }
                } else {
                    $this->info("  ✅ Cuota {$cuota->numero_cuota}: OK (S/ {$montoActual})");
                }
            }
            
            // Verificar que la suma de cuotas coincida con el monto total
            $sumaCuotas = (float)$prestamo->cuotasGrupales->sum('monto_cuota_grupal');
            $diferenciTotal = abs($sumaCuotas - $montoTotalDevolver);
            
            if ($diferenciTotal > 0.01) {
                $this->error("  ❌ Suma de cuotas (S/ {$sumaCuotas}) no coincide con monto total (S/ {$montoTotalDevolver})");
            } else {
                $this->info("  ✅ Suma de cuotas coincide con monto total");
            }
            
            $this->line('');
        }
        
        $this->info("Verificación completada:");
        $this->info("- Préstamos revisados: {$prestamosRevisuados}");
        $this->info("- Cuotas con problemas: {$cuotasProblemas}");
        
        if ($this->option('fix')) {
            $this->info("- Cuotas corregidas: {$cuotasCorregidas}");
        } else if ($cuotasProblemas > 0) {
            $this->warn("Ejecuta el comando con --fix para corregir automáticamente los problemas.");
        }
        
        return 0;
    }
}
