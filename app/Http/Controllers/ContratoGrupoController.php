<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Grupo;
use App\Models\PrestamoIndividual;
use App\Models\CuotasGrupales;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\View;

class ContratoGrupoController extends Controller
{
    public function imprimirContratos($grupoId)
    {
        $user = request()->user();

        $grupo = Grupo::with(['clientes.persona', 'prestamos'])->findOrFail($grupoId);
        $prestamoGrupal = $grupo->prestamos->sortByDesc('id')->first(); // Toma el préstamo grupal más reciente
        
        // Validar que el préstamo esté aprobado
        if (!$prestamoGrupal || strtolower($prestamoGrupal->estado) !== 'aprobado') {
            abort(403, 'Solo se pueden imprimir contratos de préstamos aprobados.');
        }

        $contratosHtml = '';

        foreach ($grupo->clientes as $cliente) {
            $persona = $cliente->persona;
            $prestamoIndividual = PrestamoIndividual::where('prestamo_id', $prestamoGrupal->id ?? null)
                ->where('cliente_id', $cliente->id)
                ->first();
            $monto = $prestamoIndividual->monto_prestado_individual ?? 0;
            $plazo = $prestamoGrupal->cantidad_cuotas ?? 4;
            $cuota = $prestamoIndividual->monto_cuota_prestamo_individual ?? 0;
            $total = $prestamoIndividual->monto_devolver_individual ?? 0;
            $seguro = $prestamoIndividual->seguro ?? 0;

            // Generar cronograma individual basado en las fechas de cuotas grupales
            // pero usando el monto individual de cada cliente
            $cuotas = CuotasGrupales::where('prestamo_id', $prestamoGrupal->id ?? null)
                ->orderBy('numero_cuota')
                ->get();
            $cronograma = [];
            $cronograma_grupal = [];
            foreach ($cuotas as $c) {
                $cronograma[] = [
                    'fecha' => $c->fecha_vencimiento,
                    'monto' => $prestamoIndividual->monto_cuota_prestamo_individual ?? 0,
                ];
                $cronograma_grupal[] = [
                    'fecha' => $c->fecha_vencimiento,
                    'monto' => $c->monto_cuota_grupal,
                ];
            }

            $contratosHtml .= View::make('contratos.contrato', [
                'cliente' => $persona,
                'monto' => $monto,
                'plazo' => $plazo,
                'cuota' => $cuota,
                'total' => $total,
                'seguro' => $seguro,
                'ciclo' => $cliente->ciclo ?? '',
                'cronograma' => $cronograma,
                'cronograma_grupal' => $cronograma_grupal,
            ])->render();
            $contratosHtml .= '<div style="page-break-after: always;"></div>';
        }

        $pdf = Pdf::loadHTML($contratosHtml);
        return $pdf->download('Contrado del Grupo '.$grupo->nombre_grupo.'.pdf');
    }
}
