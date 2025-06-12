<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Retanqueo;
use App\Models\Cliente;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\RetanqueoIndividual>
 */
class RetanqueoIndividualFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
             'retanqueo_id' => Retanqueo::factory(),
            'cliente_id' => Cliente::factory(),
            'monto_solicitado' => $this->faker->randomFloat(2, 100, 1000),
            'monto_desembolsar' => $this->faker->randomFloat(2, 100, 1000),
            'monto_cuota_retanqueo' => $this->faker->randomFloat(2, 20, 200),
            'estado_retanqueo_individual' => $this->faker->randomElement(['pendiente', 'aprobado']),
        ];
    }
}
