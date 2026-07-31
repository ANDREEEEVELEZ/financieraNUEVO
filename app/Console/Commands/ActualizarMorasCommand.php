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

        $procesadas = 0;

        DB::transaction(function () use ($prestamosActivos, &$procesadas) {
            $cuotas = CuotasGrupales::query()
                ->whereIn('prestamo_id', $prestamosActivos)
                ->where('fecha_vencimiento', '<', now()->toDateString())
                ->where('estado_pago', '!=', 'pagado')
                ->lockForUpdate()
                ->get();

            foreach ($cuotas as $cuota) {
                $diasAtraso = (int) now()->startOfDay()->diffInDays($cuota->fecha_vencimiento->startOfDay(), absolute: true);
                $moraCalculada = Mora::calcularMontoMora($cuota, now(), 'pendiente');

                Mora::updateOrCreate(
                    ['cuota_grupal_id' => $cuota->id],
                    [
                        'fecha_atraso'        => $cuota->fecha_vencimiento->toDateString(),
                        'dias_atraso'          => $diasAtraso,
                        'monto_mora_generada' => $moraCalculada,
                        'estado_mora'         => 'pendiente',
                        'fecha_snapshot'      => now()->toDateString(),
                    ]
                );

                $procesadas++;
            }
        });

        $this->info("Snapshot nocturno completado. Cuotas procesadas: {$procesadas}");
        Log::info('ActualizarMorasCommand completado exitosamente', ['cuotas_procesadas' => $procesadas]);

        return Command::SUCCESS;
    }
}
