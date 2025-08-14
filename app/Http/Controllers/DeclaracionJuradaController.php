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
        
        // Configurar opciones de DOMPDF - Configuración segura para evitar errores UTF-8
        $options = new Options();
        $options->set('defaultFont', 'Arial');
        $options->set('isHtml5ParserEnabled', false);
        $options->set('isPhpEnabled', false);
        $options->set('isRemoteEnabled', false);
        $options->set('chroot', public_path());
        
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
        
        // Función para limpiar caracteres especiales que causan problemas de codificación
        $limpiarTexto = function($texto) {
            if (empty($texto)) return $texto;
            
            $reemplazos = [
                'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n',
                'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ñ' => 'N',
                'ü' => 'u', 'Ü' => 'U'
            ];
            
            return str_replace(array_keys($reemplazos), array_values($reemplazos), $texto);
        };
        
        // Limpiar datos del cliente para evitar problemas de codificación
        $clienteLimpio = (object) [
            'nombres' => $limpiarTexto($cliente->persona->nombres ?? ''),
            'apellidos' => $limpiarTexto($cliente->persona->apellidos ?? ''),
            'DNI' => $cliente->persona->DNI ?? '',
            'estado_civil' => $limpiarTexto($cliente->persona->estado_civil ?? ''),
            'direccion' => $limpiarTexto($cliente->persona->direccion ?? ''),
            'distrito' => $limpiarTexto($cliente->persona->distrito ?? ''),
            'telefono' => $cliente->persona->telefono ?? '',
            'celular' => $cliente->persona->celular ?? '',
            'correo' => $limpiarTexto($cliente->persona->correo ?? ''),
            'ocupacion' => $limpiarTexto($cliente->persona->ocupacion ?? 'No especificada'),
        ];
        
        // Limpiar datos del formulario
        $datosLimpios = array_map(function($valor) use ($limpiarTexto) {
            return is_string($valor) ? $limpiarTexto($valor) : $valor;
        }, $datos);
        
        // Determinar qué opciones están marcadas
        $dniMarcado = 'X';
        $pasaporteMarcado = '';
        $carneExtranjeraMarcado = '';
        $otroMarcado = '';
        
        // Estado civil
        $solteroMarcado = $clienteLimpio->estado_civil === 'Soltero' ? 'X' : '';
        $casadoMarcado = $clienteLimpio->estado_civil === 'Casado' ? 'X' : '';
        $viudoMarcado = $clienteLimpio->estado_civil === 'Viudo' ? 'X' : '';
        $divorciadoMarcado = $clienteLimpio->estado_civil === 'Divorciado' ? 'X' : '';
        
        // PEP - Primera pregunta
        $siSoyPep = $datosLimpios['es_pep'] === 'si_soy' ? 'X' : '';
        $siHeSidoPep = $datosLimpios['es_pep'] === 'si_he_sido' ? 'X' : '';
        $noSoyPep = $datosLimpios['es_pep'] === 'no_soy' ? 'X' : '';
        $noHeSidoPep = $datosLimpios['es_pep'] === 'no_he_sido' ? 'X' : '';
        
        // Colaborador directo
        $siSoyColab = $datosLimpios['colaborador_directo'] === 'si_soy' ? 'X' : '';
        $siHeSidoColab = $datosLimpios['colaborador_directo'] === 'si_he_sido' ? 'X' : '';
        $noSoyColab = $datosLimpios['colaborador_directo'] === 'no_soy' ? 'X' : '';
        $noHeSidoColab = $datosLimpios['colaborador_directo'] === 'no_he_sido' ? 'X' : '';
        
        // Pariente PEP
        $siSoyPariente = isset($datosLimpios['pariente_pep']) && $datosLimpios['pariente_pep'] === 'si_soy' ? 'X' : '';
        $noSoyPariente = isset($datosLimpios['pariente_pep']) && $datosLimpios['pariente_pep'] === 'no_soy' ? 'X' : '';
        
        // Beneficiario
        $porMiMismo = 'X'; // Siempre por sí mismo en préstamos personales
        $deTercero = '';
        $personaJuridica = '';
        $enteJuridico = '';
        
        return view('pdf.declaracion-jurada', compact(
            'clienteLimpio',
            'datosLimpios',
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
