<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Asesor;
use App\Models\MetricaDiariaAsesor;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class ActualizarMetricasDiariasJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $fecha = today();

        User::whereHas('asesor')->chunk(50, function ($users) use ($fecha) {
            foreach ($users as $user) {
                $asesorId = Asesor::where('user_id', $user->id)->value('id');

                if ($asesorId === null) {
                    continue;
                }

                // Req 2 item 10 (11th reader, confirmed): excluye aplicaciones de
                // origen retanqueo — la cobertura de deuda no es cobranza cobrada.
                $cobrado = (float) DB::table('aplicacion_pago')
                    ->join('pagos', 'pagos.id', '=', 'aplicacion_pago.pago_id')
                    ->join('cuotas_grupales', 'cuotas_grupales.id', '=', 'pagos.cuota_grupal_id')
                    ->join('prestamos', 'prestamos.id', '=', 'cuotas_grupales.prestamo_id')
                    ->join('grupos', 'grupos.id', '=', 'prestamos.grupo_id')
                    ->where('grupos.asesor_id', $asesorId)
                    ->where('aplicacion_pago.tipo_aplicacion', 'cobranza')
                    ->whereDate('pagos.fecha_pago', $fecha)
                    ->selectRaw('SUM(aplicacion_pago.monto_aplicado_capital + aplicacion_pago.monto_aplicado_interes + aplicacion_pago.monto_aplicado_mora) as total')
                    ->value('total') ?? 0.0;

                MetricaDiariaAsesor::updateOrCreate(
                    ['asesor_id' => $user->id, 'fecha' => $fecha->toDateString()],
                    ['cobrado'   => $cobrado]
                );

                Cache::forget("meta_cobranza:{$user->id}:{$fecha->format('Y-m')}");
                Cache::forget("cartera_resumen:{$user->id}");
            }
        });
    }
}
