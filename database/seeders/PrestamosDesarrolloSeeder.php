<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Cliente;
use App\Models\CuotaIndividual;
use App\Models\Grupo;
use App\Models\Prestamo;
use App\Models\ProductoFinanciero;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * Crea préstamos de desarrollo con cuotas individuales para los grupos existentes.
 *
 * Debe ejecutarse DESPUÉS de ClientesGruposSeeder (necesita grupos con clientes).
 */
class PrestamosDesarrolloSeeder extends Seeder
{
    public function run(): void
    {
        $producto = ProductoFinanciero::where('codigo', 'CI-EMPRENDEDOR')->first();

        if (! $producto) {
            throw new \RuntimeException('Ejecutá ProductoFinancieroSeeder primero — CI-EMPRENDEDOR no existe.');
        }

        $grupos = Grupo::all();

        if ($grupos->isEmpty()) {
            throw new \RuntimeException('Ejecutá ClientesGruposSeeder primero — no hay grupos.');
        }

        foreach ($grupos as $grupo) {
            $this->crearPrestamoConCuotasIndividuales(
                grupo: $grupo,
                estado: 'Pendiente',
                cantidadCuotas: 6,
                producto: $producto,
            );
        }
    }

    /**
     * Crea un préstamo grupal con cuotas individuales para cada integrante.
     *
     * @return Prestamo
     */
    public function crearPrestamoConCuotasIndividuales(
        Grupo $grupo,
        string $estado,
        int $cantidadCuotas,
        ProductoFinanciero $producto,
    ): Prestamo {
        $montoPorCliente = 500.0;
        $totalClientes = $grupo->clientes()->count();
        $montoTotal = $montoPorCliente * $totalClientes;
        $interes = $montoTotal * ($producto->tasa_interes ?? 0.12);

        $prestamo = Prestamo::create([
            'grupo_id'              => $grupo->id,
            'tasa_interes'          => $producto->tasa_interes * 100,
            'monto_prestado_total'  => $montoTotal,
            'monto_devolver'        => $montoTotal + $interes,
            'cantidad_cuotas'       => $cantidadCuotas,
            'fecha_prestamo'        => now(),
            'frecuencia'            => 'mensual',
            'estado'                => $estado,
            'descripcion'           => "Préstamo desarrollo para {$grupo->nombre_grupo}",
            'titular_cuenta_desembolso' => "Presidente {$grupo->nombre_grupo}",
            'numero_cuenta_desembolso'  => '0011-0223-DEV',
            'tipo'                  => 'grupal',
        ]);

        $prestamo->producto_id = $producto->id;
        $prestamo->save();

        $this->crearCuotasIndividuales($prestamo, $grupo, $cantidadCuotas, $montoPorCliente);

        return $prestamo;
    }

    /**
     * Crea cuotas individuales para cada cliente del grupo.
     */
    private function crearCuotasIndividuales(
        Prestamo $prestamo,
        Grupo $grupo,
        int $cantidadCuotas,
        float $montoPorCliente,
    ): void {
        $vencimiento = CarbonImmutable::now()->addMonth();

        /** @var list<Cliente> $clientes */
        $clientes = $grupo->clientes()->get()->all();

        $montoInteres = $montoPorCliente * 0.12;
        $montoCuotaCapital = $montoPorCliente / $cantidadCuotas;

        foreach ($clientes as $cliente) {
            for ($i = 1; $i <= $cantidadCuotas; $i++) {
                CuotaIndividual::create([
                    'prestamo_id'            => $prestamo->id,
                    'cliente_id'             => $cliente->id,
                    'numero_cuota'           => $i,
                    'monto_capital_original' => $montoCuotaCapital,
                    'monto_interes_original' => $montoInteres / $cantidadCuotas,
                    'saldo_capital'          => $montoCuotaCapital,
                    'saldo_interes'          => $montoInteres / $cantidadCuotas,
                    'fecha_vencimiento'      => $vencimiento->addMonths($i - 1),
                    'estado'                 => 'pendiente',
                ]);
            }
        }
    }
}
