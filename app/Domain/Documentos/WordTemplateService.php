<?php

namespace App\Domain\Documentos;

use App\Models\Cliente;
use PhpOffice\PhpWord\TemplateProcessor;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Settings;

class WordTemplateService
{
    /**
     * Generar PDF desde plantilla Word
     */
    public function generateDeclaracionJuradaPdf(Cliente $cliente, array $pepData = [])
    {
        $cliente->load('persona');

        // Ruta de la plantilla Word
        $templatePath = resource_path('templates/word/Formato DJ Regimen General.docx');

        if (!file_exists($templatePath)) {
            throw new \Exception('Plantilla Word no encontrada en: ' . $templatePath);
        }

        // Crear procesador de plantilla
        $templateProcessor = new TemplateProcessor($templatePath);

        // Reemplazar variables en la plantilla
        $this->replaceVariables($templateProcessor, $cliente, $pepData);

        // Guardar archivo temporal
        $tempWordFile = storage_path('app/temp/declaracion_' . $cliente->id . '_' . time() . '.docx');
        $this->ensureTempDirectory();
        $templateProcessor->saveAs($tempWordFile);

        // Convertir a PDF
        $pdfContent = $this->convertWordToPdf($tempWordFile);

        // Limpiar archivo temporal
        if (file_exists($tempWordFile)) {
            unlink($tempWordFile);
        }

        return $pdfContent;
    }

    /**
     * Reemplazar variables en la plantilla Word
     */
    private function replaceVariables(TemplateProcessor $templateProcessor, Cliente $cliente, array $pepData)
    {
        $persona = $cliente->persona;

        // Variables básicas
        $templateProcessor->setValue('NOMBRE', strtoupper($persona->nombre ?? ''));
        $templateProcessor->setValue('APELLIDOS', strtoupper($persona->apellidos ?? ''));
        $templateProcessor->setValue('DNI', $persona->DNI ?? '');
        $templateProcessor->setValue('DIRECCION', strtoupper($persona->direccion ?? ''));
        $templateProcessor->setValue('DISTRITO', strtoupper($persona->distrito ?? ''));
        $templateProcessor->setValue('CELULAR', $persona->celular ?? '');
        $templateProcessor->setValue('CORREO', strtolower($persona->correo ?? ''));
        $templateProcessor->setValue('ACTIVIDAD', strtoupper($cliente->actividad ?? ''));
        $templateProcessor->setValue('FECHA', now()->format('d/m/Y'));

        // Variables de estado civil con checkboxes
        $estadoCivil = strtolower($persona->estado_civil ?? '');
        $templateProcessor->setValue('CK_SOLTERO', $estadoCivil == 'soltero' ? 'X' : '');
        $templateProcessor->setValue('CK_CASADO', $estadoCivil == 'casado' ? 'X' : '');
        $templateProcessor->setValue('CK_VIUDO', $estadoCivil == 'viudo' ? 'X' : '');
        $templateProcessor->setValue('CK_DIVORCIADO', $estadoCivil == 'divorciado' ? 'X' : '');
        $templateProcessor->setValue('CK_CONVIVIENTE', $estadoCivil == 'conviviente' ? 'X' : '');

        // Variables PEP
        $esPep = $pepData['es_pep'] ?? 'no_soy';
        $templateProcessor->setValue('CK_PEP_SI_SOY', $esPep == 'si_soy' ? 'X' : '');
        $templateProcessor->setValue('CK_PEP_SI_HE_SIDO', $esPep == 'si_he_sido' ? 'X' : '');
        $templateProcessor->setValue('CK_PEP_NO_SOY', $esPep == 'no_soy' ? 'X' : '');
        $templateProcessor->setValue('CK_PEP_NO_HE_SIDO', $esPep == 'no_he_sido' ? 'X' : '');

        // Variables adicionales PEP
        $templateProcessor->setValue('CONYUGE_CONVIVIENTE', $pepData['conyuge_conviviente'] ?? '');
        $templateProcessor->setValue('TELEFONO_FIJO', $pepData['telefono_fijo'] ?? '');
        $templateProcessor->setValue('PROPOSITO_RELACION', $pepData['proposito_relacion'] ?? 'PRÉSTAMO');
        $templateProcessor->setValue('CARGO_PEP', $pepData['cargo_pep'] ?? '');
        $templateProcessor->setValue('INSTITUCION_PEP', $pepData['institucion_pep'] ?? '');

        // Operación a favor
        $operacionFavor = $pepData['operacion_favor'] ?? 'mi_mismo';
        $templateProcessor->setValue('CK_MI_MISMO', $operacionFavor == 'mi_mismo' ? 'X' : '');
        $templateProcessor->setValue('CK_TERCERO_NATURAL', $operacionFavor == 'tercero_natural' ? 'X' : '');
        $templateProcessor->setValue('CK_PERSONA_JURIDICA', $operacionFavor == 'persona_juridica' ? 'X' : '');
        $templateProcessor->setValue('CK_ENTE_JURIDICO', $operacionFavor == 'ente_juridico' ? 'X' : '');

        // Variables de terceros
        $templateProcessor->setValue('TERCERO_NOMBRES', $pepData['tercero_nombres'] ?? '');
        $templateProcessor->setValue('TERCERO_DOCUMENTO', $pepData['tercero_documento'] ?? '');
        $templateProcessor->setValue('RAZON_SOCIAL', $pepData['razon_social'] ?? '');
        $templateProcessor->setValue('RUC', $pepData['ruc'] ?? '');
        $templateProcessor->setValue('OBSERVACIONES', $pepData['observaciones'] ?? 'No aplica');
    }

    /**
     * Convertir Word a PDF
     */
    private function convertWordToPdf(string $wordFile): string
    {
        // Configurar PhpWord para usar DomPDF
        Settings::setPdfRendererName(Settings::PDF_RENDERER_DOMPDF);
        Settings::setPdfRendererPath(base_path('vendor/dompdf/dompdf'));

        // Cargar el documento Word
        $phpWord = IOFactory::load($wordFile);

        // Convertir a HTML primero
        $htmlWriter = IOFactory::createWriter($phpWord, 'HTML');
        $tempHtmlFile = storage_path('app/temp/temp_' . time() . '.html');
        $htmlWriter->save($tempHtmlFile);

        // Convertir HTML a PDF usando DomPDF
        $htmlContent = file_get_contents($tempHtmlFile);

        // Limpiar HTML temporal
        if (file_exists($tempHtmlFile)) {
            unlink($tempHtmlFile);
        }

        // Generar PDF con DomPDF
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($htmlContent);
        $pdf->setPaper('a4', 'portrait');

        return $pdf->output();
    }

    /**
     * Asegurar que existe el directorio temporal
     */
    private function ensureTempDirectory()
    {
        $tempDir = storage_path('app/temp');
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }
    }

    /**
     * Obtener nombre de archivo para el PDF
     */
    public function getFilename(Cliente $cliente, string $tipo = 'general'): string
    {
        $prefijo = $tipo === 'pep' ? 'DJ_PEP' : 'DJ';
        $dni = $cliente->persona->DNI ?? 'SIN_DNI';
        $nombre = str_replace(' ', '_', $cliente->persona->nombre ?? 'SIN_NOMBRE');
        $apellidos = str_replace(' ', '_', $cliente->persona->apellidos ?? 'SIN_APELLIDO');

        return "{$prefijo}_{$dni}_{$nombre}_{$apellidos}.pdf";
    }
}
