<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Prestamo;
use App\Models\CuotasGrupales;
use App\Models\Mora;
use Carbon\Carbon;

class CorregirFechasCuotas extends Command
{
    protected $signature = 'prestamos:corregir-fechas {--dry-run : Solo mostrar lo que se haría sin hacer cambios} {--prestamo-id= : Corregir solo un préstamo específico}';
    protected $description = 'Corrige las fechas de cuotas de préstamos que fueron aprobados después de su creación';

    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        $prestamoId = $this->option('prestamo-id');

        $this->info('🔍 Iniciando corrección de fechas de cuotas...');
        
        if ($isDryRun) {
            $this->warn('⚠️  MODO DRY-RUN: Solo se mostrarán los cambios, no se aplicarán');
        }

        // Buscar préstamos aprobados que puedan tener el problema
        $query = Prestamo::with(['cuotasGrupales', 'grupo'])
            ->where('estado', 'Aprobado')
            ->whereHas('cuotasGrupales');

        if ($prestamoId) {
            $query->where('id', $prestamoId);
        }

        $prestamos = $query->get();
        
        $this->info("📊 Encontrados {$prestamos->count()} préstamos aprobados con cuotas");

        $prestamosCorregidos = 0;
        $cuotasCorregidas = 0;
        $morasEliminadas = 0;

        foreach ($prestamos as $prestamo) {
            $this->line("\n📋 Analizando préstamo ID: {$prestamo->id} - Grupo: {$prestamo->grupo->nombre_grupo}");
            
            // Verificar si hay una diferencia significativa entre created_at y fecha_prestamo
            $fechaCreacion = Carbon::parse($prestamo->created_at);
            $fechaPrestamo = Carbon::createFromFormat('Y-m-d', $prestamo->getRawOriginal('fecha_prestamo'));
            $diferenciaDias = $fechaCreacion->diffInDays($fechaPrestamo);
            
            $this->line("   📅 Fecha creación: {$fechaCreacion->toDateString()}");
            $this->line("   📅 Fecha préstamo: {$fechaPrestamo->toDateString()}");
            $this->line("   📊 Diferencia: {$diferenciaDias} días");

            // Si la diferencia es mayor a 1 día, probablemente fue aprobado después
            if ($diferenciaDias > 1) {
                $this->warn("   ⚠️  Posible problema detectado - Aprobado {$diferenciaDias} días después de la creación");
                
                // Verificar si las cuotas tienen fechas incorrectas
                $primeraCuota = $prestamo->cuotasGrupales()->orderBy('numero_cuota')->first();
                if ($primeraCuota) {
                    $fechaVencimientoPrimera = Carbon::createFromFormat('Y-m-d', $primeraCuota->getRawOriginal('fecha_vencimiento'));
                    $this->line("   📅 Primera cuota vence: {$fechaVencimientoPrimera->toDateString()}");
                    
                    // Calcular cuándo debería vencer la primera cuota desde fecha_prestamo
                    $dias = match($prestamo->frecuencia) {
                        'mensual' => 30,
                        'quincenal' => 15,
                        'semanal' => 7,
                        default => 7,
                    };
                    
                    $fechaCorrecta = $fechaPrestamo->copy()->addDays($dias);
                    $this->line("   📅 Debería vencer: {$fechaCorrecta->toDateString()}");
                    
                    if ($fechaVencimientoPrimera->toDateString() !== $fechaCorrecta->toDateString()) {
                        $this->error("   ❌ Fechas incorrectas detectadas!");
                        
                        if (!$isDryRun) {
                            // Corregir todas las cuotas del préstamo
                            $cuotas = $prestamo->cuotasGrupales()->orderBy('numero_cuota')->get();
                            foreach ($cuotas as $cuota) {
                                $nuevaFecha = $fechaPrestamo->copy()->addDays($dias * $cuota->numero_cuota);
                                
                                $fechaVencimientoAnterior = Carbon::createFromFormat('Y-m-d', $cuota->getRawOriginal('fecha_vencimiento'));
                                $this->line("     🔧 Corrigiendo cuota {$cuota->numero_cuota}: {$fechaVencimientoAnterior->toDateString()} → {$nuevaFecha->toDateString()}");
                                
                                $cuota->update(['fecha_vencimiento' => $nuevaFecha]);
                                $cuotasCorregidas++;
                                
                                // Si la cuota tiene mora y la nueva fecha es futura, eliminar la mora incorrecta
                                if ($cuota->mora && $nuevaFecha->isFuture()) {
                                    $this->line("     🗑️  Eliminando mora incorrecta de cuota {$cuota->numero_cuota}");
                                    $cuota->mora->delete();
                                    $cuota->update(['estado_cuota_grupal' => 'vigente']);
                                    $morasEliminadas++;
                                }
                            }
                            
                            $prestamosCorregidos++;
                            $this->info("   ✅ Préstamo corregido exitosamente");
                        } else {
                            $this->line("   🔍 [DRY-RUN] Se corregirían las fechas de las cuotas");
                        }
                    } else {
                        $this->info("   ✅ Fechas correctas");
                    }
                }
            } else {
                $this->info("   ✅ Sin problemas detectados");
            }
        }

        $this->info("\n📊 Resumen:");
        $this->info("   📋 Préstamos analizados: {$prestamos->count()}");
        
        if (!$isDryRun) {
            $this->info("   ✅ Préstamos corregidos: {$prestamosCorregidos}");
            $this->info("   🔧 Cuotas corregidas: {$cuotasCorregidas}");
            $this->info("   🗑️  Moras incorrectas eliminadas: {$morasEliminadas}");
        } else {
            $this->warn("   🔍 Ejecute sin --dry-run para aplicar los cambios");
        }

        return 0;
    }
}
