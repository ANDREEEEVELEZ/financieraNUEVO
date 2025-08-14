<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use Illuminate\Http\Request;
use Dompdf\Dompdf;
use Dompdf\Options;
use Carbon\Carbon;

class DeclaracionJuradaController extends Controller
{
    public function generar(Request $request, $clienteId)
    {
        // Validar los datos del formulario
        $validated = $request->validate([
            'es_pep' => 'required|in:si_soy,si_he_sido,no_soy,no_he_sido',
            'colaborador_directo' => 'required|in:si_soy,si_he_sido,no_soy,no_he_sido',
            'pariente_pep' => 'nullable|in:si_soy,no_soy',
            'parientes_data' => 'nullable|array',
            'telefono_fijo' => 'nullable|string|max:20',
            'proposito_relacion' => 'nullable|string|max:500',
            'observaciones' => 'nullable|string|max:1000',
            'conyuge_nombre' => 'nullable|string|max:255',
        ]);

        // Obtener el cliente con sus datos relacionados
        $cliente = Cliente::with('persona')->findOrFail($clienteId);
        
        // Configurar opciones de DOMPDF
        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isPhpEnabled', true);
        $options->set('isRemoteEnabled', true);
        
        $dompdf = new Dompdf($options);
        
        // Generar el HTML del documento
        $html = $this->generarHtmlDeclaracion($cliente, $validated);
        
        // Cargar el HTML en DOMPDF
        $dompdf->loadHtml($html);
        
        // Establecer el tamaño de página
        $dompdf->setPaper('A4', 'portrait');
        
        // Renderizar el PDF
        $dompdf->render();
        
        // Generar nombre del archivo
        $nombreArchivo = 'Declaracion_Jurada_' . $cliente->persona->DNI . '_' . date('Y-m-d') . '.pdf';
        
        // Retornar el PDF para descarga
        return response($dompdf->output())
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="' . $nombreArchivo . '"');
    }

    private function generarHtmlDeclaracion($cliente, $datos)
    {
        $fechaActual = Carbon::now()->format('d/m/Y');
        
        // Determinar qué opciones están marcadas
        $dniMarcado = 'X';
        $pasaporteMarcado = '';
        $carneExtranjeraMarcado = '';
        $otroMarcado = '';
        
        // Estado civil
        $solteroMarcado = $cliente->persona->estado_civil === 'Soltero' ? 'X' : '';
        $casadoMarcado = $cliente->persona->estado_civil === 'Casado' ? 'X' : '';
        $viudoMarcado = $cliente->persona->estado_civil === 'Viudo' ? 'X' : '';
        $divorciadoMarcado = $cliente->persona->estado_civil === 'Divorciado' ? 'X' : '';
        
        // PEP - Primera pregunta
        $siSoyPep = $datos['es_pep'] === 'si_soy' ? 'X' : '';
        $siHeSidoPep = $datos['es_pep'] === 'si_he_sido' ? 'X' : '';
        $noSoyPep = $datos['es_pep'] === 'no_soy' ? 'X' : '';
        $noHeSidoPep = $datos['es_pep'] === 'no_he_sido' ? 'X' : '';
        
        // Colaborador directo
        $siSoyColab = $datos['colaborador_directo'] === 'si_soy' ? 'X' : '';
        $siHeSidoColab = $datos['colaborador_directo'] === 'si_he_sido' ? 'X' : '';
        $noSoyColab = $datos['colaborador_directo'] === 'no_soy' ? 'X' : '';
        $noHeSidoColab = $datos['colaborador_directo'] === 'no_he_sido' ? 'X' : '';
        
        // Pariente PEP
        $siSoyPariente = isset($datos['pariente_pep']) && $datos['pariente_pep'] === 'si_soy' ? 'X' : '';
        $noSoyPariente = isset($datos['pariente_pep']) && $datos['pariente_pep'] === 'no_soy' ? 'X' : '';
        
        // Beneficiario
        $porMiMismo = 'X'; // Siempre por sí mismo en préstamos personales
        $deTercero = '';
        $personaJuridica = '';
        $enteJuridico = '';
        
        return view('pdf.declaracion-jurada', compact(
            'cliente',
            'datos',
            'fechaActual',
            'dniMarcado',
            'pasaporteMarcado',
            'carneExtranjeraMarcado',
            'otroMarcado',
            'solteroMarcado',
            'casadoMarcado',
            'viudoMarcado',
            'divorciadoMarcado',
            'siSoyPep',
            'siHeSidoPep',
            'noSoyPep',
            'noHeSidoPep',
            'siSoyColab',
            'siHeSidoColab',
            'noSoyColab',
            'noHeSidoColab',
            'siSoyPariente',
            'noSoyPariente',
            'porMiMismo',
            'deTercero',
            'personaJuridica',
            'enteJuridico'
        ))->render();
    }
}
