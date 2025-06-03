<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Pago;
use Barryvdh\DomPDF\Facade\Pdf;

class PagoPdfController extends Controller
{
    public function exportar(Request $request)
    {
        $user = $request->user();
        $query = Pago::query();

        // Si es un asesor, solo mostrar los pagos de sus grupos
        if ($user->hasRole('Asesor')) {
            $asesor = \App\Models\Asesor::where('user_id', $user->id)->first();
            if ($asesor) {
                $query->whereHas('cuotaGrupal.prestamo.grupo', function ($q) use ($asesor) {
                    $q->where('asesor_id', $asesor->id);
                });
            }
        }
        // Si es Jefe de Créditos o Jefe de Operaciones, puede ver todos los pagos
        elseif (!$user->hasAnyRole(['super_admin', 'Jefe de Operaciones', 'Jefe de Creditos'])) {
            return response()->json(['error' => 'No autorizado'], 403);
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

        $pdf = Pdf::loadView('pdf.pagos', compact('pagos'));
        return $pdf->download('reporte_pagos.pdf');
    }
}
