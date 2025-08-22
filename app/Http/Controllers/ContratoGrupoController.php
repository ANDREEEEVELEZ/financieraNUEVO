<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Grupo;
use App\Models\PrestamoIndividual;
use App\Models\CuotasGrupales;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Log;

class ContratoGrupoController extends Controller
{
    public function imprimirContratosMasivos(Request $request)
    {
        $user = $request->user();
        $gruposIds = explode(',', $request->get('grupos', ''));
        $estado = $request->get('estado');
        $fechaDesde = $request->get('fecha_desde');
        $fechaHasta = $request->get('fecha_hasta');

        // Validar que tenemos grupos
        if (empty($gruposIds) || empty(array_filter($gruposIds))) {
            abort(404, 'No se especificaron grupos válidos.');
        }

        // Obtener todos los grupos con sus préstamos filtrados
        $grupos = Grupo::with(['clientes.persona', 'prestamos' => function($query) use ($estado, $fechaDesde, $fechaHasta) {
            $query->where('estado', $estado);
            if ($fechaDesde) {
                $query->whereDate('fecha_prestamo', '>=', $fechaDesde);
            }
            if ($fechaHasta) {
                $query->whereDate('fecha_prestamo', '<=', $fechaHasta);
            }
        }])->whereIn('id', $gruposIds)->get();

        if ($grupos->isEmpty()) {
            abort(404, 'No se encontraron grupos válidos.');
        }

        $contratosHtml = '';
        $totalContratos = 0;

        foreach ($grupos as $grupo) {
            $prestamoGrupal = $grupo->prestamos->sortByDesc('id')->first();
            
            // Validar que el préstamo esté en estado válido para contratos
            $estadosValidos = ['Aprobado', 'Activo', 'Parcialmente_Retanqueado', 'Finalizado', 'Pendiente'];
            $estadoMostrar = $prestamoGrupal->estado_mostrar ?? $prestamoGrupal->estado;
            
            if (!$prestamoGrupal || !in_array($prestamoGrupal->estado, $estadosValidos)) {
                continue; // Saltar este grupo si no tiene préstamo válido
            }

            // Usar getIntegrantesParaContrato() para obtener los participantes correctos
            foreach ($prestamoGrupal->getIntegrantesParaContrato() as $integrante) {
                $cliente = $integrante['cliente'];
                $persona = $cliente->persona;
                $prestamoIndividual = \App\Models\PrestamoIndividual::where('prestamo_id', $prestamoGrupal->id ?? null)
                    ->where('cliente_id', $cliente->id)
                    ->first();
                $monto = $prestamoIndividual->monto_prestado_individual ?? 0;
                $plazo = $prestamoGrupal->cantidad_cuotas ?? 4;
                $cuota = $prestamoIndividual->monto_cuota_prestamo_individual ?? 0;
                $total = $prestamoIndividual->monto_devolver_individual ?? 0;
                $seguro = $prestamoIndividual->seguro ?? 0;

                // Generar cronograma individual basado en las fechas de cuotas grupales
                $cuotas = \App\Models\CuotasGrupales::where('prestamo_id', $prestamoGrupal->id ?? null)
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
                $totalContratos++;
            }
        }

        if ($totalContratos === 0) {
            abort(404, 'No se encontraron contratos válidos para generar.');
        }

        $pdf = Pdf::loadHTML($contratosHtml);
        $nombreArchivo = "Contratos_{$estado}_{$fechaDesde}_al_{$fechaHasta}_Total_{$totalContratos}.pdf";
        
        return $pdf->download($nombreArchivo);
    }

    public function imprimirContratos($grupoId)
    {
        $user = request()->user();

        $grupo = Grupo::with(['clientes.persona', 'prestamos'])->findOrFail($grupoId);
        $prestamoGrupal = $grupo->prestamos->sortByDesc('id')->first(); // Toma el préstamo grupal más reciente
        
        // Validar que el préstamo esté en estado válido para contratos
        $estadosValidos = ['Aprobado', 'Activo', 'Parcialmente_Retanqueado', 'Finalizado', 'Pendiente'];
        $estadoMostrar = $prestamoGrupal->estado_mostrar ?? $prestamoGrupal->estado;
        
        if (!$prestamoGrupal || !in_array($prestamoGrupal->estado, $estadosValidos)) {
            abort(403, 'Solo se pueden imprimir contratos de préstamos con estados válidos: Aprobado, Activo, Parcialmente Retanqueado, Finalizado o Pendiente. Estado actual: ' . ($prestamoGrupal->estado ?? 'null'));
        }

        $contratosHtml = '';

        // Usar getIntegrantesParaContrato() para obtener los participantes correctos
        foreach ($prestamoGrupal->getIntegrantesParaContrato() as $integrante) {
            $cliente = $integrante['cliente'];
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
