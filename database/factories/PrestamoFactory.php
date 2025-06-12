<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Prestamo;
use App\Models\Cliente;
use App\Models\Grupo;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PrestamoIndividual>
 */
class PrestamoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
             'grupo_id' => Grupo::factory(),
            'tasa_interes' => $this->faker->randomFloat(2, 2, 10),
            'monto_prestado_total' => $this->faker->randomFloat(2, 500, 5000),
            'monto_devolver' => $this->faker->randomFloat(2, 600, 6000),
            'cantidad_cuotas' => $this->faker->numberBetween(4, 12),
            'fecha_prestamo' => $this->faker->date('Y-m-d'),
            'frecuencia' => $this->faker->randomElement(['semanal', 'quincenal','mensual']),
            'estado' => $this->faker->randomElement(['vigente', 'cancelado']),
            'calificacion' => $this->faker->randomFloat(2, 0, 10),
        ];
    }
}
