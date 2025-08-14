<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class DeclaracionJuradaController extends Controller
{
    public function generarPDF($cliente_id)
    {
        // Buscar el cliente con su persona
        $cliente = Cliente::with('persona')->findOrFail($cliente_id);
        
        // Preparar los datos para el PDF
        $data = [
            'cliente' => $cliente,
            'persona' => $cliente->persona,
            'fecha_actual' => Carbon::now()->format('d/m/Y'),
            // Datos que se prellenan automáticamente
            'nombres' => $cliente->persona->nombre,
            'apellidos' => $cliente->persona->apellidos,
            'dni' => $cliente->persona->DNI,
            'nacionalidad' => 'PERUANA',
            'estado_civil' => $cliente->persona->estado_civil,
            'domicilio' => $cliente->persona->direccion,
            'distrito' => $cliente->persona->distrito,
            'celular' => $cliente->persona->celular,
            'correo' => $cliente->persona->correo,
            'ocupacion' => $cliente->actividad ?? '',
        ];
        
        // Generar el PDF
        $pdf = Pdf::loadView('pdf.declaracion-jurada', $data);
        
        // Configurar el PDF
        $pdf->setPaper('A4', 'portrait');
        
        // Nombre del archivo
        $filename = 'Declaracion_Jurada_' . str_replace(' ', '_', $cliente->persona->nombre) . '_' . $cliente->persona->DNI . '.pdf';
        
        // Descargar el PDF
        return $pdf->download($filename);
    }
}
