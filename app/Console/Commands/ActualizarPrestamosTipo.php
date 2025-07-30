<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Prestamo;

class ActualizarPrestamosTipo extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'prestamos:actualizar-tipo {--force : Forzar actualización sin confirmación}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Actualiza los préstamos existentes marcándolos como originales';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔄 Iniciando actualización de tipos de préstamos...');
        
        // Contar préstamos sin marcar
        $prestamosSinMarcar = Prestamo::whereNull('es_retanqueo')->orWhere('es_retanqueo', false)->count();
        
        if ($prestamosSinMarcar === 0) {
            $this->info('✅ Todos los préstamos ya están correctamente marcados.');
            return 0;
        }
        
        $this->warn("📊 Se encontraron {$prestamosSinMarcar} préstamos sin marcar como originales.");
        
        if (!$this->option('force') && !$this->confirm('¿Desea continuar con la actualización?')) {
            $this->warn('❌ Operación cancelada por el usuario.');
            return 1;
        }
        
        // Actualizar préstamos existentes como originales
        $actualizados = Prestamo::whereNull('es_retanqueo')
            ->orWhere('es_retanqueo', false)
            ->update([
                'es_retanqueo' => false,
                'descripcion' => null,
                'prestamo_origen_id' => null
            ]);
            
        $this->info("✅ Se actualizaron {$actualizados} préstamos como originales.");
        
        // Mostrar estadísticas
        $totalOriginales = Prestamo::where('es_retanqueo', false)->count();
        $totalRetanqueos = Prestamo::where('es_retanqueo', true)->count();
        
        $this->newLine();
        $this->info('📈 ESTADÍSTICAS FINALES:');
        $this->line("   • Préstamos Originales: {$totalOriginales}");
        $this->line("   • Préstamos de Retanqueo: {$totalRetanqueos}");
        $this->line("   • Total: " . ($totalOriginales + $totalRetanqueos));
        
        $this->newLine();
        $this->info('🎉 Actualización completada exitosamente.');
        
        return 0;
    }
}
