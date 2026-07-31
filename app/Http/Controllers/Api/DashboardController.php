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

        // Role gate: Asesor, JC, JO, super_admin are authorized.
        if (! $user->hasAnyRole(['Asesor', 'Jefe de creditos', 'Jefe de operaciones', 'super_admin'])) {
            return ApiResponse::error('No autorizado.', 403);
        }

        $cartera  = app(CarteraService::class)->resumen($user);
        $metaData = app(MetaCobranzaService::class)->calcular($user, now()->startOfMonth());

        $cuotasHoyCount = is_countable($cartera['cuotas_hoy'] ?? null)
            ? count($cartera['cuotas_hoy'])
            : (int) ($cartera['cuotas_hoy'] ?? 0);

        return ApiResponse::success([
            'rol_activo'     => $user->primary_role,
            'cartera_total'  => (float) $cartera['cartera_total'],
            'grupos_count'   => (int) $cartera['grupos_count'],
            'clientes_count' => (int) $cartera['clientes_count'],
            'cuotas_hoy'     => $cuotasHoyCount,
            'cuotas_mora'    => (int) ($cartera['cuotas_mora'] ?? 0),
            'meta_mensual'   => [
                'monto'      => (float) $metaData['meta'],
                'cobrado'    => (float) $metaData['cobrado'],
                'porcentaje' => (float) $metaData['pct'],
            ],
        ]);
    }
}
