<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Grupo;
use App\Models\PrestamoIndividual;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\View;

class ContratoGrupoController extends Controller
{
    public function imprimirContratos($grupoId)
    {
        $user = request()->user();

        $grupo = Grupo::with(['clientes.persona', 'prestamos'])->findOrFail($grupoId);
        $prestamoGrupal = $grupo->prestamos->sortByDesc('id')->first(); // Toma el préstamo grupal más reciente

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

            $contratosHtml .= View::make('contratos.contrato', [
                'cliente' => $persona,
                'monto' => $monto,
                'plazo' => $plazo,
                'cuota' => $cuota,
                'total' => $total,
                'seguro' => $seguro,
                'ciclo' => $cliente->ciclo ?? '',
            ])->render();
            $contratosHtml .= '<div style="page-break-after: always;"></div>';
        }

        $pdf = Pdf::loadHTML($contratosHtml);
        return $pdf->download('contratos_grupo_'.$grupo->id.'.pdf');
    }
}
