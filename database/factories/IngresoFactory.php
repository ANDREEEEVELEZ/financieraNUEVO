<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Ingreso;
use App\Models\Pago;
use App\Models\Grupo;


/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Ingreso>
 */
class IngresoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tipo_ingreso' => $this->faker->randomElement(['transferencia', 'pago de cuota de grupo']),
            'pago_id' => Pago::factory(),
            'grupo_id' => Grupo::factory(),
            'fecha_hora' => $this->faker->dateTime(),
            'descripcion' => $this->faker->sentence(),
            'monto' => $this->faker->randomFloat(2, 100, 2000),

        ];
    }
}
