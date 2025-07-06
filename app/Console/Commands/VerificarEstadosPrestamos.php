<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Prestamo;

class VerificarEstadosPrestamos extends Command
{
    protected $signature = 'prestamos:verificar-estados {--fix : Corregir automáticamente los estados incorrectos}';
    protected $description = 'Verifica y corrige el estado de préstamos basándose en el estado de sus cuotas';

    public function handle()
    {
        $this->info('Iniciando verificación de estados de préstamos...');
        
        // Obtener todos los préstamos en estado "Aprobado"
        $prestamos = Prestamo::where('estado', 'Aprobado')
            ->with('cuotasGrupales')
            ->get();
            
        $this->info("Encontrados {$prestamos->count()} préstamos en estado 'Aprobado'");
        
        $prestamosParaFinalizar = 0;
        $prestamosCorregidos = 0;
        
        foreach ($prestamos as $prestamo) {
            $totalCuotas = $prestamo->cuotasGrupales->count();
            $cuotasPagadas = $prestamo->cuotasGrupales->where('estado_pago', 'pagado')->count();
            
            $this->line("Préstamo ID {$prestamo->id}:");
            $this->line("  - Total cuotas: {$totalCuotas}");
            $this->line("  - Cuotas pagadas: {$cuotasPagadas}");
            
            if ($totalCuotas > 0 && $cuotasPagadas === $totalCuotas) {
                $this->warn("  ❌ PROBLEMA: Todas las cuotas están pagadas pero el préstamo sigue en estado 'Aprobado'");
                $prestamosParaFinalizar++;
                
                if ($this->option('fix')) {
                    $exito = $prestamo->verificarYActualizarEstado();
                    if ($exito) {
                        $this->info("  ✅ Estado corregido a 'Finalizado'");
                        $prestamosCorregidos++;
                    } else {
                        $this->error("  ❌ Error al corregir el estado");
                    }
                } else {
                    $this->line("  💡 Ejecuta con --fix para corregir automáticamente");
                }
            } else {
                $this->info("  ✅ Estado correcto");
            }
            
            $this->line('');
        }
        
        $this->info("Resumen:");
        $this->info("- Préstamos revisados: {$prestamos->count()}");
        $this->info("- Préstamos con problemas: {$prestamosParaFinalizar}");
        
        if ($this->option('fix')) {
            $this->info("- Préstamos corregidos: {$prestamosCorregidos}");
        } else if ($prestamosParaFinalizar > 0) {
            $this->warn("Ejecuta el comando con --fix para corregir automáticamente los problemas.");
        }
        
        return 0;
    }
}
