<?php

namespace App\Http\Controllers\Api;

use App\Contracts\PagoServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Pago\StorePagoRequest;
use App\Http\Resources\Api\PagoResource;
use App\Http\Responses\ApiResponse;
use App\Models\CuotasGrupales;
use App\Models\Pago;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PagoController extends Controller
{
    public function __construct(
        private readonly PagoServiceInterface $pagoService,
    ) {}

    /**
     * List pagos visible to the authenticated user.
     * Asesor sees only pagos for their own portfolio.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $pagos = Pago::query()
            ->when(
                $user->hasRole('Asesor'),
                fn ($q) => $q->whereHas(
                    'cuotaGrupal.prestamo.grupo',
                    fn ($g) => $g->where('asesor_id', $user->asesor?->id)
                )
            )
            ->with(['cuotaGrupal.prestamo.grupo'])
            ->latest('fecha_pago')
            ->paginate(15);

        $data = $pagos->getCollection()
            ->map(fn ($p) => (new PagoResource($p))->resolve())
            ->values()
            ->all();

        return response()->json([
            'success' => true,
            'data'    => $data,
            'message' => null,
            'meta'    => [
                'current_page' => $pagos->currentPage(),
                'last_page'    => $pagos->lastPage(),
                'per_page'     => $pagos->perPage(),
                'total'        => $pagos->total(),
            ],
        ]);
    }

    /**
     * Create a new pago record.
     * Creation and approval are separate operations.
     */
    public function store(StorePagoRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', Pago::class);

            $validated = $request->validated();
            $user      = $request->user();

            if ($user->hasRole('Asesor')) {
                $owned = CuotasGrupales::query()
                    ->where('id', $validated['cuota_grupal_id'])
                    ->whereHas('prestamo.grupo', fn ($g) => $g->where('asesor_id', $user->asesor?->id))
                    ->exists();

                if (! $owned) {
                    throw new AuthorizationException('Cuota grupal fuera del alcance del asesor.');
                }
            }

            $pago = Pago::create([
                'cuota_grupal_id'  => $validated['cuota_grupal_id'],
                'monto_pagado'     => $validated['monto_pagado'],
                'tipo_pago'        => $validated['tipo_pago'],
                'codigo_operacion' => $validated['codigo_operacion'] ?? null,
                'observaciones'    => $validated['observaciones'] ?? null,
                'fecha_pago'       => now(),
            ]);

            return ApiResponse::success(new PagoResource($pago), null, 201);
        } catch (AuthorizationException $e) {
            return ApiResponse::error('No autorizado.', 403);
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }
    }

    /**
     * Approve a pago (JO or SA only).
     * Delegates to PagoService::aprobarPago().
     */
    public function aprobar(Request $request, Pago $pago): JsonResponse
    {
        try {
            $this->authorize('aprobar', $pago);

            $this->pagoService->aprobarPago($pago);

            return ApiResponse::success(new PagoResource($pago->fresh()));
        } catch (AuthorizationException $e) {
            return ApiResponse::error('No autorizado.', 403);
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }
    }

    /**
     * Revert an approved pago (JO or SA only).
     * Delegates to PagoService::revertirPago().
     */
    public function revertir(Request $request, Pago $pago): JsonResponse
    {
        try {
            $this->authorize('revertir', $pago);

            $this->pagoService->revertirPago($pago);

            return ApiResponse::success(new PagoResource($pago->fresh()));
        } catch (AuthorizationException $e) {
            return ApiResponse::error('No autorizado.', 403);
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }
    }
}
