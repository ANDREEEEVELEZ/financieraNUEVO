<?php

namespace App\Services;

use App\Models\Cliente;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class DeclaracionJuradaService
{
    public static function generarPDF(Cliente $cliente, array $datosFormulario = [])
    {
        // Datos del cliente (autocompletados)
        $datos = [
            'nombres' => strtoupper($cliente->persona->nombre),
            'apellidos' => strtoupper($cliente->persona->apellidos),
            'dni' => $cliente->persona->DNI,
            'nacionalidad' => 'PERUANA',
            'estado_civil' => $cliente->persona->estado_civil,
            'domicilio' => strtoupper($cliente->persona->direccion),
            'distrito' => strtoupper($cliente->persona->distrito),
            'provincia' => 'SULLANA',
            'departamento' => 'PIURA',
            'ocupacion' => strtoupper($cliente->actividad),
            'celular' => $cliente->persona->celular,
            'correo' => strtolower($cliente->persona->correo),
            'fecha_hoy' => Carbon::now()->format('d/m/Y'),
            'lugar' => 'Sullana',
            
            // Valores por defecto (editables en el formulario)
            'tipo_documento' => $datosFormulario['tipo_documento'] ?? 'DNI',
            'numero_documento' => $datosFormulario['numero_documento'] ?? $cliente->persona->DNI,
            'otro_documento_detalle' => $datosFormulario['otro_documento_detalle'] ?? '',
            'conyuge_nombres' => $datosFormulario['conyuge_nombres'] ?? '',
            'tipo_via' => $datosFormulario['tipo_via'] ?? 'Jr.',
            'nombre_via' => $datosFormulario['nombre_via'] ?? strtoupper($cliente->persona->direccion),
            'urbanizacion' => $datosFormulario['urbanizacion'] ?? '',
            'complejo_zona_sector' => $datosFormulario['complejo_zona_sector'] ?? '',
            'interior' => $datosFormulario['interior'] ?? '',
            'departamento_numero' => $datosFormulario['departamento_numero'] ?? '',
            'telefono_fijo' => $datosFormulario['telefono_fijo'] ?? '',
            
            // Propósito (punto 9) - fijo
            'proposito_relacion' => 'PRESTAMO',
            
            // Campos PEP (punto 10)
            'es_pep' => $datosFormulario['es_pep'] ?? 'NO_SOY',
            'cargo_publico' => $datosFormulario['cargo_publico'] ?? '',
            'entidad_publica' => $datosFormulario['entidad_publica'] ?? '',
            'fecha_inicio_cargo' => $datosFormulario['fecha_inicio_cargo'] ?? '',
            'fecha_fin_cargo' => $datosFormulario['fecha_fin_cargo'] ?? '',
            'parentesco_pep' => $datosFormulario['parentesco_pep'] ?? '',
            
            // Campos de representación (punto 11) - por defecto "por mi mismo"
            'actua_por' => $datosFormulario['actua_por'] ?? 'MI_MISMO',
            'representado_nombres' => $datosFormulario['representado_nombres'] ?? '',
            'representado_apellidos' => $datosFormulario['representado_apellidos'] ?? '',
            'representado_documento' => $datosFormulario['representado_documento'] ?? '',
        ];

        // Generar PDF con vista personalizada
        $pdf = Pdf::loadView('declaracion-jurada.pdf', $datos);
        $pdf->setPaper('A4', 'portrait');
        
        $nombreArchivo = "Declaracion_Jurada_{$cliente->persona->DNI}_{$datos['fecha_hoy']}.pdf";
        
        return $pdf->download($nombreArchivo);
    }
}
