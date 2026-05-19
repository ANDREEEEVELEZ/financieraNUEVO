<?php

namespace Database\Factories;

use App\Models\CuotaIndividual;
use App\Models\Cliente;
use App\Models\Prestamo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CuotaIndividual>
 */
class CuotaIndividualFactory extends Factory
{
    protected $model = CuotaIndividual::class;

    public function definition(): array
    {
        $capital = $this->faker->randomFloat(2, 50, 300);
        $interes = $this->faker->randomFloat(2, 5, 30);

        return [
            'prestamo_id'             => Prestamo::factory(),
            'cliente_id'              => Cliente::factory(),
            'numero_cuota'            => $this->faker->numberBetween(1, 12),
            'monto_capital_original'  => $capital,
            'monto_interes_original'  => $interes,
            'saldo_capital'           => $capital,
            'saldo_interes'           => $interes,
            'estado'                  => 'pendiente',
            'fecha_vencimiento'       => now()->addDays(15)->toDateString(),
        ];
    }

    public function vencida(): static
    {
        return $this->state([
            'estado'            => 'vencida',
            'fecha_vencimiento' => now()->subDays(10)->toDateString(),
        ]);
    }

    public function pagada(): static
    {
        return $this->state([
            'estado'        => 'pagada',
            'saldo_capital' => 0,
            'saldo_interes' => 0,
        ]);
    }
}
