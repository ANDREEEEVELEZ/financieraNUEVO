<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Asesor;
use App\Models\Cliente;
use App\Models\CuotaIndividual;
use App\Models\Grupo;
use App\Models\Prestamo;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

final class CarteraService
{
    public function resumen(User $user): array
    {
        $key = "cartera_resumen:{$user->id}";

        $asesor = Asesor::where('user_id', $user->id)->first();

        $numericos = Cache::remember($key, 300, function () use ($asesor) {
            if ($asesor === null) {
                return [
                    'grupos_count'    => 0,
                    'clientes_count'  => 0,
                    'prestamos_count' => 0,
                    'cartera_total'   => 0.0,
                ];
            }

            return [
                'grupos_count'    => Grupo::where('asesor_id', $asesor->id)->count(),
                'clientes_count'  => Cliente::ofAsesor($asesor)->activo()->count(),
                'prestamos_count' => Prestamo::ofAsesor($asesor)->activo()->count(),
                'cartera_total'   => (float) CuotaIndividual::whereHas(
                    'prestamo',
                    fn ($q) => $q->ofAsesor($asesor)->activo()
                )->sum('saldo_capital'),
            ];
        });

        // cuotas_hoy: NEVER cached — always a live query
        $numericos['cuotas_hoy'] = $asesor !== null
            ? CuotaIndividual::dueToday()
                ->ofAsesor($asesor)
                ->with(['prestamo.cliente.persona', 'prestamo.grupo'])
                ->select(['id', 'prestamo_id', 'numero_cuota', 'saldo_capital', 'fecha_vencimiento', 'estado'])
                ->get()
            : collect();

        return $numericos;
    }
}
