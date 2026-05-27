<?php

namespace App\Http\Controllers\Api;

use App\Contracts\AuditServiceInterface;
use App\Events\Domain\PrestamoDesembolsado;
use App\Events\Domain\PrestamoFirmado;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Prestamo\StorePrestamoRequest;
use App\Http\Resources\Api\PrestamoResource;
use App\Http\Responses\ApiResponse;
use App\Models\Prestamo;
use App\Models\PrestamoIndividual;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class PrestamoController extends Controller
{
    public function __construct(
        private readonly AuditServiceInterface $auditService,
    ) {}

    /**
     * List all prestamos visible to the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $prestamos = Prestamo::query()
                ->visiblePorUsuario($user)
                ->with(['grupo'])
                ->paginate(15);

            $data = $prestamos->getCollection()
                ->map(fn ($p) => (new PrestamoResource($p->load('grupo')))->resolve())
                ->values()
                ->all();

            return response()->json([
                'success' => true,
                'data'    => $data,
                'message' => null,
                'meta'    => [
                    'current_page' => $prestamos->currentPage(),
                    'last_page'    => $prestamos->lastPage(),
                    'per_page'     => $prestamos->perPage(),
                    'total'        => $prestamos->total(),
                ],
            ]);
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }
    }

    /**
     * Show a single prestamo.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        try {
            $user = $request->user();

            $prestamo = Prestamo::query()
                ->visiblePorUsuario($user)
                ->with([
                    'grupo',
                    'cuotasGrupales' => fn ($q) => $q->orderBy('numero_cuota')->limit(3),
                    'prestamoIndividual',
                ])
                ->findOrFail($id);

            return ApiResponse::success(new PrestamoResource($prestamo));
        } catch (AuthorizationException $e) {
            return ApiResponse::error('No autorizado.', 403);
        } catch (ModelNotFoundException $e) {
            return ApiResponse::error('Recurso no encontrado.', 404);
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }
    }

    /**
     * Create a new prestamo.
     */
    public function store(StorePrestamoRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();

            $prestamo = DB::transaction(function () use ($validated) {
                $prestamo = Prestamo::create([
                    'grupo_id'             => $validated['grupo_id'],
                    'producto_id'          => $validated['producto_id'],
                    'monto_prestado_total' => $validated['monto_prestado_total'],
                    'cantidad_cuotas'      => $validated['cantidad_cuotas'],
                    'frecuencia'           => $validated['frecuencia'],
                    'fecha_prestamo'       => $validated['fecha_prestamo'],
                    'estado'               => Prestamo::ESTADO_PENDIENTE,
                ]);

                foreach ($validated['montos_individuales'] as $individual) {
                    PrestamoIndividual::create([
                        'prestamo_id'               => $prestamo->id,
                        'cliente_id'                => $individual['cliente_id'],
                        'monto_prestado_individual' => $individual['monto'],
                        'estado'                    => Prestamo::ESTADO_PENDIENTE,
                    ]);
                }

                return $prestamo;
            });

            return ApiResponse::success(new PrestamoResource($prestamo), null, 201);
        } catch (AuthorizationException $e) {
            return ApiResponse::error('No autorizado.', 403);
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }
    }

    /**
     * Aprobar: Pendiente → Aprobado (JC, SA).
     */
    public function aprobar(Request $request, Prestamo $prestamo): JsonResponse
    {
        try {
            $this->authorize('aprobar', $prestamo);

            if ($prestamo->estado !== Prestamo::ESTADO_PENDIENTE) {
                return ApiResponse::error('Estado inválido para esta transición.', 422);
            }

            $estadoAnterior = $prestamo->estado;

            DB::transaction(function () use ($prestamo, $estadoAnterior) {
                $prestamo->update(['estado' => Prestamo::ESTADO_APROBADO]);

                $this->auditService->registrar(
                    'prestamo.aprobado',
                    $prestamo,
                    ['estado' => $estadoAnterior],
                    ['estado' => Prestamo::ESTADO_APROBADO],
                    ''
                );
            });

            return ApiResponse::success(new PrestamoResource($prestamo->fresh()));
        } catch (AuthorizationException $e) {
            return ApiResponse::error('No autorizado.', 403);
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }
    }

    /**
     * Rechazar: Pendiente → Rechazado (JC, SA). Requires motivo.
     */
    public function rechazar(Request $request, Prestamo $prestamo): JsonResponse
    {
        try {
            $this->authorize('rechazar', $prestamo);

            $validated = $request->validate(['motivo' => 'required|string|max:500']);

            if ($prestamo->estado !== Prestamo::ESTADO_PENDIENTE) {
                return ApiResponse::error('Estado inválido para esta transición.', 422);
            }

            $estadoAnterior = $prestamo->estado;

            DB::transaction(function () use ($prestamo, $validated, $estadoAnterior) {
                $prestamo->update([
                    'estado'      => Prestamo::ESTADO_RECHAZADO,
                    'descripcion' => $validated['motivo'],
                ]);

                $this->auditService->registrar(
                    'prestamo.rechazado',
                    $prestamo,
                    ['estado' => $estadoAnterior],
                    ['estado' => Prestamo::ESTADO_RECHAZADO],
                    $validated['motivo']
                );
            });

            return ApiResponse::success(new PrestamoResource($prestamo->fresh()));
        } catch (AuthorizationException $e) {
            return ApiResponse::error('No autorizado.', 403);
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }
    }

    /**
     * Firmar: Aprobado → Firmado (Asesor, SA).
     */
    public function firmar(Request $request, Prestamo $prestamo): JsonResponse
    {
        try {
            $this->authorize('firmar', $prestamo);

            if ($prestamo->estado !== Prestamo::ESTADO_APROBADO) {
                return ApiResponse::error('Estado inválido para esta transición.', 422);
            }

            $prestamo->update(['estado' => Prestamo::ESTADO_FIRMADO]);

            PrestamoFirmado::dispatch($prestamo);

            return ApiResponse::success(new PrestamoResource($prestamo->fresh()));
        } catch (AuthorizationException $e) {
            return ApiResponse::error('No autorizado.', 403);
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }
    }

    /**
     * Desembolsar: Firmado → Activo (JO, SA). Requires titular_cuenta and numero_cuenta.
     */
    public function desembolsar(Request $request, Prestamo $prestamo): JsonResponse
    {
        try {
            $this->authorize('desembolsar', $prestamo);

            $validated = $request->validate([
                'titular_cuenta' => 'required|string',
                'numero_cuenta'  => 'required|string',
            ]);

            if ($prestamo->estado !== Prestamo::ESTADO_FIRMADO) {
                return ApiResponse::error('Estado inválido para esta transición.', 422);
            }

            $estadoAnterior = $prestamo->estado;

            DB::transaction(function () use ($prestamo, $validated, $estadoAnterior) {
                $prestamo->update([
                    'estado'                    => Prestamo::ESTADO_ACTIVO,
                    'titular_cuenta_desembolso' => $validated['titular_cuenta'],
                    'numero_cuenta_desembolso'  => $validated['numero_cuenta'],
                    'fecha_desembolso'          => now()->toDateString(),
                ]);

                PrestamoDesembolsado::dispatch($prestamo);
            });

            return ApiResponse::success(new PrestamoResource($prestamo->fresh()));
        } catch (AuthorizationException $e) {
            return ApiResponse::error('No autorizado.', 403);
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }
    }
}
