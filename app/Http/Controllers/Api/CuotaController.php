<?php

namespace App\Http\Controllers\Api;

use App\Http\Concerns\AsesorScopeGuard;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\CuotaResource;
use App\Http\Responses\ApiResponse;
use App\Models\CuotaIndividual;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CuotaController extends Controller
{
    use AsesorScopeGuard;

    /**
     * List pending cuotas for the authenticated user's clients.
     * Asesor sees only cuotas in their own portfolio.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = CuotaIndividual::with(['prestamo.grupo', 'cliente.persona'])
            ->pendientes();

        if ($user->hasRole('Asesor')) {
            try {
                $query->ofAsesor($this->resolveAsesorOrAbort($user));
            } catch (AuthorizationException $e) {
                return ApiResponse::error($e->getMessage(), 403);
            }
        }

        $paginator = $query->paginate(15);

        $data = $paginator->getCollection()
            ->map(fn ($c) => (new CuotaResource($c))->resolve())
            ->values()
            ->all();

        return response()->json([
            'success' => true,
            'data' => $data,
            'message' => null,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /**
     * List cuotas due today for the authenticated asesor.
     */
    public function hoy(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = CuotaIndividual::with(['cliente.persona'])
            ->dueToday();

        if ($user->hasRole('Asesor')) {
            try {
                $query->ofAsesor($this->resolveAsesorOrAbort($user));
            } catch (AuthorizationException $e) {
                return ApiResponse::error($e->getMessage(), 403);
            }
        }

        return ApiResponse::success(CuotaResource::collection($query->get()));
    }
}
