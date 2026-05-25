<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Asesor;
use App\Models\MetaMensual;
use App\Models\MetricaDiariaAsesor;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class MetaCobranzaService
{
    public function calcular(User $user, Carbon $mes): array
    {
        $key = "meta_cobranza:{$user->id}:{$mes->format('Y-m')}";

        return Cache::remember($key, 300, function () use ($user, $mes) {
            // Fast path: snapshot table keyed by users.id
            $cobrado = (float) MetricaDiariaAsesor::where('asesor_id', $user->id)
                ->whereYear('fecha', $mes->year)
                ->whereMonth('fecha', $mes->month)
                ->sum('cobrado');

            // Fallback: live JOIN through aplicacion_pago → pagos → cuotas_grupales
            // → prestamos → grupos where grupos.asesor_id = asesores.id for this user
            if ($cobrado == 0.0) {
                $asesorId = Asesor::where('user_id', $user->id)->value('id');

                if ($asesorId !== null) {
                    $cobrado = (float) DB::table('aplicacion_pago')
                        ->join('pagos', 'pagos.id', '=', 'aplicacion_pago.pago_id')
                        ->join('cuotas_grupales', 'cuotas_grupales.id', '=', 'pagos.cuota_grupal_id')
                        ->join('prestamos', 'prestamos.id', '=', 'cuotas_grupales.prestamo_id')
                        ->join('grupos', 'grupos.id', '=', 'prestamos.grupo_id')
                        ->where('grupos.asesor_id', $asesorId)
                        ->whereYear('pagos.fecha_pago', $mes->year)
                        ->whereMonth('pagos.fecha_pago', $mes->month)
                        ->selectRaw('SUM(aplicacion_pago.monto_aplicado_capital + aplicacion_pago.monto_aplicado_interes + aplicacion_pago.monto_aplicado_mora) as total')
                        ->value('total') ?? 0.0;
                }
            }

            $meta = (float) (MetaMensual::where('asesor_id', $user->id)
                ->where('anio', $mes->year)
                ->where('mes', $mes->month)
                ->value('meta_monto') ?? 25000.0);

            $pct = $meta > 0 ? round(($cobrado / $meta) * 100, 1) : 0.0;

            return [
                'meta'    => $meta,
                'cobrado' => $cobrado,
                'pct'     => $pct,
            ];
        });
    }
}
