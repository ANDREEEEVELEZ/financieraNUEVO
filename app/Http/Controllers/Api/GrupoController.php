<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\GrupoResource;
use App\Http\Responses\ApiResponse;
use App\Models\Grupo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class GrupoController extends Controller
{
    /**
     * GET /api/grupos
     *
     * Returns a paginated list of groups visible to the authenticated user.
     * scopeVisiblePorUsuario handles role branching internally:
     *   - Asesor → own groups only
     *   - JC / JO / SA → all groups
     *   - Unknown roles → empty (1=0)
     */
    public function index(Request $request): JsonResponse
    {
        $paginator = Grupo::visiblePorUsuario($request->user())
            ->with(['asesor'])
            ->paginate(15);

        // Transform items through GrupoResource before building the response.
        $data = GrupoResource::collection($paginator->items())->resolve();

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
     * GET /api/grupos/{grupo}
     *
     * Returns group detail with eager-loaded integrantes and active prestamo.
     * MUST apply scope — prevents cross-asesor access; returns 404 on mismatch.
     */
    public function show(Request $request, Grupo $grupo): JsonResponse
    {
        $grupo = Grupo::visiblePorUsuario($request->user())
            ->with([
                'clientes.persona',
                'prestamos' => fn ($q) => $q->where('estado', 'Activo'),
            ])
            ->findOrFail($grupo->id);

        return ApiResponse::success(new GrupoResource($grupo));
    }
}
