<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Pago;
use Barryvdh\DomPDF\Facade\Pdf;

class PagoPdfController extends Controller
{
    public function exportar(Request $request)
    {
        $query = Pago::query();
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
