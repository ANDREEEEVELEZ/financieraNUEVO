<?php

namespace App\Domain\Documentos;

use App\Models\Cliente;
use Barryvdh\DomPDF\Facade\Pdf;

class DeclaracionJuradaService
{
    /**
     * Generar PDF de Declaración Jurada para clientes PEP
     */
    public function generatePepPdf(Cliente $cliente, array $pepData = [])
    {
        $cliente->load('persona');

        $data = [
            'cliente' => $cliente,
            'persona' => $cliente->persona,
            'pep_data' => $pepData,
            'fecha_generacion' => now()->format('d/m/Y'),
        ];

        $pdf = Pdf::loadView('pdf.declaracion-jurada', $data);
        $pdf->setPaper('a4', 'portrait');

        return $pdf->output();
    }

    /**
     * Generar PDF de Declaración Jurada para clientes no PEP (Capacitado/Iletrado)
     */
    public function generateGeneralPdf(Cliente $cliente)
    {
        $cliente->load('persona');

        // Datos predefinidos para clientes no PEP
        $pepData = [
            'conyuge_conviviente' => '',
            'telefono_fijo' => '',
            'proposito_relacion' => 'Préstamo',
            'es_pep' => 'no_soy',
            'es_colaborador_pep' => 'no_he_sido',
            'cargo_pep' => '',
            'institucion_pep' => '',
            'es_pariente_pep' => 'no_soy',
            'parientes_pep' => [],
            'operacion_favor' => 'mi_mismo',
            'origen_fondos_propio' => 'No aplica',
            'tercero_nombres' => '',
            'tercero_documento' => '',
            'razon_social' => '',
            'ruc' => '',
            'observaciones' => 'No aplica',
        ];

        $data = [
            'cliente' => $cliente,
            'persona' => $cliente->persona,
            'pep_data' => $pepData,
            'fecha_generacion' => now()->format('d/m/Y'),
        ];

        $pdf = Pdf::loadView('pdf.declaracion-jurada', $data);
        $pdf->setPaper('a4', 'portrait');

        return $pdf->output();
    }

    /**
     * Obtener nombre de archivo para el PDF
     */
    public function getFilename(Cliente $cliente, string $tipo = 'general')
    {
        $prefijo = $tipo === 'pep' ? 'DJ_PEP' : 'DJ_GENERAL';
        $dni = $cliente->persona->DNI ?? 'SIN_DNI';
        $nombre = str_replace(' ', '_', $cliente->persona->nombre ?? 'SIN_NOMBRE');
        $apellidos = str_replace(' ', '_', $cliente->persona->apellidos ?? 'SIN_APELLIDO');

        return "{$prefijo}_{$dni}_{$nombre}_{$apellidos}.pdf";
    }
}
