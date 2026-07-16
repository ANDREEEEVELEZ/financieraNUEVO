<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Domain\Prestamos\Concerns\FiltraCuotasPendientesConSaldo;
use App\Models\Prestamo;
use App\Models\Retanqueo;

class ActualizarEstadosRetanqueo extends Command
{
    use FiltraCuotasPendientesConSaldo;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'retanqueos:actualizar-estados 
                           {--dry-run : Solo mostrar qué cambios se harían sin ejecutarlos}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Actualiza los estados de préstamos que tienen retanqueos ejecutados para aplicar la nueva lógica';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');
        
        $this->info('🔄 ACTUALIZANDO ESTADOS DE PRÉSTAMOS CON RETANQUEOS');
        $this->newLine();
        
        if ($dryRun) {
            $this->warn('⚠️  MODO SIMULACIÓN - No se harán cambios reales');
            $this->newLine();
        }

        // Buscar retanqueos ejecutados
        $retanqueos = Retanqueo::where('estado_retanqueo', 'ejecutado')
            ->with(['prestamoAntiguo.grupo.clientes', 'retanqueosIndividuales'])
            ->get();

        $prestamosActualizados = 0;
        $prestamosYaFinalizados = 0;

        foreach ($retanqueos as $retanqueo) {
            /** @var Retanqueo $retanqueo */
            $prestamoAntiguo = $retanqueo->prestamoAntiguo;
            
            if (!$prestamoAntiguo) {
                $this->warn("⚠️  Retanqueo ID {$retanqueo->id} no tiene préstamo antiguo");
                continue;
            }

            $this->line("🔍 Analizando préstamo ID: {$prestamoAntiguo->id}");

            // Verificar cuotas pendientes (solo informativo/log — no afecta la decisión abajo)
            $cuotasPendientes = self::cuotasPendientesConSaldo($prestamoAntiguo)->count();

            // Verificar si todos los integrantes retanquearon
            $totalIntegrantes = $prestamoAntiguo->grupo->clientes()->count();
            $integrantesQueRetanquean = $retanqueo->retanqueosIndividuales()
                ->whereIn('participacion_tipo', ['retanquea', 'nueva'])
                ->count();

            $this->line("  📊 Cuotas pendientes: {$cuotasPendientes}");
            $this->line("  👥 Total integrantes: {$totalIntegrantes}");
            $this->line("  🔄 Integrantes que retanquearon: {$integrantesQueRetanquean}");
            $this->line("  📋 Estado actual: {$prestamoAntiguo->estado}");

            $nuevoEstado = null;

            // NUEVA LÓGICA SIMPLIFICADA:
            // - Si hay integrantes que no retanquearon: Activo + flag es_parcialmente_retanqueado
            // - Si todos retanquearon: Finalizado
            
            if ($integrantesQueRetanquean === $totalIntegrantes) {
                // Todos retanquearon - finalizar
                if ($prestamoAntiguo->estado !== Prestamo::ESTADO_FINALIZADO) {
                    $nuevoEstado = Prestamo::ESTADO_FINALIZADO;
                } else {
                    $prestamosYaFinalizados++;
                    $this->line("  ✅ Ya está finalizado");
                }
            } else {
                // Algunos no retanquearon — marcar con flag
                if (!$prestamoAntiguo->es_parcialmente_retanqueado) {
                    $nuevoEstado = Prestamo::ESTADO_ACTIVO;
                } else {
                    $this->line("  ✅ Ya está en estado correcto");
                }
            }

            if ($nuevoEstado) {
                $this->line("  🔄 Cambiando estado a: {$nuevoEstado}");
                
                if (!$dryRun) {
                    $updateData = ['estado' => $nuevoEstado];
                    // Si no todos retanquearon, activar flag
                    if ($nuevoEstado === Prestamo::ESTADO_ACTIVO) {
                        $updateData['es_parcialmente_retanqueado'] = true;
                    }
                    $prestamoAntiguo->update($updateData);
                    
                    // SOLO mover a ex-integrantes si se finaliza Y todos ya terminaron de pagar
                    if ($nuevoEstado === Prestamo::ESTADO_FINALIZADO) {
                        // Verificar si realmente no hay deuda pendiente individual
                        if (!$prestamoAntiguo->tieneIntegrantesNoRetanqueadosConDeudaPendiente()) {
                            $prestamoAntiguo->moverIntegrantesNoRetanqueadosAExIntegrantes();
                            $this->line("  👥 Ex-integrantes movidos automáticamente");
                        }
                    }
                }
                
                $prestamosActualizados++;
            }

            $this->newLine();
        }

        $this->info('📊 RESUMEN:');
        $this->line("  • Retanqueos analizados: {$retanqueos->count()}");
        $this->line("  • Préstamos actualizados: {$prestamosActualizados}");
        $this->line("  • Préstamos ya finalizados: {$prestamosYaFinalizados}");
        
        if ($dryRun) {
            $this->newLine();
            $this->info('💡 Para aplicar los cambios, ejecuta el comando sin --dry-run');
        } else {
            $this->newLine();
            $this->info('✅ Actualización completada exitosamente');
        }

        return 0;
    }
}
