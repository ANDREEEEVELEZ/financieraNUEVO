<?php

namespace App\Http\Controllers;

use App\Http\Concerns\AsesorScopeGuard;
use App\Http\Responses\ApiResponse;
use App\Models\Pago;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;

class PagoPdfController extends Controller
{
    use AsesorScopeGuard;

    public function exportar(Request $request)
    {
        $request->validate([
            'grupo' => ['nullable', 'integer', 'min:1'],
            'from' => ['nullable', 'date'],
            'until' => ['nullable', 'date', 'after_or_equal:from'],
            'estado_pago' => ['nullable', 'string', 'in:aprobado,pendiente,rechazado,parcial'],
        ]);

        $user = $request->user();
        // Req 2 item 7: reporte de "pagos registrados" es cobranza-facing —
        // excluye pagos de origen retanqueo (cobertura de deuda, no cobranza).
        $query = Pago::query()->cobranza();

        // Si es un asesor, solo mostrar los pagos de sus grupos
        if ($user->hasRole('Asesor')) {
            try {
                $asesor = $this->resolveAsesorOrAbort($user);
            } catch (AuthorizationException $e) {
                return ApiResponse::error($e->getMessage(), 403);
            }

            $query->whereHas('cuotaGrupal.prestamo.grupo', function ($q) use ($asesor) {
                $q->where('asesor_id', $asesor->id);
            });
        }
        // Si es Jefe de Créditos o Jefe de Operaciones, puede ver todos los pagos
        elseif (! $user->hasAnyRole(['super_admin', 'Jefe de operaciones', 'Jefe de creditos'])) {
            return ApiResponse::error('No autorizado.', 403);
        }

        // Aplicar filtros adicionales
        if ($request->filled('grupo')) {
            $query->whereHas('cuotaGrupal.prestamo.grupo', function ($q) use ($request) {
                $q->where('id', $request->input('grupo'));
            });
        }

        if ($request->filled('from')) {
            $query->whereDate('fecha_pago', '>=', $request->input('from'));
        }
        if ($request->filled('until')) {
            $query->whereDate('fecha_pago', '<=', $request->input('until'));
        }
        if ($request->filled('estado_pago') && $request->input('estado_pago') !== '') {
            $query->where('estado_pago', $request->input('estado_pago'));
        }

        $pagos = $query->with(['cuotaGrupal.prestamo.grupo'])->get();

        // isPhpEnabled must remain false (default from config/dompdf.php) — PHP in PDF is an RCE vector
        $pdf = Pdf::loadView('pdf.pagos', compact('pagos'));

        return $pdf->download('reporte_pagos.pdf');
    }
}
