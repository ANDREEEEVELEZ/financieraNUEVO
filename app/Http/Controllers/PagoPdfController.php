<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Pago;
use App\Models\Grupo;
use Barryvdh\DomPDF\Facade\Pdf;

class PagoPdfController extends Controller
{
    // Mostrar formulario con filtros y grupos según rol
    public function mostrarFormulario(Request $request)
    {
        $user = $request->user();

        if ($user->hasRole('Asesor')) {
            $grupos = Grupo::where('asesor_id', $user->id)
                        ->orderBy('nombre_grupo')
                        ->pluck('nombre_grupo', 'id'); // id como clave
        } else {
            $grupos = Grupo::orderBy('nombre_grupo')
                        ->pluck('nombre_grupo', 'id');
        }

        return view('pagos.formulario_exportar_pdf', compact('grupos'));
    }

    // Exportar PDF con filtros y control de acceso
    public function exportar(Request $request)
    {
        $user = $request->user();

        $query = Pago::query();

        if ($user->hasRole('Asesor')) {
            // Limitar pagos a los que pertenecen a los grupos del asesor
            $query->whereHas('cuotaGrupal.prestamo.grupo', function ($q) use ($user) {
                $q->where('asesor_id', $user->id);
            });
        }

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
