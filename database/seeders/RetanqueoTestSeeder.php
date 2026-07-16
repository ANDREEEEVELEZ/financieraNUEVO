<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Contracts\RetanqueoQueryInterface;
use App\Contracts\RetanqueoWorkflowInterface;
use App\Domain\Prestamos\Concerns\FiltraCuotasPendientesConSaldo;
use App\Models\Grupo;
use App\Models\Prestamo;
use App\Models\Cliente;

class RetanqueoTestSeeder extends Seeder
{
    use FiltraCuotasPendientesConSaldo;

    /**
     * Run the database seeder.
     */
    public function run(): void
    {
        $this->command->info('🚀 Iniciando seeder de prueba para Retanqueos...');

        try {
            $retanqueoService = app(RetanqueoQueryInterface::class);
            $retanqueoWorkflow = app(RetanqueoWorkflowInterface::class);
            
            // Buscar un grupo elegible para retanqueo con suficientes integrantes
            $gruposElegibles = $retanqueoService->obtenerGruposElegibles();
            
            if ($gruposElegibles->isEmpty()) {
                $this->command->warn('❌ No hay grupos elegibles para crear retanqueos de prueba.');
                return;
            }

            // Buscar un grupo con al menos 2 integrantes
            $grupo = null;
            $prestamo = null;
            
            foreach ($gruposElegibles as $grupoCandidate) {
                if ($grupoCandidate->clientes()->count() >= 2) {
                    $prestamoCandidate = $grupoCandidate->prestamos()
                        ->where('estado', 'Aprobado')
                        ->get()
                        ->first(fn ($prestamo) => self::cuotasPendientesConSaldo($prestamo)->isNotEmpty());
                    
                    if ($prestamoCandidate) {
                        $grupo = $grupoCandidate;
                        $prestamo = $prestamoCandidate;
                        break;
                    }
                }
            }

            if (!$grupo || !$prestamo) {
                $this->command->warn('❌ No se encontró un grupo con suficientes integrantes y préstamo elegible.');
                return;
            }

            $this->command->info("✅ Usando grupo: {$grupo->nombre_grupo} (ID: {$grupo->id})");
            $this->command->info("✅ Préstamo ID: {$prestamo->id}");

            // Obtener clientes del grupo
            $clientes = $grupo->clientes()->take(3)->get(); // Solo los primeros 3 para la prueba
            
            if ($clientes->count() < 2) {
                $this->command->warn('❌ El grupo necesita al menos 2 integrantes para la prueba.');
                return;
            }

            // Configurar participantes de prueba
            $participantes = [];
            foreach ($clientes as $index => $cliente) {
                $participacionTipo = $index < 2 ? 'retanquea' : 'no_retanquea'; // Los primeros 2 retanquean
                $montoSolicitado = $participacionTipo === 'retanquea' ? 500 : 0;
                
                $participantes[] = [
                    'cliente_id' => $cliente->id,
                    'participacion_tipo' => $participacionTipo,
                    'monto_solicitado' => $montoSolicitado,
                ];
                
                $this->command->info("👤 Cliente: {$cliente->persona->nombre} - {$participacionTipo} - S/ {$montoSolicitado}");
            }

            // Crear solicitud de retanqueo de prueba
            $retanqueo = $retanqueoWorkflow->crearSolicitudRetanqueo(
                $prestamo->id,
                $participantes,
                ['cantidad_cuotas' => 16]
            );

            $this->command->info("✅ Retanqueo creado exitosamente!");
            $this->command->info("📋 ID del Retanqueo: {$retanqueo->id}");
            $this->command->info("💰 Monto del nuevo préstamo: S/ " . number_format((float)$retanqueo->monto_retanqueo, 2));
            $this->command->info("🎯 Monto a entregar: S/ " . number_format((float)$retanqueo->monto_desembolsar, 2));
            $this->command->info("🔄 Estado: {$retanqueo->estado_retanqueo}");

            // Mostrar detalles de participantes
            $this->command->info("\n📊 PARTICIPANTES:");
            foreach ($retanqueo->retanqueosIndividuales as $individual) {
                $cliente = $individual->cliente;
                $this->command->info(sprintf(
                    "  - %s %s: %s - S/ %.2f solicitado - S/ %.2f cobertura - S/ %.2f a recibir",
                    $cliente->persona->nombre,
                    $cliente->persona->apellidos,
                    $individual->participacion_tipo,
                    (float)$individual->monto_solicitado,
                    (float)$individual->aporte_cobertura,
                    (float)$individual->monto_desembolsar
                ));
            }

            $this->command->info("\n🎉 ¡Seeder de prueba completado exitosamente!");
            $this->command->info("💡 Ahora puedes probar las funciones de aprobar y ejecutar desde el panel de administración.");

        } catch (\Exception $e) {
            $this->command->error("❌ Error al ejecutar el seeder: " . $e->getMessage());
            $this->command->error("📍 Archivo: " . $e->getFile() . " Línea: " . $e->getLine());
        }
    }
}
