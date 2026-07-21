<?php

declare(strict_types=1);

namespace App\Listeners\Domain;

use App\Events\Domain\PrestamoDesembolsado;
use App\Models\CuotaIndividual;
use App\Models\CuotasGrupales;
use App\Models\Egreso;
use App\Models\MovimientoFinanciero;
use App\Models\Prestamo;
use App\Models\Retanqueo;
use Carbon\Carbon;
use Illuminate\Events\Attributes\AsEventListener;
use Illuminate\Support\Facades\Log;

final class DesembolsoListener
{
    #[AsEventListener(event: PrestamoDesembolsado::class)]
    public function handle(PrestamoDesembolsado $event): void
    {
        $this->procesarDesembolso($event->prestamo);
    }

    private function procesarDesembolso(Prestamo $prestamo): void
    {
        Log::info('DesembolsoListener: Procesando desembolso', ['prestamo_id' => $prestamo->id]);

        $fechaDesembolso = ($prestamo->fecha_desembolso ?? now())->toDateString();

        // Safety checks
        $yaTieneCuotasGrupales     = CuotasGrupales::where('prestamo_id', $prestamo->id)->exists();
        $yaTieneCuotasIndividuales = CuotaIndividual::where('prestamo_id', $prestamo->id)->exists();
        $esRetanqueo               = stripos($prestamo->descripcion ?? '', 'RETANQUEO') !== false;
        $tienePrestamoAntiguo      = Retanqueo::where('prestamo_nuevo_id', $prestamo->id)->exists();

        if ($yaTieneCuotasIndividuales || $esRetanqueo || $tienePrestamoAntiguo) {
            Log::info('DesembolsoListener: Cuotas NO creadas por seguridad', [
                'prestamo_id'               => $prestamo->id,
                'ya_tiene_cuotas_individuales' => $yaTieneCuotasIndividuales,
            ]);
            return;
        }

        if (!$prestamo->fecha_desembolso) {
            throw new \Exception('No se puede crear las cuotas sin fecha de desembolso');
        }

        $fechaInicio    = Carbon::parse($prestamo->fecha_desembolso);
        $dias           = match ($prestamo->frecuencia) {
            'mensual'   => 30,
            'quincenal' => 15,
            'semanal'   => 7,
            default     => 30,
        };
        $cantidadCuotas = $prestamo->cantidad_cuotas;

        $clientes = $this->obtenerClientesDelPrestamo($prestamo);

        if ($clientes->isEmpty()) {
            Log::warning('DesembolsoListener: No hay clientes para crear cuotas', ['prestamo_id' => $prestamo->id]);
            return;
        }

        $prestamosIndividuales = $prestamo->prestamoIndividual()->get()->keyBy('cliente_id');

        foreach ($clientes as $cliente) {
            $prestamoInd = $prestamosIndividuales->get($cliente->id);

            if (!$prestamoInd) {
                Log::warning('DesembolsoListener: Cliente sin PrestamoIndividual', [
                    'prestamo_id' => $prestamo->id,
                    'cliente_id'  => $cliente->id,
                ]);
                continue;
            }

            $capitalPorCuota  = round($prestamoInd->monto_prestado_individual / $cantidadCuotas, 2);
            $interesPorCuota  = round($prestamoInd->interes / $cantidadCuotas, 2);
            $seguroPorCuota   = round(($prestamoInd->seguro ?? 0) / $cantidadCuotas, 2);

            for ($i = 1; $i <= $cantidadCuotas; $i++) {
                $fechaVencimiento = $fechaInicio->copy()->addDays($dias * $i);

                CuotaIndividual::create([
                    'prestamo_id'            => $prestamo->id,
                    'cliente_id'             => $cliente->id,
                    'numero_cuota'           => $i,
                    'monto_capital_original' => $capitalPorCuota,
                    'monto_interes_original' => $interesPorCuota,
                    'monto_seguro'           => $seguroPorCuota,
                    'saldo_capital'          => $capitalPorCuota,
                    'saldo_interes'          => $interesPorCuota,
                    'fecha_vencimiento'      => $fechaVencimiento,
                    'estado'                 => 'pendiente',
                ]);
            }

            Log::info('DesembolsoListener: Cuotas individuales creadas', [
                'prestamo_id' => $prestamo->id,
                'cliente_id'  => $cliente->id,
                'cantidad'    => $cantidadCuotas,
            ]);
        }

        if (!$yaTieneCuotasGrupales && $prestamo->esGrupal()) {
            $this->crearCuotasGrupalesCompatibilidad($prestamo, $fechaInicio, $dias);
        }

        $this->registrarDesembolso($prestamo, $fechaDesembolso);
    }

    private function obtenerClientesDelPrestamo(Prestamo $prestamo)
    {
        if ($prestamo->esIndividual() && $prestamo->cliente_id) {
            return collect([$prestamo->cliente]);
        }

        if ($prestamo->grupo) {
            return $prestamo->grupo->clientes;
        }

        return collect();
    }

    private function crearCuotasGrupalesCompatibilidad(Prestamo $prestamo, Carbon $fechaInicio, int $dias): void
    {
        $montoTotalDevolver = $prestamo->monto_devolver;
        $cantidadCuotas     = $prestamo->cantidad_cuotas;
        $montoPorCuota      = $montoTotalDevolver / $cantidadCuotas;

        for ($i = 1; $i <= $cantidadCuotas; $i++) {
            $fechaVencimiento = $fechaInicio->copy()->addDays($dias * $i);

            CuotasGrupales::create([
                'prestamo_id'           => $prestamo->id,
                'numero_cuota'          => $i,
                'monto_cuota_grupal'    => round($montoPorCuota, 2),
                'fecha_vencimiento'     => $fechaVencimiento,
                'estado_cuota_grupal'   => 'vigente',
                'estado_pago'           => 'pendiente',
            ]);
        }

        Log::info('DesembolsoListener: Cuotas grupales creadas (compatibilidad)', ['prestamo_id' => $prestamo->id]);
    }

    private function registrarDesembolso(Prestamo $prestamo, string $fechaAprobacion): void
    {
        $existeMovimiento = MovimientoFinanciero::where('referencia_tipo', Prestamo::class)
            ->where('referencia_id', $prestamo->id)
            ->where('concepto', 'desembolso')
            ->exists();

        if (!$existeMovimiento) {
            MovimientoFinanciero::registrarDesembolso($prestamo);
            Log::info('DesembolsoListener: MovimientoFinanciero creado', ['prestamo_id' => $prestamo->id]);
        }

        $descripcion = $prestamo->esGrupal() && $prestamo->grupo
            ? 'Desembolso al grupo ' . $prestamo->grupo->nombre_grupo
            : 'Desembolso préstamo #' . $prestamo->id;

        $existeEgreso = Egreso::where('prestamo_id', $prestamo->id)
            ->where('tipo_egreso', 'desembolso')
            ->exists();

        if (!$existeEgreso) {
            Egreso::create([
                'tipo_egreso'    => 'desembolso',
                'prestamo_id'    => $prestamo->id,
                'fecha'          => $fechaAprobacion,
                'monto'          => $prestamo->monto_prestado_total,
                'descripcion'    => $descripcion,
                'categoria_id'   => null,
                'subcategoria_id' => null,
            ]);
            Log::info('DesembolsoListener: Egreso creado (compatibilidad)', ['prestamo_id' => $prestamo->id]);
        }
    }
}
