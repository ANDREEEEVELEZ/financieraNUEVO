<?php

namespace App\Services;

use App\Models\Cliente;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class PepDocumentService
{
    public function generatePdf(Cliente $cliente)
    {
        $data = [
            'cliente' => $cliente,
            'persona' => $cliente->persona,
            'fecha_generacion' => Carbon::now()->format('d/m/Y'),
            'fecha_generacion_completa' => Carbon::now()->format('d \d\e F \d\e Y'),
            'pep_data' => [], // Datos vacíos para compatibilidad
        ];

        $pdf = Pdf::loadView('pdf.pep-declaration', $data);
        $pdf->setPaper('A4', 'portrait');
        
        return $pdf->output();
    }

    public function generatePdfWithData(Cliente $cliente, array $pepData)
    {
        $data = [
            'cliente' => $cliente,
            'persona' => $cliente->persona,
            'fecha_generacion' => Carbon::now()->format('d/m/Y'),
            'fecha_generacion_completa' => Carbon::now()->format('d \d\e F \d\e Y'),
            'pep_data' => $pepData,
        ];

        $pdf = Pdf::loadView('pdf.pep-declaration', $data);
        $pdf->setPaper('A4', 'portrait');
        
        return $pdf->output();
    }
}
