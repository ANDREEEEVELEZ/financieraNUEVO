<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Prestamo;
use App\Models\Cliente;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PrestamoIndividual>
 */
class PrestamoIndividualFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'prestamo_id' => Prestamo::factory(),
            'cliente_id' => Cliente::factory(),
            'monto_prestado_individual' => $this->faker->randomFloat(2, 100, 1000),
            'monto_cuota_prestamo_individual' => $this->faker->randomFloat(2, 20, 200),
            'monto_devolver_individual' => $this->faker->randomFloat(2, 120, 1200),
            'seguro' => $this->faker->randomFloat(2, 5, 50),
            'interes' => $this->faker->randomFloat(2, 1, 5),
            'estado' => $this->faker->randomElement(['vigente', 'cancelado']),
        ];
    }
}
