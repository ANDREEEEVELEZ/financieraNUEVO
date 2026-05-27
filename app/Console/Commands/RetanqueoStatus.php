<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Contracts\RetanqueoQueryInterface;
use App\Models\Retanqueo;
use App\Models\Grupo;
use App\Models\Prestamo;

class RetanqueoStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'retanqueo:status {--grupo= : ID del grupo para filtrar}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Muestra el estado de los retanqueos y grupos elegibles';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('=== ESTADO DE RETANQUEOS ===');
        
        // Mostrar estadísticas generales
        $this->mostrarEstadisticas();
        
        // Mostrar grupos elegibles
        $this->mostrarGruposElegibles();
        
        // Mostrar retanqueos por estado
        $this->mostrarRetanqueosPorEstado();
        
        $this->info('=== FIN DEL REPORTE ===');
        
        return Command::SUCCESS;
    }

    private function mostrarEstadisticas()
    {
        $this->info("\n📊 ESTADÍSTICAS GENERALES:");
        
        $totalRetanqueos = Retanqueo::count();
        $pendientes = Retanqueo::where('estado_retanqueo', 'solicitud_pendiente')->count();
        $aprobados = Retanqueo::where('estado_retanqueo', 'aprobado')->count();
        $ejecutados = Retanqueo::where('estado_retanqueo', 'ejecutado')->count();
        $rechazados = Retanqueo::where('estado_retanqueo', 'rechazado')->count();
        
        $this->table(
            ['Métrica', 'Cantidad'],
            [
                ['Total Retanqueos', $totalRetanqueos],
                ['Solicitudes Pendientes', $pendientes],
                ['Aprobados', $aprobados],
                ['Ejecutados', $ejecutados],
                ['Rechazados', $rechazados],
            ]
        );
    }

    private function mostrarGruposElegibles()
    {
        $this->info("\n✅ GRUPOS ELEGIBLES PARA RETANQUEO:");
        
        $queryService = app(RetanqueoQueryInterface::class);
        $gruposElegibles = $queryService->obtenerGruposElegibles();

        if ($gruposElegibles->isEmpty()) {
            $this->warn('No hay grupos elegibles para retanqueo en este momento.');
            return;
        }

        $datos = [];
        foreach ($gruposElegibles as $grupo) {
            $prestamoActivo = $grupo->prestamos()
                ->where('estado', 'Aprobado')
                ->whereHas('cuotasGrupales', function ($q) {
                    $q->where('estado_pago', '!=', 'pagado')
                      ->where('saldo_pendiente', '>', 0);
                })
                ->first();

            if ($prestamoActivo) {
                $estadoPrestamo = $queryService->calcularEstadoPrestamo($prestamoActivo->id);
                $datos[] = [
                    $grupo->id,
                    $grupo->nombre_grupo,
                    'S/ ' . number_format($estadoPrestamo['saldo_pendiente_total'], 2),
                    $estadoPrestamo['cuotas_pagadas'] . '/' . $estadoPrestamo['cuotas_total'],
                    $grupo->clientes()->count() . ' integrantes'
                ];
            }
        }

        if (!empty($datos)) {
            $this->table(
                ['ID', 'Grupo', 'Saldo Pendiente', 'Cuotas', 'Integrantes'],
                $datos
            );
        }
    }

    private function mostrarRetanqueosPorEstado()
    {
        $this->info("\n📋 RETANQUEOS POR ESTADO:");
        
        $estados = ['solicitud_pendiente', 'aprobado', 'ejecutado', 'rechazado'];
        
        foreach ($estados as $estado) {
            $retanqueos = Retanqueo::with(['prestamoAntiguo.grupo'])
                ->where('estado_retanqueo', $estado)
                ->get();
            
            if ($retanqueos->isNotEmpty()) {
                $titulo = match($estado) {
                    'solicitud_pendiente' => '⏳ SOLICITUDES PENDIENTES',
                    'aprobado' => '✅ APROBADOS',
                    'ejecutado' => '🎯 EJECUTADOS',
                    'rechazado' => '❌ RECHAZADOS',
                    default => strtoupper($estado)
                };
                
                $this->info("\n{$titulo}:");
                
                $datos = [];
                foreach ($retanqueos as $retanqueo) {
                    $grupo = $retanqueo->prestamoAntiguo?->grupo;
                    $datos[] = [
                        $retanqueo->id,
                        $grupo ? $grupo->nombre_grupo : 'Sin grupo',
                        'S/ ' . number_format((float)$retanqueo->monto_retanqueo, 2),
                        'S/ ' . number_format((float)$retanqueo->monto_desembolsar, 2),
                        $retanqueo->created_at->format('d/m/Y'),
                    ];
                }
                
                $this->table(
                    ['ID', 'Grupo', 'Monto Total', 'A Entregar', 'Fecha'],
                    $datos
                );
            }
        }
    }
}
