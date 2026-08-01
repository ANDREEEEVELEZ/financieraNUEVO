<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\CuotaIndividual;
use App\Models\CuotasGrupales;
use App\Models\Mora;
use App\Models\Prestamo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class ActualizarMorasCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'moras:actualizar';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Ejecuta el snapshot nocturno de moras en DB (ACID) para cuotas vencidas.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Iniciando snapshot nocturno de moras...');

        $prestamosActivos = Prestamo::query()
            ->where('estado', 'Activo')
            ->pluck('id');

        if ($prestamosActivos->isEmpty()) {
            $this->info('No hay préstamos activos para procesar.');
            return Command::SUCCESS;
        }

        $cuotas = CuotasGrupales::query()
            ->whereIn('prestamo_id', $prestamosActivos)
            ->where('fecha_vencimiento', '<', now()->toDateString())
            ->where('estado_pago', '!=', 'pagado')
            ->get();

        $procesadas = 0;
        $fallidas = 0;

        foreach ($cuotas as $cuota) {
            try {
                DB::transaction(function () use ($cuota, &$procesadas) {
                    $cuotaBloqueada = CuotasGrupales::query()
                        ->whereKey($cuota->id)
                        ->lockForUpdate()
                        ->firstOrFail();

                    $diasAtraso = (int) now()->startOfDay()->diffInDays($cuotaBloqueada->fecha_vencimiento->startOfDay(), absolute: true);
                    $moraCalculada = Mora::calcularMontoMora($cuotaBloqueada, now(), 'pendiente');

                    Mora::updateOrCreate(
                        ['cuota_grupal_id' => $cuotaBloqueada->id],
                        [
                            'fecha_atraso'        => $cuotaBloqueada->fecha_vencimiento->toDateString(),
                            'dias_atraso'          => $diasAtraso,
                            'monto_mora_generada' => $moraCalculada,
                            'estado_mora'         => 'pendiente',
                            'fecha_snapshot'      => now()->toDateString(),
                        ]
                    );

                    $procesadas++;
                });
            } catch (\Throwable $e) {
                $fallidas++;
                Log::error('ActualizarMorasCommand: fallo al procesar cuota, se continúa con las restantes', [
                    'cuota_grupal_id' => $cuota->id,
                    'error'           => $e->getMessage(),
                ]);
            }
        }

        $this->info("Snapshot nocturno completado. Cuotas procesadas: {$procesadas}, fallidas: {$fallidas}");
        Log::info('ActualizarMorasCommand completado', ['cuotas_procesadas' => $procesadas, 'cuotas_fallidas' => $fallidas]);

        return $fallidas === 0 ? Command::SUCCESS : Command::FAILURE;
    }
}
