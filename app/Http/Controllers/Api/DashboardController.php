<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Asesor;
use App\Models\CuotaIndividual;
use App\Domain\Cartera\CarteraService;
use App\Domain\Cartera\MetaCobranzaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DashboardController extends Controller
{
    /**
     * GET /api/dashboard
     *
     * Accessible only by Asesor and super_admin.
     * JC / JO receive 403.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        // Role gate: only Asesor and super_admin may access the dashboard.
        if (! $user->hasAnyRole(['Asesor', 'super_admin'])) {
            return ApiResponse::error('No autorizado.', 403);
        }

        $asesor   = Asesor::where('user_id', $user->id)->first();
        $cartera  = app(CarteraService::class)->resumen($user);
        $metaData = app(MetaCobranzaService::class)->calcular($user, now()->startOfMonth());

        // cuotas_hoy / cuotas_mora: apply asesor scope when user is an Asesor.
        // For SA (no asesor record) these are always 0 — dashboard is asesor-scoped.
        if ($asesor !== null) {
            $cuotasHoy  = CuotaIndividual::ofAsesor($asesor)->dueToday()->count();
            $cuotasMora = CuotaIndividual::ofAsesor($asesor)->enMora()->count();
        } else {
            $cuotasHoy  = 0;
            $cuotasMora = 0;
        }

        return ApiResponse::success([
            'cartera_total'  => $cartera['cartera_total'],
            'grupos_count'   => $cartera['grupos_count'],
            'clientes_count' => $cartera['clientes_count'],
            'cuotas_hoy'     => $cuotasHoy,
            'cuotas_mora'    => $cuotasMora,
            'meta_mensual'   => [
                'monto'      => $metaData['meta'],
                'cobrado'    => $metaData['cobrado'],
                'porcentaje' => $metaData['pct'],
            ],
        ]);
    }
}
