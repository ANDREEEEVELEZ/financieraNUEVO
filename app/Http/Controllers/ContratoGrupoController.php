<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Grupo;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\View;

class ContratoGrupoController extends Controller
{
    public function imprimirContratos($grupoId)
    {
        $grupo = Grupo::with(['clientes.persona', 'prestamos'])->findOrFail($grupoId);
        $contratosHtml = '';

        foreach ($grupo->clientes as $cliente) {
            $persona = $cliente->persona;
            $prestamo = $grupo->prestamos->where('persona_id', $cliente->id)->first();
            $monto = $prestamo->monto ?? 0;
            $plazo = $prestamo->plazo ?? 4;
            $cuota = $prestamo->cuota_semanal ?? 0;
            $total = $cuota * $plazo;
            $seguro = $prestamo->seguro ?? 0;

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
