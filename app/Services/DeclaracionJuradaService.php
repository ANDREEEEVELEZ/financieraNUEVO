<?php

namespace App\Services;

use App\Models\Cliente;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class DeclaracionJuradaService
{
    /**
     * Limpiar caracteres especiales para PDF
     */
    private static function limpiarTexto($texto)
    {
        if (empty($texto)) return '';
        
        // Convertir a string básico
        $texto = (string) $texto;
        
        // Remover cualquier carácter no ASCII
        $texto = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto);
        
        // Remover caracteres de control
        $texto = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $texto);
        
        return trim($texto);
    }

    public static function generarPDF(Cliente $cliente, array $datosFormulario = [])
    {
        // Datos del cliente (autocompletados)
        $datos = [
            'nombres' => self::limpiarTexto(strtoupper($cliente->persona->nombre)),
            'apellidos' => self::limpiarTexto(strtoupper($cliente->persona->apellidos)),
            'dni' => $cliente->persona->DNI,
            'nacionalidad' => 'PERUANA',
            'estado_civil' => self::limpiarTexto($cliente->persona->estado_civil),
            'domicilio' => self::limpiarTexto(strtoupper($cliente->persona->direccion)),
            'distrito' => self::limpiarTexto(strtoupper($cliente->persona->distrito)),
            'provincia' => 'SULLANA',
            'departamento' => 'PIURA',
            'ocupacion' => self::limpiarTexto(strtoupper($cliente->actividad)),
            'celular' => $cliente->persona->celular,
            'correo' => strtolower($cliente->persona->correo),
            'fecha_hoy' => Carbon::now()->format('d/m/Y'),
            'lugar' => 'Sullana',
            
            // Valores por defecto (editables en el formulario)
            'tipo_documento' => $datosFormulario['tipo_documento'] ?? 'DNI',
            'numero_documento' => $datosFormulario['numero_documento'] ?? $cliente->persona->DNI,
            'otro_documento_detalle' => self::limpiarTexto($datosFormulario['otro_documento_detalle'] ?? ''),
            'conyuge_nombres' => self::limpiarTexto($datosFormulario['conyuge_nombres'] ?? ''),
            'tipo_via' => $datosFormulario['tipo_via'] ?? 'Jr.',
            'nombre_via' => self::limpiarTexto($datosFormulario['nombre_via'] ?? strtoupper($cliente->persona->direccion)),
            'urbanizacion' => self::limpiarTexto($datosFormulario['urbanizacion'] ?? ''),
            'complejo_zona_sector' => self::limpiarTexto($datosFormulario['complejo_zona_sector'] ?? ''),
            'interior' => self::limpiarTexto($datosFormulario['interior'] ?? ''),
            'departamento_numero' => self::limpiarTexto($datosFormulario['departamento_numero'] ?? ''),
            'telefono_fijo' => $datosFormulario['telefono_fijo'] ?? '',
            
            // Propósito (punto 9) - fijo
            'proposito_relacion' => 'PRESTAMO',
            
            // Campos PEP (punto 10)
            'es_pep' => $datosFormulario['es_pep'] ?? 'NO_SOY',
            'cargo_publico' => self::limpiarTexto($datosFormulario['cargo_publico'] ?? ''),
            'entidad_publica' => self::limpiarTexto($datosFormulario['entidad_publica'] ?? ''),
            'fecha_inicio_cargo' => $datosFormulario['fecha_inicio_cargo'] ?? '',
            'fecha_fin_cargo' => $datosFormulario['fecha_fin_cargo'] ?? '',
            'parentesco_pep' => self::limpiarTexto($datosFormulario['parentesco_pep'] ?? ''),
            
            // Campos de representación (punto 11) - por defecto "por mi mismo"
            'actua_por' => $datosFormulario['actua_por'] ?? 'MI_MISMO',
            'representado_nombres' => self::limpiarTexto($datosFormulario['representado_nombres'] ?? ''),
            'representado_apellidos' => self::limpiarTexto($datosFormulario['representado_apellidos'] ?? ''),
            'representado_documento' => $datosFormulario['representado_documento'] ?? '',
        ];

        // Generar PDF con vista personalizada
        try {
            $pdf = Pdf::loadView('declaracion-jurada.test', $datos);
            $pdf->setPaper('A4', 'portrait');
            
            $fechaArchivo = Carbon::now()->format('d-m-Y');
            $nombreArchivo = "Declaracion_Jurada_{$cliente->persona->DNI}_{$fechaArchivo}.pdf";
            
            return $pdf->download($nombreArchivo);
        } catch (\Exception $e) {
            // Log del error específico
            Log::error('Error generando PDF: ' . $e->getMessage());
            Log::error('Datos: ' . json_encode($datos));
            throw new \Exception('Error al generar PDF: ' . $e->getMessage());
        }
    }
}
