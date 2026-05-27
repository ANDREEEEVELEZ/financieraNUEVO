<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Resources\Api\ClienteResource;
use App\Http\Responses\ApiResponse;
use App\Models\Cliente;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ClienteController extends Controller
{
    /**
     * GET /api/clientes
     *
     * Returns a paginated list of clients visible to the authenticated user.
     * scopeVisiblePorUsuario handles role branching internally:
     *   - Asesor → own clients only
     *   - JC / JO / SA → all clients
     *   - Unknown roles → empty (1=0)
     */
    public function index(Request $request): JsonResponse
    {
        $paginator = Cliente::visiblePorUsuario($request->user())
            ->with([
                'persona',
                'scoring' => fn ($q) => $q->where('vigente', true),
            ])
            ->paginate(15);

        // Transform items through ClienteResource before building the response.
        $data = ClienteResource::collection($paginator->items())->resolve();

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
     * GET /api/clientes/{cliente}
     *
     * Returns client detail. MUST apply scope — prevents cross-asesor access.
     * Returns 404 when the client does not belong to the requesting asesor's portfolio.
     */
    public function show(Request $request, Cliente $cliente): JsonResponse
    {
        $cliente = Cliente::visiblePorUsuario($request->user())
            ->with([
                'persona',
                'scoring'  => fn ($q) => $q->where('vigente', true),
                'grupos',
                'prestamos' => fn ($q) => $q->latest()->limit(5),
            ])
            ->findOrFail($cliente->id);

        return ApiResponse::success(new ClienteResource($cliente));
    }
}
