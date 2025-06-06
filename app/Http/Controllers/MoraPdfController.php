<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CuotasGrupales;
use App\Models\Asesor; // Asegúrate de importar el modelo Asesor
use Barryvdh\DomPDF\Facade\Pdf;

class MoraPdfController extends Controller
{
    public function exportar(Request $request)
    {
        $user = $request->user();

        $query = CuotasGrupales::with(['mora', 'prestamo.grupo'])
            ->whereHas('mora');

        // Filtro por rol Asesor: solo grupos propios
        if ($user->hasRole('Asesor')) {
            // CORRECCIÓN: Obtener el ID del asesor desde la tabla Asesor
            $asesor = Asesor::where('user_id', $user->id)->first();
            
            if ($asesor) {
                $query->whereHas('prestamo.grupo', function ($q) use ($asesor) {
                    $q->where('asesor_id', $asesor->id);
                });
            } else {
                // Si no se encuentra el asesor, no mostrar nada
                $query->whereRaw('1 = 0'); // Condición que siempre es falsa
            }
        }
        // Otros roles ven todos los grupos (sin filtro por asesor_id)

        // Filtros dinámicos
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
            // Mapear 'parcial' a 'parcialmente_pagada' para consistencia
            if ($estado === 'parcial') {
                $estado = 'parcialmente_pagada';
            }
            $estadosValidos = ['pendiente', 'pagada', 'parcialmente_pagada'];
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

        $pdf = Pdf::loadView('pdf.moras', compact('cuotas_mora'))
            -> setPaper('A4', 'portrait');
          // Renderiza el contenido
    $dompdf = $pdf->getDomPDF();
    $canvas = $dompdf->getCanvas();

    $canvas->page_script(function ($pageNumber, $pageCount, $canvas, $fontMetrics) {
        $text = "Página $pageNumber de $pageCount";
        $font = $fontMetrics->getFont('Helvetica', 'normal');
        $size = 10;
        $width = $fontMetrics->getTextWidth($text, $font, $size);
        $canvas->text(500 - $width, 820, $text, $font, $size); // ajusta la posición si es necesario
    });
        
        
            return $pdf->download('reporte_moras.pdf');
    }
}