<?php

declare(strict_types=1);

namespace App\Domain\Cartera;

use App\Models\Asesor;
use App\Models\Cliente;
use App\Models\CuotaIndividual;
use App\Models\Grupo;
use App\Models\Prestamo;
use App\Models\User;
use App\Contracts\CacheServiceInterface;
use Illuminate\Support\Facades\Cache;

final class CarteraService
{
    public function __construct(private CacheServiceInterface $cache) {}

    public function resumen(User $user): array
    {
        $primaryRole = $user->primary_role;
        $isJefeOrAdmin = in_array($primaryRole, ['super_admin', 'Jefe de creditos', 'Jefe de operaciones'], true);
        $asesor = $this->cache->getAsesorByUserId($user->id);

        $key = "cartera_resumen:{$user->id}";

        $numericos = Cache::remember($key, 300, function () use ($asesor, $isJefeOrAdmin) {
            if ($isJefeOrAdmin || $asesor === null) {
                return [
                    'grupos_count'    => Grupo::count(),
                    'clientes_count'  => Cliente::count(),
                    'prestamos_count' => Prestamo::where('estado', 'Activo')->count(),
                    'cartera_total'   => (float) CuotaIndividual::whereHas(
                        'prestamo',
                        fn ($q) => $q->where('estado', 'Activo')
                    )->sum('saldo_capital'),
                ];
            }

            return [
                'grupos_count'    => Grupo::where('asesor_id', $asesor->id)->count(),
                'clientes_count'  => Cliente::ofAsesor($asesor)->count(),
                'prestamos_count' => Prestamo::ofAsesor($asesor)->where('estado', 'Activo')->count(),
                'cartera_total'   => (float) CuotaIndividual::whereHas(
                    'prestamo',
                    fn ($q) => $q->ofAsesor($asesor)->where('estado', 'Activo')
                )->sum('saldo_capital'),
            ];
        });

        // Live queries for time-sensitive metrics
        if ($isJefeOrAdmin || $asesor === null) {
            $numericos['cuotas_hoy']  = CuotaIndividual::dueToday()->get();
            $numericos['cuotas_mora'] = CuotaIndividual::enMora()->count();
        } else {
            $numericos['cuotas_hoy']  = CuotaIndividual::dueToday()->ofAsesor($asesor)->get();
            $numericos['cuotas_mora'] = CuotaIndividual::enMora()->ofAsesor($asesor)->count();
        }

        return $numericos;
    }
}
