<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Cuotas_Grupales;
use Barryvdh\DomPDF\Facade\Pdf;

class MoraPdfController extends Controller
{
    public function exportar(Request $request)
    {
        $query = Cuotas_Grupales::with(['mora', 'prestamo.grupo'])
            ->whereHas('mora');

        // Filtros dinámicos exactos
        if ($request->filled('grupo')) {
            $query->whereHas('prestamo.grupo', function ($q) use ($request) {
                $q->where('nombre_grupo', 'like', '%' . $request->input('grupo') . '%');
            });
        }
        if ($request->filled('desde')) {
            $query->whereDate('fecha_vencimiento', '>=', $request->input('desde'));
        }
        if ($request->filled('hasta')) {
            $query->whereDate('fecha_vencimiento', '<=', $request->input('hasta'));
        }
        if ($request->filled('monto')) {
            $query->where('monto_cuota_grupal', '>=', $request->input('monto'));
        }
        if ($request->filled('estado_mora')) {
            $estado = $request->input('estado_mora');
            $estadosValidos = ['pendiente', 'pagada', 'parcial'];
            if (in_array($estado, $estadosValidos)) {
                $query->whereHas('mora', function ($q) use ($estado) {
                    $q->where('estado_mora', $estado);
                });
            }
        }
        $cuotas_mora = $query->get();

        if ($cuotas_mora->isEmpty()) {
            $pdf = Pdf::loadHtml('<h2 style="color:#2563eb;text-align:center;margin-top:40px;">No hay registros de moras para los filtros seleccionados.</h2>');
            return $pdf->download('reporte_moras.pdf');
        }

        $pdf = Pdf::loadView('pdf.moras', compact('cuotas_mora'));
        return $pdf->download('reporte_moras.pdf');
    }
}
