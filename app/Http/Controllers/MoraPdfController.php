<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CuotasGrupales;
use App\Models\Asesor; // Asegúrate de importar el modelo Asesor
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Response;
use Carbon\Carbon;

class MoraPdfController extends Controller
{
    public function exportar(Request $request)
    {
        $formato = $request->get('formato', 'pdf');

        if ($formato === 'excel') {
            return $this->exportarExcel($request);
        } else {
            return $this->exportarPDF($request);
        }
    }

    private function exportarExcel(Request $request)
    {
        $user = $request->user();

        $query = CuotasGrupales::with(['mora', 'prestamo.grupo'])
            ->whereHas('mora');

        // Filtro por rol Asesor: solo grupos propios
        if ($user->hasRole('Asesor')) {
            $asesor = Asesor::where('user_id', $user->id)->first();

            if ($asesor) {
                $query->whereHas('prestamo.grupo', function ($q) use ($asesor) {
                    $q->where('asesor_id', $asesor->id);
                });
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        // Aplicar filtros
        $this->aplicarFiltros($query, $request);

        $cuotas_mora = $query->get();

        // Crear CSV con delimitador punto y coma para Excel
        $csvContent = "\xEF\xBB\xBF"; // BOM para UTF-8

        if ($cuotas_mora->isEmpty()) {
            $csvContent .= "\"REPORTE DE MORAS\";\"SIN RESULTADOS\";\"\";\"\";\"\";\"\";\"\";\"\";\"\";\"\"\n";
            $csvContent .= "\"No se encontraron registros\";\"Fecha: " . now()->format('d/m/Y H:i:s') . "\";\"\";\"\";\"\";\"\";\"\";\"\";\"\";\"\"\n";
            $csvContent .= "\"\";\"\";\"\";\"\";\"\";\"\";\"\";\"\";\"\";\"\";\n";
        } else {
            // Encabezados de columnas completos según la tabla
            $csvContent .= "\"Nombre del Grupo\";\"N° Integrantes\";\"N° Cuota\";\"Monto de Cuota\";\"Fecha Vencimiento\";\"Saldo pendiente\";\"Días de Atraso\";\"Monto Mora pendiente\";\"Monto Mora Pagada\";\"Monto total a pagar\";\"Estado\"\n";

            foreach ($cuotas_mora as $cuota) {
                $nombreGrupo = $cuota->prestamo->grupo->nombre_grupo ?? '';
                $numeroIntegrantes = $cuota->prestamo->grupo->clientes()->count() ?? 0;
                $numeroCuota = $cuota->numero_cuota ?? '';
                $montoCuota = number_format($cuota->monto_cuota_grupal ?? 0, 2, ',', '');
                $fechaVencimiento = $cuota->fecha_vencimiento ? Carbon::parse($cuota->fecha_vencimiento)->format('d/m/Y') : '';
                $saldoPendiente = number_format($cuota->saldo_pendiente ?? 0, 2, ',', '');
                $diasAtraso = $cuota->mora->dias_atraso ?? 0;

                // Calcular monto mora pendiente
                $moraPendiente = $cuota->getSaldoMoraPendiente();
                $montoMoraPendiente = number_format($moraPendiente, 2, ',', '');

                // Calcular monto mora pagada
                $moraPagada = $cuota->getMoraPagada();
                $montoMoraPagada = number_format($moraPagada, 2, ',', '');

                // Calcular monto total a pagar (saldo cuota + mora pendiente)
                $saldoCuotaPendiente = $cuota->getSaldoCuotaPendiente();
                $montoTotal = $saldoCuotaPendiente + $moraPendiente;
                $montoTotalPagar = number_format($montoTotal, 2, ',', '');

                $estado = ucfirst(str_replace('_', ' ', $cuota->mora->estado_mora ?? ''));

                $csvContent .= "\"{$nombreGrupo}\";\"{$numeroIntegrantes}\";\"{$numeroCuota}\";\"{$montoCuota}\";\"{$fechaVencimiento}\";\"{$saldoPendiente}\";\"{$diasAtraso}\";\"{$montoMoraPendiente}\";\"{$montoMoraPagada}\";\"{$montoTotalPagar}\";\"{$estado}\"\n";
            }
        }

        $fechaActual = now()->format('Y-m-d_H-i-s');
        $nombreArchivo = "Reporte_Moras_{$cuotas_mora->count()}reg_{$fechaActual}.csv";

        return Response::make($csvContent, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$nombreArchivo}\"",
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0'
        ]);
    }

    private function exportarPDF(Request $request)
    {
        $user = $request->user();

        $query = CuotasGrupales::with(['mora', 'prestamo.grupo'])
            ->whereHas('mora');

        // Filtro por rol Asesor: solo grupos propios
        if ($user->hasRole('Asesor')) {
            $asesor = Asesor::where('user_id', $user->id)->first();

            if ($asesor) {
                $query->whereHas('prestamo.grupo', function ($q) use ($asesor) {
                    $q->where('asesor_id', $asesor->id);
                });
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        // Aplicar filtros
        $this->aplicarFiltros($query, $request);

        $cuotas_mora = $query->get();

        if ($cuotas_mora->isEmpty()) {
            $pdf = Pdf::loadHtml('<h2 style="color:#2563eb;text-align:center;margin-top:40px;">No hay registros de moras para los filtros seleccionados.</h2>');
            return $pdf->download('reporte_moras.pdf');
        }

        $pdf = Pdf::loadView('pdf.moras', compact('cuotas_mora'))
            -> setPaper('A4', 'landscape');
          // Renderiza el contenido
    $dompdf = $pdf->getDomPDF();
    $canvas = $dompdf->getCanvas();

    $canvas->page_script(function ($pageNumber, $pageCount, $canvas, $fontMetrics) {
        $text = "Página $pageNumber de $pageCount";
        $font = $fontMetrics->getFont('Helvetica', 'normal');
        $size = 10;
        $width = $fontMetrics->getTextWidth($text, $font, $size);
        $canvas->text(500 - $width, 820, $text, $font, $size);
    });

        return $pdf->download('reporte_moras.pdf');
    }

    private function aplicarFiltros($query, $request)
    {
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
        if ($request->filled('estado_mora') && $request->input('estado_mora') !== 'Todos los estados') {
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
    }
}
