<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use Illuminate\Http\Request;
use Dompdf\Dompdf;
use Dompdf\Options;
use Carbon\Carbon;

class DeclaracionJuradaTestController extends Controller
{
    public function test()
    {
        // Configurar opciones de DOMPDF de forma básica
        $options = new Options();
        $options->set('defaultFont', 'Arial');
        $options->set('isHtml5ParserEnabled', false);
        $options->set('isPhpEnabled', false);
        $options->set('isRemoteEnabled', false);
        
        $dompdf = new Dompdf($options);
        
        // HTML básico para prueba
        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Test PDF</title>
            <style>
                body { font-family: Arial, sans-serif; }
                .header { text-align: center; font-weight: bold; }
            </style>
        </head>
        <body>
            <div class="header">DECLARACION JURADA DE PRUEBA</div>
            <p>Esta es una prueba de generacion de PDF sin caracteres especiales.</p>
            <p>Fecha: ' . date('d/m/Y') . '</p>
        </body>
        </html>';
        
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        
        return response($dompdf->output())
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="test_declaracion.pdf"');
    }
}
