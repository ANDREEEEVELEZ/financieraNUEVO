<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\CuotaResource;
use App\Http\Responses\ApiResponse;
use App\Models\CuotaIndividual;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CuotaController extends Controller
{
    /**
     * List pending cuotas for the authenticated user's clients.
     * Asesor sees only cuotas in their own portfolio.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = CuotaIndividual::with(['prestamo.grupo', 'cliente.persona'])
            ->pendientes();

        if ($user->hasRole('Asesor') && $user->asesor) {
            $query->ofAsesor($user->asesor);
        }

        $paginator = $query->paginate(15);

        $data = $paginator->getCollection()
            ->map(fn ($c) => (new CuotaResource($c))->resolve())
            ->values()
            ->all();

        return response()->json([
            'success' => true,
            'data'    => $data,
            'message' => null,
            'meta'    => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
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

        if ($user->hasRole('Asesor') && $user->asesor) {
            $query->ofAsesor($user->asesor);
        }

        return ApiResponse::success(CuotaResource::collection($query->get()));
    }
}
