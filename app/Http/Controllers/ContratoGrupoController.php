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
            // Buscar el préstamo principal (no retanqueos) con estado válido para contratos
            $prestamoGrupal = $grupo->prestamos
                ->filter(function($prestamo) {
                    // Excluir retanqueos (que contienen "RETANQUEO" en la descripción)
                    $esRetanqueo = stripos($prestamo->descripcion ?? '', 'RETANQUEO') !== false;
                    return !$esRetanqueo;
                })
                ->whereIn('estado', [\App\Models\Prestamo::ESTADO_APROBADO, \App\Models\Prestamo::ESTADO_ACTIVO, \App\Models\Prestamo::ESTADO_AL_DIA, \App\Models\Prestamo::ESTADO_EN_MORA, \App\Models\Prestamo::ESTADO_FINALIZADO])
                ->sortByDesc('id')
                ->first();
            
            // Si no encontramos préstamo principal válido, intentar con cualquier préstamo válido
            if (!$prestamoGrupal) {
                $prestamoGrupal = $grupo->prestamos
                    ->whereIn('estado', [\App\Models\Prestamo::ESTADO_APROBADO, \App\Models\Prestamo::ESTADO_ACTIVO, \App\Models\Prestamo::ESTADO_AL_DIA, \App\Models\Prestamo::ESTADO_EN_MORA, \App\Models\Prestamo::ESTADO_FINALIZADO])
                    ->sortByDesc('id')
                    ->first();
            }
            
            if (!$prestamoGrupal) {
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
                    'fecha_aprobacion' => $prestamoGrupal->fecha_prestamo,
                    'lugar' => 'Sullana',
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
        
        // Buscar el préstamo principal (no retanqueos) con estado válido para contratos
        $prestamoGrupal = $grupo->prestamos
            ->filter(function($prestamo) {
                // Excluir retanqueos (que contienen "RETANQUEO" en la descripción)
                $esRetanqueo = stripos($prestamo->descripcion ?? '', 'RETANQUEO') !== false;
                return !$esRetanqueo;
            })
            ->whereIn('estado', [\App\Models\Prestamo::ESTADO_APROBADO, \App\Models\Prestamo::ESTADO_ACTIVO, \App\Models\Prestamo::ESTADO_AL_DIA, \App\Models\Prestamo::ESTADO_EN_MORA, \App\Models\Prestamo::ESTADO_FINALIZADO])
            ->sortByDesc('id')
            ->first();
        
        // Si no encontramos préstamo principal válido, intentar con cualquier préstamo válido
        if (!$prestamoGrupal) {
            $prestamoGrupal = $grupo->prestamos
                ->whereIn('estado', [\App\Models\Prestamo::ESTADO_APROBADO, \App\Models\Prestamo::ESTADO_ACTIVO, \App\Models\Prestamo::ESTADO_AL_DIA, \App\Models\Prestamo::ESTADO_EN_MORA, \App\Models\Prestamo::ESTADO_FINALIZADO])
                ->sortByDesc('id')
                ->first();
        }
        
        // Validar que encontramos un préstamo válido
        if (!$prestamoGrupal) {
            abort(403, 'No se encontró un préstamo válido para imprimir contratos. Solo se pueden imprimir contratos de préstamos con estados: Aprobado, Activo, Parcialmente Retanqueado o Finalizado.');
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
                'fecha_aprobacion' => $prestamoGrupal->fecha_prestamo,
                'lugar' => 'Sullana',
            ])->render();
            $contratosHtml .= '<div style="page-break-after: always;"></div>';
        }

        $pdf = Pdf::loadHTML($contratosHtml);
        return $pdf->download('Contrado del Grupo '.$grupo->nombre_grupo.'.pdf');
    }

    /**
     * Imprime contratos de un préstamo específico (original o retanqueo)
     */
    public function imprimirContratosPrestamo($prestamoId)
    {
        $user = request()->user();

        $prestamoGrupal = \App\Models\Prestamo::with(['grupo.clientes.persona'])->findOrFail($prestamoId);
        
        // Validar que el préstamo tiene estado válido para contratos
        if (!in_array($prestamoGrupal->estado, [\App\Models\Prestamo::ESTADO_APROBADO, \App\Models\Prestamo::ESTADO_ACTIVO, \App\Models\Prestamo::ESTADO_AL_DIA, \App\Models\Prestamo::ESTADO_EN_MORA, \App\Models\Prestamo::ESTADO_FINALIZADO])) {
            abort(403, 'Solo se pueden imprimir contratos de préstamos con estados: Aprobado, Activo, Parcialmente Retanqueado o Finalizado.');
        }

        $contratosHtml = '';

        // Usar getIntegrantesParaContrato() para obtener los participantes correctos del préstamo específico
        foreach ($prestamoGrupal->getIntegrantesParaContrato() as $integrante) {
            $cliente = $integrante['cliente'];
            $persona = $cliente->persona;
            $prestamoIndividual = PrestamoIndividual::where('prestamo_id', $prestamoGrupal->id)
                ->where('cliente_id', $cliente->id)
                ->first();
            $monto = $prestamoIndividual->monto_prestado_individual ?? 0;
            $plazo = $prestamoGrupal->cantidad_cuotas ?? 4;
            $cuota = $prestamoIndividual->monto_cuota_prestamo_individual ?? 0;
            $total = $prestamoIndividual->monto_devolver_individual ?? 0;
            $seguro = $prestamoIndividual->seguro ?? 0;

            // Generar cronograma individual basado en las fechas de cuotas grupales del préstamo específico
            $cuotas = CuotasGrupales::where('prestamo_id', $prestamoGrupal->id)
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
                'fecha_aprobacion' => $prestamoGrupal->fecha_prestamo,
                'lugar' => 'Sullana',
            ])->render();
            $contratosHtml .= '<div style="page-break-after: always;"></div>';
        }

        // Determinar nombre del archivo según tipo de préstamo
        $nombreGrupo = $prestamoGrupal->grupo->nombre_grupo ?? 'Grupo';
        $tipoContrato = stripos($prestamoGrupal->descripcion ?? '', 'RETANQUEO') !== false ? 'Retanqueo' : 'Prestamo';
        $nombreArchivo = "Contrato_{$tipoContrato}_{$nombreGrupo}.pdf";

        $pdf = Pdf::loadHTML($contratosHtml);
        return $pdf->download($nombreArchivo);
    }

    /**
     * Imprime cartilla de identificación de un préstamo específico
     */
    public function imprimirCartillaPrestamo($prestamoId)
    {
        $user = request()->user();

        $prestamoGrupal = \App\Models\Prestamo::with(['grupo.clientes.persona'])->findOrFail($prestamoId);
        
        // Validar que el préstamo tiene estado válido para cartilla
        if (!in_array($prestamoGrupal->estado, [\App\Models\Prestamo::ESTADO_APROBADO, \App\Models\Prestamo::ESTADO_ACTIVO, \App\Models\Prestamo::ESTADO_AL_DIA, \App\Models\Prestamo::ESTADO_EN_MORA, \App\Models\Prestamo::ESTADO_FINALIZADO])) {
            abort(403, 'Solo se pueden imprimir cartillas de préstamos con estados: Aprobado, Activo, Parcialmente Retanqueado o Finalizado.');
        }

        // Obtener los integrantes del préstamo
        $integrantes = $prestamoGrupal->getIntegrantesParaContrato();

        // Generar HTML para la cartilla
        $cartillaHtml = View::make('contratos.cartilla', [
            'prestamo' => $prestamoGrupal,
            'grupo' => $prestamoGrupal->grupo,
            'integrantes' => $integrantes,
            'fecha' => $prestamoGrupal->fecha_prestamo,
        ])->render();

        // Determinar nombre del archivo
        $nombreGrupo = $prestamoGrupal->grupo->nombre_grupo ?? 'Grupo';
        $nombreArchivo = "Cartilla_Identificacion_{$nombreGrupo}.pdf";

        $pdf = Pdf::loadHTML($cartillaHtml);
        return $pdf->download($nombreArchivo);
    }
}
